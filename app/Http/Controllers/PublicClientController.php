<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\User;
use App\Notifications\ConflictDetected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class PublicClientController extends Controller
{
    /**
     * Normalize a phone number to standard digits with country code.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '00962')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0962')) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '962' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '962')) {
            $digits = '962' . $digits;
        }

        return $digits;
    }

    public function create(string $uuid): View
    {
        $partner = Partner::where('public_uuid', $uuid)->firstOrFail();

        return view('public-client-form', compact('partner', 'uuid'));
    }

    public function store(Request $request, string $uuid): RedirectResponse
    {
        $partner = Partner::where('public_uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'phone' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'area' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
        ]);

        $normalizedPhone = $this->normalizePhone($validated['phone']);
        $localDigits = str_starts_with($normalizedPhone, '962') ? substr($normalizedPhone, 3) : $normalizedPhone;
        $withZero = '0' . $localDigits;

        // Search for existing client
        $foundClient = Client::where('phone', $normalizedPhone)
            ->orWhere('phone', $validated['phone'])
            ->orWhere('phone', $withZero)
            ->orWhere('phone', $localDigits)
            ->first();

        if (!$foundClient) {
            // Case A: Client NOT found
            $client = Client::create([
                'partner_id' => $partner->id,
                'phone' => $normalizedPhone,
                'business_name' => $validated['name'],
                'city_area' => $validated['area'] ?: 'عمان',
                'business_category' => 'عام',
                'lead_source' => $validated['source'] ?: ('مندوب: ' . $partner->company_name),
                'status' => 'prospect',
            ]);

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => null,
                'type' => 'client_created_by_delegate',
                'description' => "تم إنشاء العميل من خلال رابط مندوب الشريك [{$partner->company_name}]",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('public.client.create', $uuid)
                ->with('success', 'تم إضافة العميل بنجاح');
        }

        // Case B: Client FOUND -> Create ConflictResolutionRequest
        $conflict = ConflictResolutionRequest::create([
            'partner_id' => $partner->id,
            'client_id' => $foundClient->id,
            'submitted_phone' => $normalizedPhone,
            'submitted_name' => $validated['name'],
            'submitted_area' => $validated['area'] ?? null,
            'submitted_source' => $validated['source'] ?? null,
            'status' => 'pending',
        ]);

        // Send Notification to Admins
        $admins = User::where('role', 'admin')->get();
        $message = "طلب ربط عميل [{$normalizedPhone}] من الشريك [{$partner->company_name}]. هذا الرقم مسجل مسبقاً. يرجى المراجعة.";

        foreach ($admins as $admin) {
            DB::table('notifications')->insert([
                'user_id' => $admin->id,
                'type' => 'conflict_detected',
                'title' => 'تعارض عميل جديد',
                'message' => $message,
                'action_url' => route('conflicts.index'),
                'source_type' => 'conflict_resolution_request',
                'source_id' => $conflict->id,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ConflictDetected($partner, $normalizedPhone, $conflict));
        }

        return redirect()->route('public.client.create', $uuid)
            ->with('info', 'تم استلام طلبك، سيتم مراجعته من قبل الإدارة');
    }
}
