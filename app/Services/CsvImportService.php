<?php

namespace App\Services;

use App\Models\Client;
use App\Support\ClientLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CsvImportService
{
    private readonly ClientPrimaryContactService $primaryContacts;

    public function __construct(?ClientPrimaryContactService $primaryContacts = null)
    {
        $this->primaryContacts = $primaryContacts ?? app(ClientPrimaryContactService::class);
    }

    /**
     * Optional primary_phone_type column (§28.1). A missing/blank value defaults to "business": the stored
     * default for every client created before P4, where clients.phone was also copied into business_phone.
     * Returns null for an unknown value so the row is reported as invalid instead of guessed.
     */
    public function primaryPhoneType(array $data): ?string
    {
        $type = strtolower(trim((string) ($data['primary_phone_type'] ?? '')));

        if ($type === '') {
            return ClientPrimaryContactService::BUSINESS;
        }

        return in_array($type, Client::PRIMARY_PHONE_TYPES, true) ? $type : null;
    }

    /**
     * Normalize a phone number by stripping non-digit characters and standardizing format.
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        
        // Remove leading international code for Jordan (962 or 00962)
        if (str_starts_with($digits, '00962')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '962')) {
            $digits = substr($digits, 3);
        }

        // Standardize leading 0
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Parse and analyze a CSV file for preview, identifying valid, duplicate, and invalid rows.
     *
     * @param string $filePath
     * @param string $type legacy UI hint; normal V1 import always creates prospects
     * @return array
     */
    public function previewCsv(string $filePath, string $type = 'prospect'): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException('ملف CSV غير موجود أو غير قابل للقراءة');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('تعذر فتح ملف CSV');
        }

        // Detect and remove UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [
                'type' => $type,
                'total' => 0,
                'valid' => [],
                'duplicates' => [],
                'invalid' => [],
            ];
        }

        // Clean headers
        $cleanedHeaders = array_map(function ($col) {
            return trim(strtolower((string) $col));
        }, $header);

        // Fetch all existing clients phones from database for duplicate check
        $existingClients = DB::table('clients')->select('id', 'business_name', 'phone')->get();
        $dbPhoneMap = [];
        foreach ($existingClients as $c) {
            $norm = $this->normalizePhone($c->phone);
            if ($norm !== '') {
                $dbPhoneMap[$norm] = $c->business_name;
            }
        }

        $filePhones = [];
        $valid = [];
        $duplicates = [];
        $invalid = [];
        $rowIndex = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            if (empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $data = [];
            foreach ($cleanedHeaders as $i => $colName) {
                $data[$colName] = isset($row[$i]) ? trim($row[$i]) : '';
            }

            $businessName = $data['business_name'] ?? '';
            $phone = $data['phone'] ?? '';
            $cityArea = $data['city_area'] ?? '';
            $businessCategory = $data['business_category'] ?? '';

            // Validation checks
            $errors = [];
            if ($businessName === '') {
                $errors[] = 'اسم النشاط التجاري مطلوب';
            }
            if ($phone === '') {
                $errors[] = 'رقم الهاتف مطلوب';
            }
            if ($cityArea === '') {
                $errors[] = 'المنطقة مطلوبة';
            }
            if ($businessCategory === '') {
                $errors[] = 'فئة النشاط مطلوبة';
            }
            if ($this->primaryPhoneType($data) === null) {
                $errors[] = __('notify.clients.contact_model.import_invalid_phone_type');
            }
            $contactEmail = $data['contact_email'] ?? '';
            if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = __('notify.clients.contact_model.import_invalid_email');
            }

            if (!empty($errors)) {
                $invalid[] = [
                    'row' => $rowIndex,
                    'data' => $data,
                    'reason' => implode('، ', $errors),
                ];
                continue;
            }

            $normalizedPhone = $this->normalizePhone($phone);

            // Duplicate checks
            if (isset($filePhones[$normalizedPhone])) {
                $duplicates[] = [
                    'row' => $rowIndex,
                    'data' => $data,
                    'normalized_phone' => $normalizedPhone,
                    'reason' => 'رقم هاتف مكرر داخل نفس الملف (السطر ' . $filePhones[$normalizedPhone] . ')',
                ];
                continue;
            }

            if (isset($dbPhoneMap[$normalizedPhone])) {
                $duplicates[] = [
                    'row' => $rowIndex,
                    'data' => $data,
                    'normalized_phone' => $normalizedPhone,
                    'reason' => 'رقم الهاتف مسجل مسبقاً في النظام لعميل: ' . $dbPhoneMap[$normalizedPhone],
                ];
                continue;
            }

            // Valid row
            $filePhones[$normalizedPhone] = $rowIndex;
            $valid[] = [
                'row' => $rowIndex,
                'data' => $data,
                'normalized_phone' => $normalizedPhone,
            ];
        }

        fclose($handle);

        return [
            'type' => $type,
            'total' => count($valid) + count($duplicates) + count($invalid),
            'valid' => $valid,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
        ];
    }

    /**
     * Import confirmed valid rows into the database.
     *
     * @param array $validRows Array of rows containing 'data'
     * @param string $type legacy UI hint; normal V1 import always creates prospects
     * @param int $userId
     * @return int Count of imported clients
     */
    public function importValidRows(array $validRows, string $type, int $userId): int
    {
        return DB::transaction(function () use ($validRows, $type, $userId) {
            $importedCount = 0;
            $now = now();
            $user = \App\Models\User::find($userId);

            if (!$user || !$user->isActiveApplicationUser()) {
                throw new AuthorizationException('CSV import is limited to internal Notify users.');
            }

            foreach ($validRows as $item) {
                $data = $item['data'] ?? $item;
                $primaryPhoneType = $this->primaryPhoneType($data);

                if ($primaryPhoneType === null) {
                    throw new \InvalidArgumentException(__('notify.clients.contact_model.import_invalid_phone_type'));
                }

                $client = new Client([
                    'business_name' => $data['business_name'],
                    'city_area' => $data['city_area'],
                    'city' => !empty($data['city']) ? $data['city'] : $data['city_area'],
                    'area' => !empty($data['area']) ? $data['area'] : null,
                    'business_category' => $data['business_category'],
                    'business_type' => !empty($data['business_type']) ? $data['business_type'] : $data['business_category'],
                    'lead_source' => !empty($data['lead_source']) ? $data['lead_source'] : 'CSV Import',
                    'source_reference' => !empty($data['source_reference']) ? $data['source_reference'] : null,
                    'location_text' => !empty($data['location_text']) ? $data['location_text'] : null,
                    'primary_owner_id' => $userId,
                    'status' => 'prospect',
                    'stage' => ClientLifecycle::PROSPECT,
                    'notes' => !empty($data['notes']) ? $data['notes'] : null,
                ]);

                // Same §28.1 rules as manual creation; names are never guessed, only taken from the file.
                $this->primaryContacts->sync(
                    $client,
                    $primaryPhoneType,
                    $data['phone'],
                    $data['business_phone'] ?? null,
                    [
                        'name' => ($data['contact_name'] ?? '') !== '' ? $data['contact_name'] : ($data['contact_person'] ?? null),
                        'role' => $data['contact_role'] ?? null,
                        'phone' => $data['contact_phone'] ?? null,
                        'whatsapp' => $data['contact_whatsapp'] ?? null,
                        'email' => $data['contact_email'] ?? null,
                    ]
                );
                $clientId = $client->id;
                $importedCount++;

                // Append activity log
                DB::table('activity_logs')->insert([
                    'client_id' => $clientId,
                    'user_id' => $userId,
                    'type' => 'client_imported',
                    'description' => 'تم استيراد العميل بنجاح من ملف CSV كفرصة تشغيلية',
                    'metadata' => json_encode([
                        'requested_import_type' => $type,
                        'policy' => 'normal_csv_import_does_not_create_subscriber_or_billing_records',
                        'referral_policy' => 'csv_import_leaves_referral_metadata_empty',
                        'primary_phone_type' => $primaryPhoneType,
                        'primary_phone_type_source' => ($data['primary_phone_type'] ?? '') === '' ? 'default_business' : 'file',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $importedCount;
        });
    }
}
