<?php

namespace App\Http\Controllers;

use App\Models\ConflictResolutionRequest;
use App\Services\ClientPartnerAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConflictResolutionController extends Controller
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

        $conflicts = ConflictResolutionRequest::with(['partner', 'client'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('conflicts.index', compact('conflicts'));
    }

    public function show(int $id): View
    {
        $this->checkAdmin();

        $conflict = ConflictResolutionRequest::with(['partner', 'client'])->findOrFail($id);

        return view('conflicts.show', compact('conflict'));
    }

    public function resolve(Request $request, int $id, ClientPartnerAttributionService $attributions): RedirectResponse
    {
        $this->checkAdmin();

        $conflict = ConflictResolutionRequest::with(['partner', 'client'])->findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:transfer,update,reject',
        ]);

        $action = $validated['action'];
        $client = $conflict->client;

        if ($action === 'transfer') {
            if ($client) {
                $client->update([
                    'business_name' => $conflict->submitted_name ?: $client->business_name,
                    'city_area' => $conflict->submitted_area ?: $client->city_area,
                    'lead_source' => $conflict->submitted_source ?: $client->lead_source,
                ]);
                $attributions->assign($client, $conflict->partner, null, null, auth()->id());

                DB::table('activity_logs')->insert([
                    'client_id' => $client->id,
                    'user_id' => auth()->id(),
                    'type' => 'client_transferred',
                    'description' => "تم نقل العميل إلى الشريك [{$conflict->partner->company_name}] إثر حل التعارض",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $conflict->status = 'resolved_transferred';
        } elseif ($action === 'update') {
            if ($client) {
                // Update client details without altering partner_id
                $client->update([
                    'business_name' => $conflict->submitted_name ?: $client->business_name,
                    'city_area' => $conflict->submitted_area ?: $client->city_area,
                    'lead_source' => $conflict->submitted_source ?: $client->lead_source,
                ]);

                DB::table('activity_logs')->insert([
                    'client_id' => $client->id,
                    'user_id' => auth()->id(),
                    'type' => 'client_updated',
                    'description' => "تم تحديث بيانات العميل من طلب الشريك [{$conflict->partner->company_name}] دون نقل التبعية",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $conflict->status = 'resolved_updated';
        } elseif ($action === 'reject') {
            if ($client) {
                DB::table('activity_logs')->insert([
                    'client_id' => $client->id,
                    'user_id' => auth()->id(),
                    'type' => 'conflict_rejected',
                    'description' => "تم رفض طلب إلحاق العميل بالشريك [{$conflict->partner->company_name}]",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $conflict->status = 'rejected';
        }

        $conflict->resolved_by = auth()->id();
        $conflict->resolved_at = now();
        $conflict->save();

        return redirect()->route('conflicts.index')->with('success', 'تمت معالجة طلب التعارض بنجاح.');
    }
}
