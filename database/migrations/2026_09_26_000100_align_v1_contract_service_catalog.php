<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * P8 owner decision: V1 business service catalog used on contracts.
     *
     * Stable codes remain the identity. Existing systems keep their id, code, Arabic name, credential
     * capability and every subscription/access link; only the business display name (name_en) is aligned
     * and a short description is filled where the owner defined one and none is stored yet.
     * E-Menu / E-Store and Restaurant System / Store System stay separate products.
     * Clink Management, CRM + AI Tool and Custom System are created with the owner-defined name only.
     */
    private const EXISTING = [
        'smart_link' => [
            'name_en' => 'Smart-Link Premium',
            'description_ar' => 'صفحة رقمية ذكية للنشاط التجاري وروابطه.',
            'description_en' => 'Smart digital business and link page.',
        ],
        'auto_sms_system' => [
            'name_en' => 'Auto SMS Sender',
            'description_ar' => 'خدمة تواصل تلقائي مع العملاء عبر الرسائل النصية.',
            'description_en' => 'Automatic SMS communication service.',
        ],
        'e_menu' => [
            'name_en' => 'E-Menu',
            'description_ar' => 'خدمة قائمة رقمية.',
            'description_en' => 'Digital menu service.',
        ],
        'e_store' => [
            'name_en' => 'E-Store',
            'description_ar' => 'خدمة متجر رقمي.',
            'description_en' => 'Digital storefront service.',
        ],
        'restaurant_system' => [
            'name_en' => 'Restaurant System',
            'description_ar' => 'نظام متكامل لإدارة المطعم يشمل نقطة البيع والخدمات المرتبطة بالنظام.',
            'description_en' => 'Integrated restaurant management system including point-of-sale and related system services.',
        ],
        'digital_store_system' => [
            'name_en' => 'Store System',
            'description_ar' => 'نظام متكامل لإدارة المتجر يشمل نقطة البيع والخدمات المرتبطة بالنظام.',
            'description_en' => 'Integrated store management system including point-of-sale and related system services.',
        ],
    ];

    private const MISSING = [
        'clink_management' => 'Clink Management',
        'crm_ai_tool' => 'CRM + AI Tool',
        'custom_system' => 'Custom System',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $now = now();
        $hasDescriptions = Schema::hasColumn('products', 'description_ar') && Schema::hasColumn('products', 'description_en');

        foreach (self::EXISTING as $code => $catalog) {
            $product = DB::table('products')->where('code', $code)->first();
            if ($product === null) {
                continue;
            }

            $updates = ['name_en' => $catalog['name_en'], 'updated_at' => $now];
            if ($hasDescriptions) {
                foreach (['description_ar', 'description_en'] as $column) {
                    if (blank($product->{$column})) {
                        $updates[$column] = $catalog[$column];
                    }
                }
            }
            DB::table('products')->where('id', $product->id)->update($updates);
        }

        foreach (self::MISSING as $code => $name) {
            if (DB::table('products')->where('code', $code)->exists()) {
                continue;
            }

            $row = ['code' => $code, 'name_ar' => $name, 'name_en' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            if (Schema::hasColumn('products', 'requires_credentials')) {
                $row['requires_credentials'] = false;
            }
            DB::table('products')->insert($row);
        }
    }

    /**
     * Catalog rows may already carry subscriptions; data is not rolled back.
     */
    public function down(): void
    {
    }
};
