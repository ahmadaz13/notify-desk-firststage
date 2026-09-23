<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
     * Administration hub – shows summary cards for all 6 sections.
     */
    public function index(): View
    {
        Gate::authorize(Permissions::MANAGE_COMMERCIAL_CATALOG);

        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        // Products & Pricing summary
        $activeProducts = Product::where('is_active', true)->whereNull('archived_at')->count();
        $totalProducts  = Product::count();

        // Team summary (admin only)
        $activeTeamMembers = $isAdmin ? User::where('is_active', true)
            ->whereIn('role', User::activeInternalRoles())
            ->count() : null;

        return view('administration.index', compact(
            'isAdmin',
            'activeProducts',
            'totalProducts',
            'activeTeamMembers',
        ));
    }

    // ─────────────────────────────────────────────
    // Team & Permissions
    // ─────────────────────────────────────────────

    public function team(): View
    {
        $this->checkAdmin();

        $teamMembers = User::whereIn('role', User::activeInternalRoles())
            ->orderByRaw("CASE role WHEN 'founder' THEN 1 WHEN 'admin' THEN 2 WHEN 'staff' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get();

        return view('administration.team', compact('teamMembers'));
    }

    public function teamCreate(): View
    {
        $this->checkAdmin();
        return view('administration.team-create');
    }

    public function teamStore(Request $request): RedirectResponse
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'role'     => ['required', 'in:admin,staff'],
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'password'  => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return redirect()->route('administration.team')
            ->with('success', __('notify.team.created'));
    }

    public function teamEdit(int $id): View
    {
        $this->checkAdmin();

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);

        return view('administration.team-edit', compact('member'));
    }

    public function teamUpdate(Request $request, int $id): RedirectResponse
    {
        $this->checkAdmin();

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);

        // Prevent demoting yourself
        if ($member->id === auth()->id() && $request->input('role') !== $member->role) {
            return back()->withErrors(['role' => __('notify.team.cannot_change_own_role')]);
        }

        if ($request->input('role') !== $member->role || ! $request->has('is_active')) {
            $this->protectFounder($member);
        }

        if ($request->input('role') === User::ROLE_FOUNDER && ! $member->isFounder()) {
            $this->requireFounder();
        }

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email,' . $member->id,
            'role'      => ['required', 'in:founder,admin,staff'],
            'is_active' => 'nullable',
        ]);

        $member->update([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'is_active' => $request->has('is_active'),
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
            'password' => 'required|string|min:8|confirmed',
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
}
