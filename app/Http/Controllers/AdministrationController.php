<?php

namespace App\Http\Controllers;

use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use App\Support\FinancialPermissions;
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
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة');
        }
    }

    /**
     * Administration hub – shows summary cards for all 6 sections.
     */
    public function index(): View
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        // Products & Pricing summary
        $activeProducts = Product::where('is_active', true)->whereNull('archived_at')->count();
        $totalProducts  = Product::count();

        // Partners summary (admin only)
        $activePartners   = $isAdmin ? Partner::where('status', 'active')->count() : null;
        $pendingConflicts = $isAdmin ? ConflictResolutionRequest::where('status', 'pending')->count() : null;

        // Team summary (admin only)
        $activeTeamMembers = $isAdmin ? User::where('is_active', true)
            ->whereIn('role', User::activeInternalRoles())
            ->count() : null;

        return view('administration.index', compact(
            'isAdmin',
            'activeProducts',
            'totalProducts',
            'activePartners',
            'pendingConflicts',
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
            ->orderByRaw("FIELD(role, 'founder', 'admin', 'staff')")
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
            ->with('success', 'تم إنشاء حساب عضو الفريق بنجاح.');
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
            return back()->withErrors(['role' => 'لا يمكنك تغيير دورك الخاص.']);
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
            ->with('success', 'تم تحديث بيانات عضو الفريق بنجاح.');
    }

    public function teamResetPassword(Request $request, int $id): RedirectResponse
    {
        $this->checkAdmin();

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $member->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('administration.team')
            ->with('success', 'تمت إعادة تعيين كلمة المرور بنجاح.');
    }

    public function teamDeactivate(int $id): RedirectResponse
    {
        $this->checkAdmin();

        if ($id === auth()->id()) {
            return back()->withErrors(['error' => 'لا يمكنك إلغاء تفعيل حسابك الخاص.']);
        }

        $member = User::whereIn('role', User::activeInternalRoles())->findOrFail($id);
        $member->update(['is_active' => false]);

        return redirect()->route('administration.team')
            ->with('success', 'تم إلغاء تفعيل حساب عضو الفريق.');
    }
}
