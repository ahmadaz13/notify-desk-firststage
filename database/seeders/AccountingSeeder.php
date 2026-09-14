<?php

namespace Database\Seeders;

use App\Services\AccountingSetupService;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        app(AccountingSetupService::class)->ensureSeeded();
    }
}

