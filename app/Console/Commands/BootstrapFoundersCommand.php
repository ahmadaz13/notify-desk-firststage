<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapFoundersCommand extends Command
{
    protected $signature = 'notify:bootstrap-founders {--reset-passwords : Intentionally reset founder passwords from environment configuration}';

    protected $description = 'Create or classify the required Notify founder accounts without exposing credentials.';

    public function handle(): int
    {
        $founders = config('founder_accounts.accounts', []);
        $resetPasswords = (bool) $this->option('reset-passwords');

        if (! is_array($founders) || count($founders) === 0) {
            $this->error('Founder account configuration is missing.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $passwordsReset = 0;

        foreach ($founders as $key => $founder) {
            $name = trim((string) ($founder['name'] ?? Str::headline((string) $key)));
            $email = Str::lower(trim((string) ($founder['email'] ?? '')));
            $password = (string) ($founder['password'] ?? '');

            if ($email === '') {
                $this->error("Founder [{$key}] email is missing.");

                return self::FAILURE;
            }

            if ($password === '') {
                $this->error("Founder [{$email}] password environment value is missing.");

                return self::FAILURE;
            }

            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $user) {
                User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_FOUNDER,
                    'is_active' => true,
                    'partner_id' => null,
                    'email_verified_at' => now(),
                ]);

                $created++;
                $this->info("Created founder account: {$email}");

                continue;
            }

            $changes = [];

            if ($user->role !== User::ROLE_FOUNDER) {
                $changes['role'] = User::ROLE_FOUNDER;
            }

            if ($user->is_active !== true) {
                $changes['is_active'] = true;
            }

            if ($user->partner_id !== null) {
                $changes['partner_id'] = null;
            }

            if ($resetPasswords) {
                $changes['password'] = Hash::make($password);
                $passwordsReset++;
            }

            if ($changes !== []) {
                $user->forceFill($changes)->save();
                $updated++;
                $this->line("Updated founder classification: {$email}");
            } else {
                $unchanged++;
                $this->line("Founder already current: {$email}");
            }
        }

        $this->info("Founder bootstrap complete. created={$created}; updated={$updated}; unchanged={$unchanged}; password_resets={$passwordsReset}");

        return self::SUCCESS;
    }
}
