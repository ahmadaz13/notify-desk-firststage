<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Subscription;
use App\Services\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService
    ) {}

    /**
     * Preview the rendered contract with print toolbar and disclaimers.
     */
    public function preview(Contract $contract): View
    {
        Gate::authorize('view', $contract);

        return view('contracts.template', [
            'contract' => $contract,
            'contractNumber' => $contract->contract_number,
            'snapshot' => $contract->snapshot_data,
            'issuedDate' => $contract->issued_at?->toDateString() ?? $contract->created_at->toDateString(),
            'legalReviewStatus' => $contract->legal_review_status,
            'autoPrint' => false,
        ]);
    }

    /**
     * Printable contract view with auto-trigger for browser print dialog.
     */
    public function print(Contract $contract): View
    {
        Gate::authorize('view', $contract);

        return view('contracts.template', [
            'contract' => $contract,
            'contractNumber' => $contract->contract_number,
            'snapshot' => $contract->snapshot_data,
            'issuedDate' => $contract->issued_at?->toDateString() ?? $contract->created_at->toDateString(),
            'legalReviewStatus' => $contract->legal_review_status,
            'autoPrint' => true,
        ]);
    }

    /**
     * Securely download the archived contract HTML document.
     */
    public function download(Contract $contract): StreamedResponse|Response
    {
        Gate::authorize('download', $contract);

        if (!Storage::disk('local')->exists($contract->private_file_path)) {
            $htmlContent = view('contracts.template', [
                'contract' => $contract,
                'contractNumber' => $contract->contract_number,
                'snapshot' => $contract->snapshot_data,
                'issuedDate' => $contract->issued_at?->toDateString() ?? $contract->created_at->toDateString(),
                'legalReviewStatus' => $contract->legal_review_status,
                'autoPrint' => false,
            ])->render();
            Storage::disk('local')->put($contract->private_file_path, $htmlContent);
        }

        return Storage::disk('local')->download(
            $contract->private_file_path,
            "Contract-{$contract->contract_number}.html",
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Generate a new contract draft for a subscription.
     */
    public function store(Request $request, Client $client, Subscription $subscription): RedirectResponse
    {
        Gate::authorize('create', Contract::class);

        if ((int) $subscription->client_id !== (int) $client->id) {
            abort(404, 'Subscription does not belong to client');
        }

        $contract = $this->contractService->createContract($client, $subscription, $request->user());

        return redirect()->route('clients.show', $client->id)
            ->with('success', "تم إنشاء مسودة العقد رقم {$contract->contract_number} بنجاح.");
    }

    /**
     * Formally issue an existing draft contract.
     */
    public function issue(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('issue', $contract);

        $this->contractService->issueContract($contract, $request->user());

        return redirect()->back()->with('success', "تم اعتماد وإصدار العقد رقم {$contract->contract_number} رسمياً.");
    }

    /**
     * Void a contract with an operational reason.
     */
    public function void(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('void', $contract);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->contractService->voidContract($contract, $validated['reason'], $request->user());

        return redirect()->back()->with('success', "تم إلغاء (Void) العقد رقم {$contract->contract_number} بنجاح.");
    }

    /**
     * Supersede an existing contract with a new draft.
     */
    public function supersede(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('supersede', $contract);

        $newContract = $this->contractService->supersedeContract($contract, $request->user());

        return redirect()->route('clients.show', $contract->client_id)
            ->with('success', "تم استبدال العقد السابق وتوليد العقد الجديد رقم {$newContract->contract_number}.");
    }
}
