<?php

namespace App\Http\Controllers;

use App\Services\OfferService;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function store(Request $request, int $client, OfferService $offerService)
    {
        $data = $request->validate([
            'package' => 'required|string|max:120',
            'billing_period' => 'required|in:monthly,annual,installment',
            'price' => 'required|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0',
            'final_agreed_price' => 'nullable|numeric|min:0',
            'offer_date' => 'required|date',
            'decision_deadline' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $offerService->createOffer($client, auth()->id(), $data);

        return back()->with('success', 'تم تقديم العرض التجاري بنجاح.');
    }
}
