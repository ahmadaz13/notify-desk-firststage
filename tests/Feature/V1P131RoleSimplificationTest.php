<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $defaultAdmin = $this->insertUser('legacy-default@example.test', null); // legacy column default = admin
        $founder = $this->insertUser('owner@example.test', 'founder');
        $staff = $this->insertUser('staff@example.test', 'staff');
        $this->assertSame('admin', DB::table('users')->where('id', $defaultAdmin)->value('role'));

        $this->retireAdminRole();
        $this->retireAdminRole(); // idempotent

        $this->assertSame('staff', DB::table('users')->where('id', $plainAdmin)->value('role'));
        $this->assertSame('staff', DB::table('users')->where('id', $defaultAdmin)->value('role'));
        $this->assertSame('founder', DB::table('users')->where('id', $founder)->value('role'));
        $this->assertSame('staff', DB::table('users')->where('id', $staff)->value('role'));
        $this->assertSame(0, DB::table('users')->where('role', 'admin')->count());
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
