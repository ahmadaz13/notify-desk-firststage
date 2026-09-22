<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\OfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfferController extends Controller
{
    public function store(Request $request, int $client, OfferService $offerService)
    {
        $clientModel = Client::findOrFail($client);
        Gate::authorize('update', $clientModel);

        $data = $request->validate([
            'package' => 'required|string|max:120',
            'billing_period' => 'required|in:monthly,annual,installment',
            'price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'final_agreed_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'offer_date' => 'required|date',
            'decision_deadline' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $offerService->createOffer($client, auth()->id(), $data);

        return back()->with('success', 'تم تقديم العرض التجاري بنجاح.');
    }
}
