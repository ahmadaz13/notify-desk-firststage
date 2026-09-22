<?php

namespace App\Services;

use App\Support\ClientLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CsvImportService
{
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
            $leadSource = $data['lead_source'] ?? 'CSV Import';
            $contactPerson = $data['contact_person'] ?? null;
            $notes = $data['notes'] ?? null;

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

                $clientData = [
                    'business_name' => $data['business_name'],
                    'phone' => $data['phone'],
                    'business_phone' => $data['business_phone'] ?? $data['phone'],
                    'contact_person' => !empty($data['contact_person']) ? $data['contact_person'] : null,
                    'city_area' => $data['city_area'],
                    'city' => $data['city'] ?? $data['city_area'],
                    'area' => $data['area'] ?? null,
                    'business_category' => $data['business_category'],
                    'business_type' => $data['business_type'] ?? $data['business_category'],
                    'lead_source' => !empty($data['lead_source']) ? $data['lead_source'] : 'CSV Import',
                    'source_reference' => !empty($data['source_reference']) ? $data['source_reference'] : null,
                    'primary_owner_id' => $userId,
                    'status' => 'prospect',
                    'stage' => ClientLifecycle::PROSPECT,
                    'notes' => !empty($data['notes']) ? $data['notes'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $clientId = DB::table('clients')->insertGetId($clientData);
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
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (!empty($data['contact_person'])) {
                    DB::table('client_contacts')->insert([
                        'client_id' => $clientId,
                        'name' => $data['contact_person'],
                        'role' => null,
                        'primary_phone' => $data['phone'],
                        'secondary_phone' => null,
                        'whatsapp_number' => null,
                        'preferred_contact_method' => null,
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

            }

            return $importedCount;
        });
    }
}
