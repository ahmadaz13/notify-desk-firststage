<?php

use App\Services\CompanyAccountBootstrapService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-1: ensure the fixed CASH-BOX and CLIQ company accounts and their chart mappings.
     * Idempotent; find-by-code, never duplicates.
     */
    public function up(): void
    {
        if (! Schema::hasTable('financial_accounts') || ! Schema::hasTable('chart_accounts')) {
            return;
        }

        app(CompanyAccountBootstrapService::class)->ensureDefaults();
    }

    /**
     * Company accounts may carry posted cash movements; they are never deleted on rollback.
     */
    public function down(): void
    {
    }
};
