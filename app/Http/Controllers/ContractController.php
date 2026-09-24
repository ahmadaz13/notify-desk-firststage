<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Subscription;
use App\Services\ContractPdfService;
use App\Services\ContractService;
use App\Support\ContractDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Contract actions (§8.3): Preview and Download PDF for every active user; Issue (plus void/supersede)
 * for Owner/Admin only. No print page, no HTML download, no e-signature.
 */
class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService,
        protected ContractPdfService $pdfService
    ) {}

    public function preview(Contract $contract): View
    {
        Gate::authorize('view', $contract);

        return view('contracts.document', [
            'document' => ContractDocument::for($contract),
            'mode' => 'screen',
            'backUrl' => route('clients.show', $contract->client_id),
        ]);
    }

    public function downloadPdf(Contract $contract): Response|RedirectResponse
    {
        Gate::authorize('download', $contract);

        try {
            return $this->pdfService->downloadResponse($contract);
        } catch (Throwable $e) {
            Log::error('Contract PDF generation failed.', ['contract_id' => $contract->id, 'exception' => $e]);

            return redirect()->route('clients.show', $contract->client_id)
                ->with('warning', __('notify.contracts.pdf_failed'));
        }
    }

    /** Manual recovery: make sure a paid subscription has its Draft contract. */
    public function store(Request $request, Client $client, Subscription $subscription): RedirectResponse
    {
        Gate::authorize('create', Contract::class);

        if ((int) $subscription->client_id !== (int) $client->id) {
            abort(404, 'Subscription does not belong to client');
        }

        $contract = $this->contractService->ensureDraftContract($client, $subscription, $request->user());

        return redirect()->route('clients.show', $client->id)->with('success', $contract->isDraft()
            ? __('notify.contracts.draft_ready')
            : __('notify.contracts.current_ready', ['number' => $contract->displayNumber()]));
    }

    public function issue(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('issue', $contract);

        $issued = $this->contractService->issueContract($contract, $request->user());

        return redirect()->back()->with('success', __('notify.contracts.issued', ['number' => $issued->contract_number]));
    }

    public function void(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('void', $contract);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->contractService->voidContract($contract, $validated['reason'], $request->user());

        return redirect()->back()->with('success', __('notify.contracts.voided_done', ['number' => $contract->displayNumber()]));
    }

    public function supersede(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('supersede', $contract);

        $this->contractService->supersedeContract($contract, $request->user());

        return redirect()->route('clients.show', $contract->client_id)
            ->with('success', __('notify.contracts.superseded_done'));
    }
}
