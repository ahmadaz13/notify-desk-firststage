<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        $uuid = (string) Str::uuid();

        $partner = Partner::create([
            'company_name' => $validated['company_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'profit_share_percentage' => $validated['profit_share_percentage'] ?? null,
            'public_uuid' => $uuid,
        ]);

        User::create([
            'name' => $partner->company_name,
            'email' => $partner->email,
            'password' => Hash::make(Str::random(10)),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $delegateLink = url('/p/' . $partner->public_uuid . '/client/create');

        return redirect()->route('partners.index')
            ->with('success', 'تم إنشاء الشريك بنجاح. رابط المندوب: ' . $delegateLink)
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
            'profit_share_percentage' => 'nullable|numeric|min:0|max:100',
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

    public function destroy(int $id): RedirectResponse
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        User::where('partner_id', $partner->id)->delete();
        $partner->delete();

        return redirect()->route('partners.index')->with('success', 'تم حذف الشريك وحسابه بنجاح.');
    }
}
