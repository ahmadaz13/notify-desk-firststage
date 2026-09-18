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
use Throwable;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService,
        protected \App\Services\ContractPdfService $pdfService
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
     * Securely download the archived contract HTML or PDF document.
     */
    public function download(Request $request, Contract $contract): StreamedResponse|Response|RedirectResponse
    {
        Gate::authorize('download', $contract);

        if ($request->query('format') === 'pdf') {
            return $this->downloadPdf($contract);
        }

        if (! $contract->private_file_path || ! Storage::disk('local')->exists($contract->private_file_path)) {
            abort(409, 'ملف العقد غير متاح ويحتاج إعادة توليد صريحة من صفحة العميل.');
        }

        return Storage::disk('local')->download(
            $contract->private_file_path,
            "Contract-{$contract->contract_number}.html",
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Download the immutable contract as a PDF document.
     */
    public function downloadPdf(Contract $contract): Response|RedirectResponse
    {
        Gate::authorize('download', $contract);

        try {
            return $this->pdfService->downloadResponse($contract);
        } catch (Throwable $e) {
            return redirect()->route('clients.show', $contract->client_id)
                ->with('warning', 'تعذر إنشاء ملف PDF للعقد في الوقت الحالي؛ لا يزال بإمكانك استعراض العقد وطباعته عبر المتصفح.');
        }
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

        $contract = $this->contractService->ensureDraftContract($client, $subscription, $request->user());

        try {
            $contract = $this->contractService->generateArtifact($contract);
        } catch (Throwable) {
            return redirect()->route('clients.show', $client->id)
                ->with('warning', "مسودة العقد رقم {$contract->contract_number} محفوظة، لكن ملفها يحتاج إعادة توليد لاحقاً.");
        }

        $message = $contract->status === 'draft'
            ? "مسودة العقد رقم {$contract->contract_number} جاهزة بنجاح."
            : "العقد الحالي رقم {$contract->contract_number} جاهز بنجاح.";

        return redirect()->route('clients.show', $client->id)->with('success', $message);
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
