<?php

use App\Support\ClientLifecycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * M-11: copy meaningful legacy clients.status values into stage where stage is null (§4).
     * Idempotent: rows that already have a stage are never touched, and unknown values stay null.
     */
    public function up(): void
    {
        DB::table('clients')->whereNull('stage')->where('status', 'archived')->update(['stage' => ClientLifecycle::CLOSED]);

        foreach (ClientLifecycle::STAGES as $stage) {
            DB::table('clients')->whereNull('stage')->where('status', $stage)->update(['stage' => $stage]);
        }
    }

    /**
     * Data backfill: not reversible without losing track of which rows were copied.
     */
    public function down(): void
    {
    }
};
