<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('plans')) {
            return;
        }

        $now = now();
        $products = [
            'restaurant_system' => [
                'name_ar' => 'نظام المطاعم',
                'name_en' => 'Restaurant System',
            ],
            'digital_store_system' => [
                'name_ar' => 'نظام المتجر الرقمي',
                'name_en' => 'Digital Store System',
            ],
            'auto_sms_system' => [
                'name_ar' => 'نظام الرسائل النصية التلقائية',
                'name_en' => 'Auto SMS System',
            ],
        ];

        foreach ($products as $code => $product) {
            $existingId = DB::table('products')->where('code', $code)->value('id');

            if ($existingId) {
                DB::table('products')->where('id', $existingId)->update([
                    'name_ar' => $product['name_ar'],
                    'name_en' => $product['name_en'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('products')->insert([
                'code' => $code,
                'name_ar' => $product['name_ar'],
                'name_en' => $product['name_en'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $restaurantProductId = DB::table('products')->where('code', 'restaurant_system')->value('id');
        if ($restaurantProductId === null) {
            return;
        }

        foreach ([
            'restaurant_package_1' => 1,
            'restaurant_package_2' => 2,
            'restaurant_package_3' => 3,
        ] as $planCode => $tier) {
            DB::table('plans')
                ->where('code', $planCode)
                ->whereNull('product_id')
                ->update([
                    'product_id' => $restaurantProductId,
                    'tier' => $tier,
                    'offer_type' => 'package',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('plans')) {
            return;
        }

        $restaurantProductId = DB::table('products')->where('code', 'restaurant_system')->value('id');
        if ($restaurantProductId !== null) {
            DB::table('plans')
                ->where('product_id', $restaurantProductId)
                ->whereIn('code', ['restaurant_package_1', 'restaurant_package_2', 'restaurant_package_3'])
                ->update([
                    'product_id' => null,
                    'tier' => null,
                    'offer_type' => null,
                    'updated_at' => now(),
                ]);
        }

        DB::table('products')
            ->whereIn('code', ['restaurant_system', 'digital_store_system', 'auto_sms_system'])
            ->whereNotIn('id', DB::table('plans')->select('product_id')->whereNotNull('product_id'))
            ->delete();
    }
};
