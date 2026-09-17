<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'operational_cost_percentage' => '20',
            'market_valuation_multiplier' => '5',
            'allow_auto_transfer_clients' => 'false',
            'annual_discount_percentage' => '10.00',
            'sales_tax_percentage' => '16.00',
            'monthly_due_day' => '1',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
