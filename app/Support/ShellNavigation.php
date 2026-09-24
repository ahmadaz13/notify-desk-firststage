<?php

namespace App\Support;

use App\Models\Client;
use App\Models\PaymentReceiptConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Single source of the application shell navigation (§3, P9).
 *
 * Visibility is derived from the P2 permission matrix and feature flags only; there are no role
 * names here. Hiding a destination is UX only — every controller still authorizes on its own.
 * Active state is derived from route-name families so exactly one destination is current.
 */
class ShellNavigation
{
    /** Finance sections in sidebar/segmented-control order (§3.1, §12). */
    private const FINANCE_SECTIONS = [
        'overview' => ['route' => 'finance.index', 'patterns' => ['finance.index'], 'icon' => 'layout-dashboard', 'permission' => Permissions::VIEW_FINANCIAL_STATEMENTS],
        'collections' => ['route' => 'finance.collections', 'patterns' => ['finance.collections'], 'icon' => 'wallet', 'permission' => Permissions::VIEW_FINANCIAL_REPORTS],
        'expenses' => ['route' => 'finance.expenses', 'patterns' => ['finance.expenses'], 'icon' => 'receipt', 'permission' => Permissions::VIEW_EXPENSE_MANAGEMENT],
        'accounts' => ['route' => 'finance.accounts', 'patterns' => ['finance.accounts'], 'icon' => 'landmark', 'permission' => Permissions::VIEW_CASH_MANAGEMENT],
        'accounting' => ['route' => 'finance.accounting', 'patterns' => ['finance.accounting'], 'icon' => 'book-open', 'permission' => Permissions::VIEW_ACCOUNTING],
        'reports' => ['route' => 'finance.reports', 'patterns' => ['finance.reports', 'finance.reports.*'], 'icon' => 'file-chart', 'permission' => Permissions::VIEW_FINANCIAL_STATEMENTS],
        'capital' => ['route' => 'finance.capital', 'patterns' => ['finance.capital'], 'icon' => 'trending-up', 'permission' => Permissions::VIEW_CAPITAL_MANAGEMENT, 'feature' => Features::CAPITAL],
    ];

    /** Administration destinations in §3.1 order (P13 adds Operational Reference Data). */
    private const ADMINISTRATION_ITEMS = [
        'systems' => ['route' => 'commercial-catalog.index', 'patterns' => ['commercial-catalog.*'], 'icon' => 'package', 'permission' => Permissions::MANAGE_COMMERCIAL_CATALOG],
        'team' => ['route' => 'administration.team', 'patterns' => ['administration.team', 'administration.team.*'], 'icon' => 'user-cog', 'permission' => Permissions::MANAGE_TEAM],
        'reference-data' => ['route' => 'administration.reference-data', 'patterns' => ['administration.reference-data', 'administration.reference-data.*'], 'icon' => 'clipboard-list', 'permission' => Permissions::MANAGE_REFERENCE_DATA],
        'import' => ['route' => 'clients.import', 'patterns' => ['clients.import', 'clients.import.*'], 'icon' => 'upload', 'permission' => Permissions::IMPORT_CLIENTS],
        'settings' => ['route' => 'settings.index', 'patterns' => ['settings.*'], 'icon' => 'settings', 'permission' => Permissions::MANAGE_COMPANY_SETTINGS],
    ];

    private const AREA_PATTERNS = [
        'today' => ['dashboard'],
        'custom-projects' => ['custom-projects.*', 'clients.custom-projects.*'],
        'collections-due' => ['collections-due.*'],
        'finance' => ['finance.*'],
        'administration' => ['administration.*', 'commercial-catalog.*', 'settings.*', 'clients.import', 'clients.import.*'],
        'clients' => ['clients.*'],
        'notifications' => ['notifications.*'],
        'profile' => ['profile.*'],
    ];

    /** @var array<int, array<string, mixed>> */
    public array $primary = [];

    /** @var array<string, array<string, mixed>> */
    public array $groups = [];

    /** @var array<int, array<string, mixed>> */
    public array $tabs = [];

    /** @var array<string, array<string, mixed>> */
    public array $moreSections = [];

    public bool $moreActive = false;

    public ?string $area = null;

    public string $areaLabel;

    public function __construct(private readonly User $user, private readonly ?string $routeName, int $unreadNotifications = 0)
    {
        $this->area = $this->resolveArea();
        $this->areaLabel = $this->area === null ? __('notify.shell_nav.app') : __('notify.shell_nav.areas.'.$this->area);

        $this->buildDesktop();
        $this->buildMobile($unreadNotifications);
    }

    public static function forCurrentRequest(User $user, int $unreadNotifications = 0): self
    {
        return new self($user, request()->route()?->getName(), $unreadNotifications);
    }

