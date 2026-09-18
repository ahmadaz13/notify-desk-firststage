<?php

namespace App\Services;

use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ContractPdfService
{
    public function generateFilename(Contract $contract): string
    {
        $snapshot = $contract->snapshot_data ?? [];

        $clientName = $snapshot['client']['business_name']
            ?? $contract->client?->business_name
            ?? 'client';

        $productName = $snapshot['product']['name_en']
            ?? $snapshot['product']['code']
            ?? $contract->subscription?->plan?->product?->name_en
            ?? 'product';

        $sanitizedClient = Str::slug($clientName, '-') ?: 'client';
        $sanitizedProduct = Str::slug($productName, '-') ?: 'product';
        $contractNumber = $contract->contract_number ?: "ND-{$contract->id}";

        return "Notify-Contract-{$sanitizedClient}-{$sanitizedProduct}-{$contractNumber}.pdf";
    }

    public function generatePdfOutput(Contract $contract): string
    {
        try {
            $snapshot = $contract->snapshot_data;
            if (! is_array($snapshot) || empty($snapshot)) {
                throw new RuntimeException("Contract {$contract->id} does not contain valid snapshot data.");
            }

            $html = view('contracts.template', [
                'contract' => $contract,
                'contractNumber' => $contract->contract_number,
                'snapshot' => $snapshot,
                'issuedDate' => $contract->issued_at?->toDateString() ?? $contract->created_at->toDateString(),
                'legalReviewStatus' => $contract->legal_review_status,
                'autoPrint' => false,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false);

            return $pdf->output();
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to generate contract PDF: '.$e->getMessage(), 0, $e);
        }
    }

    public function downloadResponse(Contract $contract): Response
    {
        $pdfOutput = $this->generatePdfOutput($contract);
        $filename = $this->generateFilename($contract);

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
