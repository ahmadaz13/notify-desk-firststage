<?php

namespace App\Providers;

use App\Models\Client;
use App\Policies\ClientPolicy;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        App::setLocale('ar');
        Gate::policy(Client::class, ClientPolicy::class);
    }
}