    /**
     * Finance sections the user may open (sidebar group and in-page segmented control share this).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function financeSections(User $user, ?string $routeName = null): array
    {
        $sections = [];
        foreach (self::FINANCE_SECTIONS as $key => $section) {
            if (isset($section['feature']) && ! Features::enabled($section['feature'])) {
                continue;
            }
            if (! Permissions::allows($user, $section['permission'])) {
                continue;
            }
            $sections[$key] = [
                'key' => $key,
                'destination' => 'finance-'.$key,
                'label' => __('notify.finance_hub.sections.'.$key),
                'icon' => $section['icon'],
                'href' => route($section['route']),
                'active' => self::matches($routeName, $section['patterns']),
                'badge' => null,
            ];
        }

        return $sections;
    }

    private function buildDesktop(): void
    {
        $this->primary[] = $this->item('today', 'home', route('dashboard', ['mode' => 'daily']));

        if ($this->canViewClients()) {
            $this->primary[] = $this->item('clients', 'users', route('clients.index'));
        }

        if ($this->showsCollectionsDue()) {
            $this->primary[] = $this->item('collections-due', 'wallet', route('collections-due.index'));
        }

        if ($this->canViewCustomProjects()) {
            $this->primary[] = $this->item('custom-projects', 'folder-kanban', route('custom-projects.index'));
        }

        $finance = self::financeSections($this->user, $this->routeName);
        if (isset($finance['collections']) && Permissions::allows($this->user, Permissions::APPROVE_PAYMENT_RECEIPTS)) {
            $pending = PaymentReceiptConfirmation::query()->pending()->count();
            $finance['collections']['badge'] = $pending > 0 ? $pending : null;
        }
        if ($finance !== []) {
            $this->groups['finance'] = [
                'key' => 'finance',
                'label' => __('notify.shell_nav.areas.finance'),
                'active' => $this->area === 'finance',
                'items' => array_values($finance),
            ];
        }

        $administration = $this->administrationItems();
        if ($administration !== []) {
            $this->groups['administration'] = [
                'key' => 'administration',
                'label' => __('notify.shell_nav.areas.administration'),
                'active' => $this->area === 'administration',
                'items' => $administration,
            ];
        }
    }

    private function buildMobile(int $unreadNotifications): void
    {
        $this->tabs[] = $this->item('today', 'home', route('dashboard', ['mode' => 'daily']));

        if ($this->canViewClients()) {
            $this->tabs[] = $this->item('clients', 'users', route('clients.index'));
        }

        // Third tab: Finance for users who hold Finance Overview; otherwise the operational
        // Collections due list (§3.4).
        $financeSections = $this->groups['finance']['items'] ?? [];
        $overview = collect($financeSections)->firstWhere('key', 'overview');
        if ($overview !== null) {
            $this->tabs[] = [
                'destination' => 'finance',
                'label' => __('notify.shell_nav.areas.finance'),
                'icon' => 'chart',
                'href' => $overview['href'],
                'active' => $this->area === 'finance',
                'badge' => null,
            ];
        } elseif ($this->showsCollectionsDue()) {
            $tab = $this->item('collections-due', 'wallet', route('collections-due.index'));
            $tab['label'] = __('notify.shell_nav.collections_tab');
            $this->tabs[] = $tab;
        }

        $work = [];
        if ($this->canViewCustomProjects()) {
            $work[] = $this->item('custom-projects', 'folder-kanban', route('custom-projects.index'));
        }
        $notifications = $this->item('notifications', 'bell', route('notifications.index'));
        $notifications['badge'] = $unreadNotifications > 0 ? $unreadNotifications : null;
        $work[] = $notifications;
        $this->moreSections['work'] = ['key' => 'work', 'label' => __('notify.shell_nav.sections.work'), 'items' => $work];

        if (isset($this->groups['administration'])) {
            $this->moreSections['administration'] = [
                'key' => 'administration',
                'label' => $this->groups['administration']['label'],
                'items' => $this->groups['administration']['items'],
            ];
        }

        $this->moreActive = in_array($this->area, ['custom-projects', 'administration', 'notifications', 'profile'], true);
    }

    /** @return array<int, array<string, mixed>> */
    private function administrationItems(): array
    {
        return self::administrationDestinations($this->user, $this->routeName);
    }

    /**
     * Administration destinations the user may open (sidebar group, More sheet and the
     * /administration index share this list).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function administrationDestinations(User $user, ?string $routeName = null): array
    {
        $items = [];
        foreach (self::ADMINISTRATION_ITEMS as $key => $definition) {
            if (! Permissions::allows($user, $definition['permission'])) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'destination' => 'admin-'.$key,
                'label' => __('notify.shell_nav.administration.'.$key),
                'icon' => $definition['icon'],
                'href' => route($definition['route']),
                'active' => self::matches($routeName, $definition['patterns']),
                'badge' => null,
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function item(string $area, string $icon, string $href): array
    {
        return [
            'key' => $area,
            'destination' => $area,
            'label' => __('notify.shell_nav.areas.'.$area),
            'icon' => $icon,
            'href' => $href,
            'active' => $this->area === $area,
            'badge' => null,
        ];
    }

    private function resolveArea(): ?string
    {
        foreach (self::AREA_PATTERNS as $area => $patterns) {
            if (self::matches($this->routeName, $patterns)) {
                return $area;
            }
        }

        return null;
    }

    private function canViewClients(): bool
    {
        return Gate::forUser($this->user)->allows('viewAny', Client::class);
    }

    private function canViewCustomProjects(): bool
    {
        return Permissions::allows($this->user, Permissions::VIEW_CUSTOM_PROJECTS);
    }

    /** The operational list is for users without the full Collections & Receivables page (§3.2, §9.7). */
    private function showsCollectionsDue(): bool
    {
        return Permissions::allows($this->user, Permissions::VIEW_COLLECTIONS_DUE)
            && ! Permissions::allows($this->user, Permissions::VIEW_FINANCIAL_REPORTS);
    }

    /** @param  array<int, string>  $patterns */
    private static function matches(?string $routeName, array $patterns): bool
    {
        if ($routeName === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === $routeName || (str_ends_with($pattern, '.*') && str_starts_with($routeName, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }
}
