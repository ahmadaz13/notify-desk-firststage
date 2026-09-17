<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'computers', 'name_ar' => 'أجهزة كمبيوتر', 'name_en' => 'Computers', 'sort_order' => 1],
            ['code' => 'mobile_devices', 'name_ar' => 'أجهزة محمولة', 'name_en' => 'Mobile Devices', 'sort_order' => 2],
            ['code' => 'pos_hardware', 'name_ar' => 'أجهزة نقاط بيع', 'name_en' => 'POS Hardware', 'sort_order' => 3],
            ['code' => 'network_equipment', 'name_ar' => 'معدات شبكات', 'name_en' => 'Network Equipment', 'sort_order' => 4],
            ['code' => 'office_equipment', 'name_ar' => 'معدات مكتبية', 'name_en' => 'Office Equipment', 'sort_order' => 5],
            ['code' => 'furniture', 'name_ar' => 'أثاث', 'name_en' => 'Furniture', 'sort_order' => 6],
            ['code' => 'vehicles', 'name_ar' => 'مركبات', 'name_en' => 'Vehicles', 'sort_order' => 7],
            ['code' => 'tools', 'name_ar' => 'أدوات', 'name_en' => 'Tools', 'sort_order' => 8],
            ['code' => 'other', 'name_ar' => 'أصول أخرى', 'name_en' => 'Other', 'sort_order' => 99],
        ];

        foreach ($categories as $category) {
            $assetCategory = AssetCategory::firstOrCreate(
                ['code' => $category['code']],
                [
                    'name_ar' => $category['name_ar'],
                    'name_en' => $category['name_en'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );

            $updates = [];
            foreach (['name_ar', 'name_en'] as $field) {
                if (empty($assetCategory->{$field})) {
                    $updates[$field] = $category[$field];
                }
            }
            if ($updates !== []) {
                $assetCategory->update($updates);
            }
        }
    }
}
