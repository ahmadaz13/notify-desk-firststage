<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Browser smoke tests only (§32, FROZEN D-23): adds a Staff login next to the demo Owner from
 * DatabaseSeeder. Runs against the throw-away e2e database, never production.
 */
class BrowserSmokeSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Sara', 'password' => Hash::make('password'), 'role' => User::ROLE_STAFF, 'is_active' => true],
        );
    }
}
