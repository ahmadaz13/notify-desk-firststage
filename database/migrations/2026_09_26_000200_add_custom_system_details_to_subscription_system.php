<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * P8 owner decision: a Custom System line carries its own short project title and description,
     * captured when the subscription starts and copied into the contract snapshot.
     */
    public function up(): void
    {
        if (! Schema::hasTable('subscription_system')) {
            return;
        }

        Schema::table('subscription_system', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_system', 'custom_title')) {
                $table->string('custom_title', 160)->nullable()->after('system_name_en_snapshot');
            }
            if (! Schema::hasColumn('subscription_system', 'custom_description')) {
                $table->string('custom_description', 300)->nullable()->after('custom_title');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_system')) {
            return;
        }

        Schema::table('subscription_system', function (Blueprint $table) {
            foreach (['custom_description', 'custom_title'] as $column) {
                if (Schema::hasColumn('subscription_system', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
