<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\PartnerCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'contact_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:partners,email',
            'phone' => 'nullable|string|max:50',
            'profit_share_percentage' => 'nullable|numeric|min:0|max:100',
            'default_commission_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $uuid = (string) Str::uuid();
        $commissionBps = $this->percentageToBps($validated['default_commission_percentage'] ?? $validated['profit_share_percentage'] ?? null);

        $partner = Partner::create([
            'company_name' => $validated['company_name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'profit_share_percentage' => $commissionBps === null ? null : number_format($commissionBps / 100, 2, '.', ''),
            'default_commission_bps' => $commissionBps,
            'public_uuid' => $uuid,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => auth()->id(),
            'type' => 'partner_referrer_created',
            'description' => "تم إنشاء مرجع شريك {$partner->company_name} بدون حساب دخول بواسطة " . auth()->user()->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delegateLink = url('/p/' . $partner->public_uuid . '/client/create');

        return redirect()->route('partners.index')
            ->with('success', 'تم إنشاء مرجع الشريك بدون حساب دخول.')
            ->with('new_delegate_link', $delegateLink);
    }

    public function edit(int $id): View
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        return view('partners.edit', compact('partner'));
    }

    public function show(int $id, PartnerCommissionService $commissions): View
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);
        $partnerSummary = $commissions->summary($partner);

        return view('partners.show', compact('partner', 'partnerSummary'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:partners,email,' . $partner->id,
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,suspended',
            'profit_share_percentage' => 'nullable|numeric|min:0|max:100',
            'default_commission_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $commissionBps = $this->percentageToBps($validated['default_commission_percentage'] ?? $validated['profit_share_percentage'] ?? null);
        $partner->update([
            'company_name' => $validated['company_name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? $partner->status,
            'profit_share_percentage' => $commissionBps === null ? null : number_format($commissionBps / 100, 2, '.', ''),
            'default_commission_bps' => $commissionBps,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('partners.index')->with('success', 'تم تحديث بيانات الشريك بنجاح.');
    }

    public function resetPassword(int $id): RedirectResponse
    {
        $this->checkAdmin();

        abort(410, 'Partner login credentials are retired. Partner records are referral/history only.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkAdmin();

        $partner = Partner::findOrFail($id);

        $partner->update(['status' => 'suspended']);

        return redirect()->route('partners.index')->with('success', 'تم أرشفة مرجع الشريك مع الحفاظ على السجل.');
    }

    private function percentageToBps(mixed $percentage): ?int
    {
        if ($percentage === null || $percentage === '') {
            return null;
        }

        return (int) round(((float) $percentage) * 100);
    }
}
