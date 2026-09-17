<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'وقود', 'name_en' => 'Fuel', 'key' => 'fuel', 'icon' => '⛽', 'color' => '#ef4444', 'sort_order' => 1],
            ['name' => 'تنقلات', 'name_en' => 'Transportation', 'key' => 'transportation', 'icon' => '🚗', 'color' => '#3b82f6', 'sort_order' => 2],
            ['name' => 'تنقلات', 'name_en' => 'Transport', 'key' => 'transport', 'icon' => '🚗', 'color' => '#3b82f6', 'sort_order' => 3],
            ['name' => 'إيجار المكتب', 'name_en' => 'Office Rent', 'key' => 'office_rent', 'icon' => '🏢', 'color' => '#6366f1', 'sort_order' => 4],
            ['name' => 'إنترنت واتصالات', 'name_en' => 'Internet and Communications', 'key' => 'internet_communications', 'icon' => '📱', 'color' => '#8b5cf6', 'sort_order' => 5],
            ['name' => 'اتصالات', 'name_en' => 'Communications', 'key' => 'communications', 'icon' => '📱', 'color' => '#8b5cf6', 'sort_order' => 6],
            ['name' => 'استضافة', 'name_en' => 'Hosting', 'key' => 'hosting', 'icon' => '☁️', 'color' => '#0ea5e9', 'sort_order' => 7],
            ['name' => 'اشتراكات برمجية', 'name_en' => 'Software Subscriptions', 'key' => 'software_subscriptions', 'icon' => '💻', 'color' => '#14b8a6', 'sort_order' => 8],
            ['name' => 'تسويق', 'name_en' => 'Marketing', 'key' => 'marketing', 'icon' => '📣', 'color' => '#f97316', 'sort_order' => 9],
            ['name' => 'رواتب وأجور', 'name_en' => 'Salaries and Wages', 'key' => 'salaries_wages', 'icon' => '👥', 'color' => '#22c55e', 'sort_order' => 10],
            ['name' => 'خدمات مهنية', 'name_en' => 'Professional Services', 'key' => 'professional_services', 'icon' => '🧾', 'color' => '#64748b', 'sort_order' => 11],
            ['name' => 'صيانة', 'name_en' => 'Maintenance', 'key' => 'maintenance', 'icon' => '🛠️', 'color' => '#a855f7', 'sort_order' => 12],
            ['name' => 'مكتب ومستلزمات', 'name_en' => 'Office Supplies', 'key' => 'office_supplies', 'icon' => '📎', 'color' => '#10b981', 'sort_order' => 13],
            ['name' => 'مكتب ومستلزمات', 'name_en' => 'Office', 'key' => 'office', 'icon' => '🏢', 'color' => '#10b981', 'sort_order' => 14],
            ['name' => 'ضيافة', 'name_en' => 'Hospitality', 'key' => 'hospitality', 'icon' => '☕', 'color' => '#f59e0b', 'sort_order' => 15],
            ['name' => 'قانونية ومحاسبية', 'name_en' => 'Legal and Accounting', 'key' => 'legal_accounting', 'icon' => '⚖️', 'color' => '#475569', 'sort_order' => 16],
            ['name' => 'أخرى', 'name_en' => 'Other', 'key' => 'other', 'icon' => '📦', 'color' => '#64748b', 'sort_order' => 99],
        ];

        foreach ($categories as $cat) {
            $category = ExpenseCategory::firstOrCreate(
                ['key' => $cat['key']],
                [
                    'code' => $cat['key'],
                    'name' => $cat['name'],
                    'name_ar' => $cat['name'],
                    'name_en' => $cat['name_en'],
                    'icon' => $cat['icon'],
                    'color' => $cat['color'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                ]
            );

            $updates = [];
            foreach (['code' => $cat['key'], 'name_ar' => $cat['name'], 'name_en' => $cat['name_en']] as $field => $value) {
                if (empty($category->{$field})) {
                    $updates[$field] = $value;
                }
            }
            if ($updates !== []) {
                $category->update($updates);
            }
        }
    }
}
