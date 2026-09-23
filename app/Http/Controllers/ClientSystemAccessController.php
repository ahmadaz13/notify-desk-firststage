<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientSystemAccessController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_SYSTEM_ACCESS);
        $data = $request->validate([
            'system_ids' => ['required', 'array', 'min:1'],
            'system_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $systems = Product::sellable()->whereIn('id', $data['system_ids'])->get();
        foreach ($systems as $system) {
            $client->systems()->syncWithoutDetaching([$system->id => [
                'access_type' => 'free',
                'granted_at' => now('Asia/Amman')->toDateString(),
                'revoked_at' => null,
                'note' => $data['note'] ?? null,
                'granted_by' => $request->user()->id,
            ]]);
        }

        return back()->with('success', __('notify.system_access.granted'));
    }

    public function destroy(Client $client, Product $system): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_SYSTEM_ACCESS);
        $client->systems()->updateExistingPivot($system->id, ['revoked_at' => now('Asia/Amman')->toDateString()]);

        return back()->with('success', __('notify.system_access.revoked'));
    }
}
