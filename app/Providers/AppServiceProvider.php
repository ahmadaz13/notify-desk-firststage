<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Contract;
use App\Policies\ClientPolicy;
use App\Policies\ContractPolicy;
use App\Services\NotificationService;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);

        foreach (Permissions::ALL as $permission) {
            Gate::define($permission, fn ($user) => Permissions::allows($user, $permission));
        }

        View::composer('components.notify.app-shell', function ($view): void {
            $view->with('unreadCount', auth()->check()
                ? app(NotificationService::class)->getUnreadCount((int) auth()->id())
                : 0);
        });
    }
}
