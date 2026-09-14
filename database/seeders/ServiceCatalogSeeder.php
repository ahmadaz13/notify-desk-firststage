<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'key' => 'smart_link',
                'name_ar' => 'الرابط الذكي',
                'name_en' => 'Smart Link',
                'description' => 'رابط ذكي وموحد يجمع كافة منصات التواصل والخدمات للمنشأة مع تتبع الزيارات والتحليلات.',
                'default_price' => 50.000,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'e_menu',
                'name_ar' => 'القائمة الإلكترونية',
                'name_en' => 'E-Menu',
                'description' => 'منيو رقمي تفاعلي مدعوم برمز QR وتحديث فوري للأسعار والأطباق والتصنيفات.',
                'default_price' => 100.000,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'e_store',
                'name_ar' => 'المتجر الإلكتروني',
                'name_en' => 'E-Store',
                'description' => 'متجر إلكتروني متكامل لإدارة المنتجات، السلة، وتلقي الطلبات والدفع الإلكتروني.',
                'default_price' => 250.000,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'auto_sms_sender',
                'name_ar' => 'الرسائل النصية التلقائية',
                'name_en' => 'Auto SMS Sender',
                'description' => 'نظام إرسال الرسائل القصيرة SMS التلقائية للحملات وتأكيد المواعيد والفواتير.',
                'default_price' => 80.000,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'e_landing_page',
                'name_ar' => 'صفحة الهبوط الإلكترونية',
                'name_en' => 'E-Landing Page',
                'description' => 'صفحة هبوط تسويقية مخصصة ومحسنة لتحويل الزوار إلى عملاء مع نماذج اتصال تفاعلية.',
                'default_price' => 120.000,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'key' => 'restaurant_pos',
                'name_ar' => 'نظام نقاط البيع للمطاعم',
                'name_en' => 'Restaurant POS',
                'description' => 'منظومة نقاط بيع سحابية متقدمة لإدارة الطاولات، طلبات المطبخ، والفواتير وطباعة الإيصالات للمطاعم والمقاهي.',
                'default_price' => 350.000,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'key' => 'store_management',
                'name_ar' => 'إدارة المتجر',
                'name_en' => 'Store Management',
                'description' => 'نظام إدارة شامل للمخزون، المبيعات، حسابات الموردين، وإدارة فروع المتاجر التجارية ونقاط البيع.',
                'default_price' => 300.000,
                'is_active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['key' => $service['key']],
                $service
            );
        }
    }
}
