<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * P13.1 owner decision: V1 has two active roles, Founder and Staff. Admin is deferred post-V1.
     *
     * Existing role=admin rows are never mapped to Founder by default (that would be a privilege
     * escalation): they become Staff. The only exception is an account the repository identifies as an
     * intentional Founder — the configured Founder accounts (config/founder_accounts.php) and the demo
     * Founders Ahmad and Khalid created by DatabaseSeeder. Founder rows are untouched. Idempotent.
     *
     * The users.role column default is 'staff' (corrected column definitions for fresh installs, and
     * 2026_09_28_000200 for existing databases). A user that still carries 'admin' is not an active
     * application user.
     */
    private const DEMO_FOUNDER_EMAILS = ['ahmad@example.com', 'khalid@example.com'];

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $founderEmails = collect(config('founder_accounts.accounts', []))
            ->pluck('email')
            ->merge(self::DEMO_FOUNDER_EMAILS)
            ->filter()
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values()
            ->all();

        $now = now();

        if ($founderEmails !== []) {
            DB::table('users')
                ->where('role', 'admin')
                ->whereIn(DB::raw('LOWER(email)'), $founderEmails)
                ->update(['role' => 'founder', 'updated_at' => $now]);
        }

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'staff', 'updated_at' => $now]);
    }

    public function down(): void
    {
        // One-way data change: the previous Admin assignments are not recorded and are not restored.
    }
};
