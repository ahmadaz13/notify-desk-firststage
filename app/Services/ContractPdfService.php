<?php

namespace App\Services;

use App\Models\Contract;
use App\Support\ContractDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use RuntimeException;
use Throwable;

/**
 * Arabic contract PDF (§8.5, FROZEN D-11). dompdf reversed Arabic word order and broke mixed
 * Arabic/English lines in the P8 spike, so contracts render with mPDF: OpenType shaping, RTL bidi and
 * the bundled XB Riyaz Naskh font. A4, 19 mm margins; Draft pages carry a watermark.
 */
class ContractPdfService
{
    public const FONT = 'xbriyaz';

    public function generateFilename(Contract $contract): string
    {
        $document = ContractDocument::for($contract);
        $client = Str::slug((string) ($document['client']['business_name'] ?? ''), '-') ?: 'client';
        $systems = collect($document['services'])->pluck('code')->filter()->map(fn ($code) => Str::slug((string) $code, '-'))->join('-') ?: 'services';
        $suffix = $document['number'] ?: 'draft';

        return "Notify-Contract-{$client}-{$systems}-{$suffix}.pdf";
    }

    public function generatePdfOutput(Contract $contract): string
    {
        try {
            $document = ContractDocument::for($contract);
            $html = view('contracts.document', ['document' => $document, 'mode' => 'pdf'])->render();

            $mpdf = $this->makeMpdf();
            if ($document['is_draft'] || $document['is_voided']) {
                $mpdf->SetWatermarkText($document['is_voided'] ? 'VOID' : 'DRAFT', 0.07);
                $mpdf->showWatermarkText = true;
            }
            $mpdf->SetHTMLFooter(view('contracts.partials.pdf-footer', ['document' => $document])->render());
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to generate contract PDF: '.$e->getMessage(), 0, $e);
        }
    }

    public function downloadResponse(Contract $contract): Response
    {
        return response($this->generatePdfOutput($contract), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->generateFilename($contract).'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /** Number of pages in a generated PDF (used to hold the two-page contract target). */
    public static function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page(?!s)\b#', $pdf);
    }

    private function makeMpdf(): Mpdf
    {
        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 19,
            'margin_right' => 19,
            'margin_top' => 18,
            'margin_bottom' => 20,
            'margin_footer' => 8,
            'default_font' => self::FONT,
            'default_font_size' => 10,
            'directionality' => 'rtl',
            'useSubstitutions' => true,
            'backupSubsFont' => ['dejavusans'],
            'tempDir' => $tempDir,
            'allow_output_buffering' => true,
        ]);
    }
}
