<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P13.1 — V1 role simplification (owner decision D-25): Founder and Staff are the only active roles.
 * Admin is a deferred post-V1 value that never inherits Founder rights.
 */
class V1P131RoleSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private function retireAdminRole(): void
    {
        $migration = require database_path('migrations/2026_09_28_000100_retire_admin_role_for_v1.php');
        $migration->up();
    }

    private function insertUser(string $email, ?string $role): int
    {
        $row = ['name' => $email, 'email' => $email, 'password' => 'x', 'created_at' => now(), 'updated_at' => now()];
        if ($role !== null) {
            $row['role'] = $role;
        }

        return DB::table('users')->insertGetId($row);
    }

    public function test_existing_admin_rows_become_staff_never_founder(): void
    {
        $plainAdmin = $this->insertUser('ops@example.test', 'admin');
        $secondAdmin = $this->insertUser('legacy-second@example.test', 'admin');
        $founder = $this->insertUser('owner@example.test', 'founder');
        $staff = $this->insertUser('staff@example.test', 'staff');

        $this->retireAdminRole();
        $this->retireAdminRole(); // idempotent

        $this->assertSame('staff', DB::table('users')->where('id', $plainAdmin)->value('role'));
        $this->assertSame('staff', DB::table('users')->where('id', $secondAdmin)->value('role'));
        $this->assertSame('founder', DB::table('users')->where('id', $founder)->value('role'));
        $this->assertSame('staff', DB::table('users')->where('id', $staff)->value('role'));
        $this->assertSame(0, DB::table('users')->where('role', 'admin')->count());
    }

    private function roleColumnDefault(): string
    {
        return trim((string) collect(Schema::getColumns('users'))->firstWhere('name', 'role')['default'], "'\" ()");
    }

    /** P13.1 hardening: the database itself defaults users.role to Staff (fresh schema). */
    public function test_database_default_for_role_is_staff(): void
    {
        $this->assertSame('staff', $this->roleColumnDefault());

        $raw = $this->insertUser('raw-insert@example.test', null);
        $this->assertSame('staff', DB::table('users')->where('id', $raw)->value('role'));

        $raw = User::find($raw);
        $this->assertFalse($raw->isOwnerLevelInternalUser());
        $this->assertTrue($raw->isActiveApplicationUser());

        // Admin stays representable (future compatibility) but grants nothing.
        $dormant = $this->insertUser('dormant@example.test', 'admin');
        $this->assertSame('admin', DB::table('users')->where('id', $dormant)->value('role'));
        $this->assertFalse(User::find($dormant)->isActiveApplicationUser());
    }

    /**
     * Databases created before the fix (users.role defaulting to 'admin') are corrected by the forward
     * migration: only the default changes; columns, rows and indexes are kept.
     */
    public function test_forward_migration_corrects_a_legacy_admin_default(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite rebuild path; MySQL uses ALTER ... MODIFY.');
        }

        $founder = $this->insertUser('kept-founder@example.test', 'founder');
        $create = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql');

        // Recreate the pre-fix definition (default 'admin') with the same columns and rows.
        $legacy = preg_replace("/(\"?role\"?\s[^,]*?default\s+\(?)'staff'/i", "$1'admin'", $create, 1);
        $legacy = preg_replace('/^CREATE TABLE\s+"?users"?/i', 'CREATE TABLE "users_legacy"', $legacy, 1);
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'users')->whereNotNull('sql')->pluck('sql')->all();
        DB::statement($legacy);
        DB::statement('INSERT INTO users_legacy SELECT * FROM users');
        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_legacy RENAME TO users');
        foreach ($indexes as $index) {
            DB::statement($index);
        }
        $this->assertSame('admin', $this->roleColumnDefault());

        $migration = require database_path('migrations/2026_09_28_000200_set_users_role_database_default_to_staff.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame('staff', $this->roleColumnDefault());
        $this->assertSame('founder', DB::table('users')->where('id', $founder)->value('role'), 'existing rows are not rewritten');
        $this->assertSame('staff', DB::table('users')->where('id', $this->insertUser('after-fix@example.test', null))->value('role'));
        $this->assertEqualsCanonicalizing(
            collect($indexes)->map(fn ($sql) => strtolower($sql))->all(),
            DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'users')->whereNotNull('sql')->pluck('sql')->map(fn ($sql) => strtolower($sql))->all(),
            'indexes are restored'
        );
        $this->assertSame(
            collect(Schema::getColumns('users'))->pluck('name')->all(),
            ['id', 'name', 'email', 'role', 'is_active', 'email_verified_at', 'password', 'remember_token', 'first_login_at', 'reset_expires_at', 'created_at', 'updated_at', 'phone', 'job_title', 'avatar_path'],
        );
    }

    public function test_only_repository_evidenced_founder_accounts_keep_owner_access(): void
    {
        config(['founder_accounts.accounts.ahmad.email' => 'Ahmad@NotifyDisk.com']);
        $configured = $this->insertUser('ahmad@notifydisk.com', 'admin');
        $demo = $this->insertUser('khalid@example.com', 'admin');
        $other = $this->insertUser('someone@example.test', 'admin');

        $this->retireAdminRole();

        $this->assertSame('founder', DB::table('users')->where('id', $configured)->value('role'));
        $this->assertSame('founder', DB::table('users')->where('id', $demo)->value('role'));
        $this->assertSame('staff', DB::table('users')->where('id', $other)->value('role'));
    }

    public function test_new_users_default_to_staff_and_demo_seed_creates_two_founders(): void
    {
        $user = User::create(['name' => 'No Role', 'email' => 'norole@example.test', 'password' => 'secret123']);
        $this->assertSame(User::ROLE_STAFF, $user->fresh()->role);
        $this->assertSame(User::ROLE_STAFF, User::factory()->create()->role);

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->assertSame(
            ['ahmad@example.com' => 'founder', 'khalid@example.com' => 'founder'],
            DB::table('users')->whereIn('email', ['ahmad@example.com', 'khalid@example.com'])->orderBy('email')->pluck('role', 'email')->all()
        );
        $this->assertSame(0, DB::table('users')->where('role', 'admin')->count());
    }

    public function test_staff_operational_permissions_are_unchanged(): void
    {
        $this->assertEqualsCanonicalizing([
            Permissions::SUBMIT_PAYMENT_RECEIPT, Permissions::MANAGE_SYSTEM_ACCESS, Permissions::START_PAID_SUBSCRIPTION,
            Permissions::VIEW_COLLECTIONS_DUE, Permissions::MANAGE_CLIENT_CREDENTIALS, Permissions::REVEAL_CLIENT_CREDENTIALS,
            Permissions::VIEW_CUSTOM_PROJECTS,
        ], Permissions::forRole(User::ROLE_STAFF));
        $this->assertEqualsCanonicalizing(Permissions::ALL, Permissions::forRole(User::ROLE_FOUNDER));
        $this->assertSame([], Permissions::forRole(User::ROLE_ADMIN));
    }
}
