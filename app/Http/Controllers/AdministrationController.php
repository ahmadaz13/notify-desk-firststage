<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use App\Support\ShellNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    protected function checkAdmin(): void
    {
        abort_unless(Permissions::allows(auth()->user(), Permissions::MANAGE_TEAM), 403, __('notify.team.forbidden'));
    }

    /**
     * Founder account protection (§2.1 hardening): only a Founder may promote a user to Founder,
     * change a Founder's role, deactivate a Founder, or reset a Founder's password.
     */
    protected function protectFounder(User $member): void
    {
        abort_if($member->isFounder() && ! auth()->user()->isFounder(), 403, __('notify.team.founder_protected'));
    }

    protected function requireFounder(): void
    {
        abort_unless(auth()->user()->isFounder(), 403, __('notify.team.founder_protected'));
    }

    /**
     * Administration index (§15): orientation only — one line per canonical destination, no forms.
     */
    public function index(): View
    {
        Gate::authorize(Permissions::MANAGE_COMMERCIAL_CATALOG);

        $user = auth()->user();
        $facts = [
            'systems' => __('notify.administration.active_count', ['count' => Product::where('is_active', true)->whereNull('archived_at')->count()]),
            'team' => Permissions::allows($user, Permissions::MANAGE_TEAM)
                ? __('notify.administration.members_count', ['count' => User::where('is_active', true)->whereIn('role', User::activeInternalRoles())->count()])
                : null,
        ];

        return view('administration.index', [
            'destinations' => ShellNavigation::administrationDestinations($user),
            'facts' => $facts,
        ]);
    }

    // ─────────────────────────────────────────────
    // Team & Roles
    // ─────────────────────────────────────────────

    public function team(): View
    {
        $this->checkAdmin();

        return $this->teamView();
    }

    /** Add member opens as a sheet on the team page (P12); the URL stays linkable. */
    public function teamCreate(): View
    {
        $this->checkAdmin();

        return $this->teamView('team-add');
    }

    public function teamEdit(int $id): View
    {
        $this->checkAdmin();
        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);

        return $this->teamView('team-edit-'.$member->id);
    }

    public function teamStore(Request $request): RedirectResponse
    {
        $this->checkAdmin();

        // Team creation never creates a Founder (§2.1).
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', 'in:admin,staff'],
            'job_title' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:50',
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'job_title' => $validated['job_title'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return redirect()->route('administration.team')
            ->with('success', __('notify.team.created'));
    }

    public function teamUpdate(Request $request, int $id): RedirectResponse
    {
        $this->checkAdmin();

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);
        $isSelf = $member->id === auth()->id();
        $requestedActive = $request->boolean('is_active');

        // Prevent demoting or deactivating yourself.
        if ($isSelf && $request->input('role') !== $member->role) {
            return back()->withInput()->withErrors(['role' => __('notify.team.cannot_change_own_role')]);
        }
        if ($isSelf && ! $requestedActive) {
            return back()->withInput()->withErrors(['is_active' => __('notify.team.cannot_deactivate_self')]);
        }

        if ($request->input('role') !== $member->role || $requestedActive !== (bool) $member->is_active) {
            $this->protectFounder($member);
        }

        if ($request->input('role') === User::ROLE_FOUNDER && ! $member->isFounder()) {
            $this->requireFounder();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$member->id,
            'role' => ['required', 'in:founder,admin,staff'],
            'job_title' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable',
        ]);

        $member->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'job_title' => $request->has('job_title') ? ($validated['job_title'] ?? null) : $member->job_title,
            'phone' => $request->has('phone') ? ($validated['phone'] ?? null) : $member->phone,
            'is_active' => $requestedActive,
        ]);

        return redirect()->route('administration.team')
            ->with('success', __('notify.team.updated'));
    }

    public function teamResetPassword(Request $request, int $id): RedirectResponse
    {
        $this->checkAdmin();

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);
        $this->protectFounder($member);

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $member->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('administration.team')
            ->with('success', __('notify.team.password_reset'));
    }

    public function teamDeactivate(int $id): RedirectResponse
    {
        $this->checkAdmin();

        if ($id === auth()->id()) {
            return back()->withErrors(['error' => __('notify.team.cannot_deactivate_self')]);
        }

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);
        $this->protectFounder($member);
        $member->update(['is_active' => false]);

        return redirect()->route('administration.team')
            ->with('success', __('notify.team.deactivated'));
    }

    /** Reactivation follows the same Founder protection as deactivation. */
    public function teamActivate(int $id): RedirectResponse
    {
        $this->checkAdmin();

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);
        $this->protectFounder($member);
        $member->update(['is_active' => true]);

        return redirect()->route('administration.team')
            ->with('success', __('notify.team.activated'));
    }

    /**
     * Team list with per-member abilities resolved once here (no permission checks per row in the
     * view). Forbidden Founder actions are not offered at all rather than shown disabled.
     */
    private function teamView(?string $openSheet = null): View
    {
        $actor = auth()->user();
        $actorIsFounder = $actor->isFounder();

        $teamMembers = User::whereIn('role', User::activeInternalRoles())
            ->orderByRaw("CASE role WHEN 'founder' THEN 1 WHEN 'admin' THEN 2 WHEN 'staff' THEN 3 ELSE 4 END")
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $abilities = $teamMembers->mapWithKeys(function (User $member) use ($actor, $actorIsFounder) {
            $isSelf = $member->id === $actor->id;
            $protected = $member->isFounder() && ! $actorIsFounder;

            return [$member->id => [
                'self' => $isSelf,
                'change_role' => ! $isSelf && ! $protected,
                'roles' => $actorIsFounder ? ['founder', 'admin', 'staff'] : ['admin', 'staff'],
                'toggle_active' => ! $isSelf && ! $protected,
                'reset_password' => ! $protected,
            ]];
        });

        return view('administration.team', [
            'teamMembers' => $teamMembers,
            'abilities' => $abilities,
            'openSheet' => $openSheet,
        ]);
    }
}
