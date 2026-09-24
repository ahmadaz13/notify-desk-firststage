<?php

use App\Services\ReferenceDataService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * P13 owner decision: the active Notify business types are the client_category reference list
     * (Restaurants, Phone shops, Travel & Tourism, Farms / Chalets). Idempotent; never overwrites owner
     * edits. Existing client values are untouched and keep rendering as stored.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reference_options')) {
            return;
        }

        app(ReferenceDataService::class)->ensureDefaults([ReferenceDataService::CLIENT_CATEGORY]);
    }

    public function down(): void
    {
        // Data seed only: options may already be referenced by clients, so nothing is removed.
    }
};
