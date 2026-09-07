<?php

namespace App\Services;

use Carbon\Carbon;
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
     * @param string $type 'prospect' or 'subscriber'
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

            if ($type === 'subscriber') {
                $billingType = strtolower($data['billing_type'] ?? '');
                if (!in_array($billingType, ['monthly', 'annual', 'installment'])) {
                    $errors[] = 'نوع الفوترة غير صالح (يجب أن يكون: monthly, annual, installment)';
                }
                if (!isset($data['total_price']) || !is_numeric($data['total_price']) || (float) $data['total_price'] <= 0) {
                    $errors[] = 'السعر الإجمالي مطلوب ورقم موجب';
                }
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
     * @param string $type 'prospect' or 'subscriber'
     * @param int $userId
     * @return int Count of imported clients
     */
    public function importValidRows(array $validRows, string $type, int $userId): int
    {
        return DB::transaction(function () use ($validRows, $type, $userId) {
            $importedCount = 0;
            $now = now();

            foreach ($validRows as $item) {
                $data = $item['data'] ?? $item;

                $clientData = [
                    'business_name' => $data['business_name'],
                    'phone' => $data['phone'],
                    'contact_person' => !empty($data['contact_person']) ? $data['contact_person'] : null,
                    'city_area' => $data['city_area'],
                    'business_category' => $data['business_category'],
                    'lead_source' => !empty($data['lead_source']) ? $data['lead_source'] : 'CSV Import',
                    'primary_owner_id' => $userId,
                    'status' => $type === 'subscriber' ? 'subscriber' : 'prospect',
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
                    'description' => 'تم استيراد العميل بنجاح من ملف CSV كـ ' . ($type === 'subscriber' ? 'مشترك' : 'فرصة'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // If subscriber, create subscription and payment schedules
                if ($type === 'subscriber') {
                    $billingType = strtolower($data['billing_type'] ?? 'monthly');
                    $totalPrice = (float) $data['total_price'];
                    $startDate = !empty($data['start_date']) ? Carbon::parse($data['start_date'])->toDateString() : $now->toDateString();
                    $installmentsCount = !empty($data['installments_count']) ? (int) $data['installments_count'] : ($billingType === 'installment' ? 2 : null);

                    $count = $billingType === 'annual' ? 1 : ($billingType === 'installment' ? ($installmentsCount ?: 2) : 12);

                    $subscriptionId = DB::table('subscriptions')->insertGetId([
                        'client_id' => $clientId,
                        'user_id' => $userId,
                        'billing_type' => $billingType,
                        'total_price' => $totalPrice,
                        'start_date' => $startDate,
                        'renewal_date' => Carbon::parse($startDate)->addYear()->toDateString(),
                        'installments_count' => $installmentsCount,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $per = round($totalPrice / $count, 2);
                    for ($i = 0; $i < $count; $i++) {
                        $amountDue = ($i === $count - 1) ? $totalPrice - ($per * ($count - 1)) : $per;
                        $dueDate = Carbon::parse($startDate)->addMonths($billingType === 'annual' ? 0 : $i)->toDateString();

                        DB::table('payment_schedules')->insert([
                            'subscription_id' => $subscriptionId,
                            'amount_due' => $amountDue,
                            'due_date' => $dueDate,
                            'status' => ($i === 0 && Carbon::parse($dueDate)->isPast()) ? 'due' : 'upcoming',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            return $importedCount;
        });
    }
}
