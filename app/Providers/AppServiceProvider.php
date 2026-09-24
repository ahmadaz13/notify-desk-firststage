<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Contract;
use App\Policies\ClientPolicy;
use App\Policies\ContractPolicy;
use App\Services\NotificationService;
use App\Support\Permissions;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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

        // P12 interaction system: one pagination look everywhere, and field a11y state for forms.
        Paginator::defaultView('pagination.notify');
        Paginator::defaultSimpleView('pagination.notify');
        Blade::directive('invalid', fn (string $expression) => "<?php echo \App\Support\FormState::attributes(\$errors ?? null, {$expression}); ?>");
        Blade::directive('formscope', fn (string $expression) => "<?php \App\Support\FormState::begin({$expression}); ?>");
        Blade::directive('endformscope', fn () => '<?php \App\Support\FormState::end(); ?>');
    }
}
