<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * M-12: Capital & Financing feature flag, default OFF (§13, D-05). An existing value is kept.
     */
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'feature_capital_financing')->exists()) {
            DB::table('settings')->insert([
                'key' => 'feature_capital_financing',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'feature_capital_financing')->delete();
    }
};
