<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PartnerController extends Controller
{
    protected function checkAdmin(): void
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة');
        }
    }

    public function index(): View
    {
        $this->checkAdmin();

        $partners = Partner::withCount('clients')->orderByDesc('created_at')->paginate(15);

        return view('partners.index', compact('partners'));
    }

    public function create(): View
    {
        $this->checkAdmin();

        return view('partners.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:partners,email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'profit_share_percentage' => 'nullable|numeric|min:0|max:100',
            'deduction_percentage' => 'nullable|numeric|min:0|max:100',
            'password' => 'nullable|string|min:6',
        ]);

        $uuid = (string) Str::uuid();
        $rawPassword = !empty($validated['password']) ? $validated['password'] : Str::random(10);

        $partner = Partner::create([
            'company_name' => $validated['company_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'profit_share_percentage' => $validated['profit_share_percentage'] ?? null,
            'deduction_percentage' => $validated['deduction_percentage'] ?? 20.00,
            'public_uuid' => $uuid,
            'status' => 'active',
        ]);

        User::create([
            'name' => $partner->company_name,
            'email' => $partner->email,
            'password' => Hash::make($rawPassword),
            'role' => 'partner',
            'partner_id' => $partner->id,
            'first_login_at' => null,
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => auth()->id(),
            'type' => 'partner_created',
            'description' => "تم إنشاء الشريك {$partner->company_name} وحساب الدخول بواسطة " . auth()->user()->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delegateLink = url('/p/' . $partner->public_uuid . '/client/create');
        $credentials = [
            'company_name' => $partner->company_name,
            'email' => $partner->email,
            'password' => $rawPassword,
            'login_url' => url('/login'),
            'delegate_link' => $delegateLink,
        ];

        return redirect()->route('partners.index')
            ->with('success', 'تم إنشاء الشريك وحساب الدخول بنجاح.')
            ->with('new_partner_credentials', $credentials)
            ->with('new_delegate_link', $delegateLink);
    }

    public function edit(int $id): View
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        return view('partners.edit', compact('partner'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:partners,email,' . $partner->id,
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,suspended',
            'profit_share_percentage' => 'nullable|numeric|min:0|max:100',
            'deduction_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $oldEmail = $partner->email;
        $partner->update($validated);

        if ($oldEmail !== $partner->email) {
            User::where('partner_id', $partner->id)->update([
                'email' => $partner->email,
                'name' => $partner->company_name,
            ]);
        }

        return redirect()->route('partners.index')->with('success', 'تم تحديث بيانات الشريك بنجاح.');
    }

    public function resetPassword(int $id): RedirectResponse
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);
        $user = User::where('partner_id', $partner->id)->firstOrFail();

        $newPassword = Str::random(10);
        $user->update([
            'password' => Hash::make($newPassword),
            'remember_token' => null,
            'reset_expires_at' => now()->addHours(48),
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => auth()->id(),
            'type' => 'partner_password_reset',
            'description' => "تمت إعادة تعيين كلمة مرور الشريك {$partner->company_name} بواسطة " . auth()->user()->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delegateLink = url('/p/' . $partner->public_uuid . '/client/create');
        $credentials = [
            'company_name' => $partner->company_name,
            'email' => $user->email,
            'password' => $newPassword,
            'login_url' => url('/login'),
            'delegate_link' => $delegateLink,
        ];

        return redirect()->route('partners.index')
            ->with('success', 'تمت إعادة تعيين كلمة مرور الشريك بنجاح.')
            ->with('reset_partner_credentials', $credentials);
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        User::where('partner_id', $partner->id)->delete();
        $partner->delete();

        return redirect()->route('partners.index')->with('success', 'تم حذف الشريك وحسابه بنجاح.');
    }
}
