<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-9: clients.primary_phone_type (business|owner|manager), default business (§28.1).
     * clients.address is not added: clients.location_text already holds the free-text address/location.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'primary_phone_type')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('primary_phone_type', 16)->default('business');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'primary_phone_type')) {
            Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('primary_phone_type'));
        }
    }
};
