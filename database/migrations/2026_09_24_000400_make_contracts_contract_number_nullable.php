<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-7: contracts.contract_number becomes nullable so the number can be assigned at Issue (P8).
     * The existing unique index is kept; SQLite and MySQL both allow multiple NULLs under UNIQUE.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('contract_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('contracts')->whereNull('contract_number')->exists()) {
            throw new RuntimeException('Cannot make contracts.contract_number NOT NULL: unnumbered contracts exist.');
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('contract_number')->nullable(false)->change();
        });
    }
};
