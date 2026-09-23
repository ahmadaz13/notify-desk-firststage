<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-8 data (owner decision, P3 hardening): explicit V1 System identities for Smart Link, E-Menu,
     * E-Store and Auto SMS, with their credential capability (§6, §18.1).
     *
     * - Auto SMS reuses the existing `auto_sms_system` product (same business System).
     * - Smart Link, E-Menu and E-Store had no Systems row; `restaurant_system` / `digital_store_system`
     *   are not proven equivalent, so canonical rows are created. Existing systems are never renamed or deleted.
     *
     * Idempotent: rows are found by stable code; only requires_credentials is enforced on existing rows.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'requires_credentials')) {
            return;
        }

        $now = now();
        foreach (Product::V1_SYSTEM_IDENTITIES as $code => $system) {
            $existing = DB::table('products')->where('code', $code)->first();

            if ($existing !== null) {
                DB::table('products')->where('id', $existing->id)->update([
                    'requires_credentials' => $system['requires_credentials'],
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('products')->insert([
                'code' => $code,
                'name_ar' => $system['name_ar'],
                'name_en' => $system['name_en'],
                'is_active' => true,
                'requires_credentials' => $system['requires_credentials'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Systems may already carry subscriptions or access rows; they are not deleted on rollback.
     */
    public function down(): void
    {
    }
};
