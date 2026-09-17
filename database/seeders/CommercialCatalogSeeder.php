<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Seeder;

class CommercialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $restaurantSystem = Product::firstOrCreate(
            ['code' => 'restaurant_system'],
            [
                'name_ar' => 'نظام المطاعم',
                'name_en' => 'Restaurant System',
                'description_ar' => null,
                'description_en' => null,
                'is_active' => true,
            ]
        );

        Product::firstOrCreate(
            ['code' => 'digital_store_system'],
            [
                'name_ar' => 'نظام المتجر الرقمي',
                'name_en' => 'Digital Store System',
                'description_ar' => null,
                'description_en' => null,
                'is_active' => true,
            ]
        );

        Product::firstOrCreate(
            ['code' => 'auto_sms_system'],
            [
                'name_ar' => 'نظام الرسائل النصية التلقائية',
                'name_en' => 'Auto SMS System',
                'description_ar' => null,
                'description_en' => null,
                'is_active' => true,
            ]
        );

        $plans = [
            [
                'code' => 'restaurant_package_1',
                'name_ar' => 'الباقة الأولى',
                'tier' => 1,
                'monthly_jod' => '15.000',
                'annual_jod' => '144.000',
            ],
            [
                'code' => 'restaurant_package_2',
                'name_ar' => 'الباقة الثانية',
                'tier' => 2,
                'monthly_jod' => '25.000',
                'annual_jod' => '240.000',
            ],
            [
                'code' => 'restaurant_package_3',
                'name_ar' => 'الباقة الثالثة',
                'tier' => 3,
                'monthly_jod' => '40.000',
                'annual_jod' => '384.000',
            ],
        ];

        foreach ($plans as $seed) {
            $plan = Plan::firstOrCreate(
                ['code' => $seed['code']],
                [
                    'product_id' => $restaurantSystem->id,
                    'tier' => $seed['tier'],
                    'offer_type' => 'package',
                    'name_ar' => $seed['name_ar'],
                    'name_en' => null,
                    'description_ar' => null,
                    'description_en' => null,
                    'is_active' => true,
                ]
            );

            $plan->update([
                'product_id' => $plan->product_id ?: $restaurantSystem->id,
                'tier' => $plan->tier ?: $seed['tier'],
                'offer_type' => $plan->offer_type ?: 'package',
            ]);

            $this->seedPriceIfMissing($plan, PlanPrice::MONTHLY, $seed['monthly_jod']);
            $this->seedPriceIfMissing($plan, PlanPrice::ANNUAL, $seed['annual_jod']);
        }
    }

    private function seedPriceIfMissing(Plan $plan, string $interval, string $amountJod): void
    {
        $hasAnyPrice = PlanPrice::where('plan_id', $plan->id)
            ->where('billing_interval', $interval)
            ->exists();

        if ($hasAnyPrice) {
            return;
        }

        PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => Money::fromJod($amountJod)->minorUnits(),
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'additional_branch_price_minor' => null,
            'default_tax_rate_bps' => null,
            'effective_from' => now(),
            'effective_until' => null,
            'is_active' => true,
        ]);
    }
}
