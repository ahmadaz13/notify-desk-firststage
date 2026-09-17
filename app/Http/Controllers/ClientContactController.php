<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClientContactController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $this->validateContact($request);

        DB::transaction(function () use ($client, $validated) {
            $isPrimary = (bool) ($validated['is_primary'] ?? false);
            if (!$isPrimary && !$client->contacts()->exists()) {
                $isPrimary = true;
            }

            if ($isPrimary) {
                $client->contacts()->update(['is_primary' => false]);
            }

            $contact = $client->contacts()->create(array_merge($validated, [
                'is_primary' => $isPrimary,
            ]));

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'client_contact_created',
                'description' => "تم إضافة جهة اتصال للعميل: {$contact->name}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'تم حفظ جهة الاتصال بنجاح.');
    }

    public function update(Request $request, Client $client, ClientContact $contact): RedirectResponse
    {
        Gate::authorize('update', $client);
        abort_unless((int) $contact->client_id === (int) $client->id, 404);

        $validated = $this->validateContact($request);

        DB::transaction(function () use ($client, $contact, $validated) {
            $isPrimary = (bool) ($validated['is_primary'] ?? false);
            if ($isPrimary) {
                $client->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            } elseif (! $client->contacts()->where('id', '!=', $contact->id)->where('is_primary', true)->exists()) {
                $isPrimary = true;
            }

            $contact->update(array_merge($validated, [
                'is_primary' => $isPrimary,
            ]));

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'client_contact_updated',
                'description' => "تم تحديث جهة اتصال للعميل: {$contact->name}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'تم تحديث جهة الاتصال بنجاح.');
    }

    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:120',
            'primary_phone' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:50',
            'preferred_contact_method' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
        ]);
    }
}
