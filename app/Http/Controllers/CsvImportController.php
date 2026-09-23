<?php

namespace App\Http\Controllers;

use App\Services\CsvImportService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CsvImportController extends Controller
{
    public function index()
    {
        Gate::authorize(Permissions::IMPORT_CLIENTS);

        return view('clients.import');
    }

    public function preview(Request $request, CsvImportService $csvService)
    {
        Gate::authorize(Permissions::IMPORT_CLIENTS);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'type' => 'required|in:prospect,subscriber',
        ]);

        $file = $request->file('csv_file');
        $type = $request->input('type');

        $preview = $csvService->previewCsv($file->getRealPath(), $type);

        // Store valid rows in session temporarily for confirm step
        session([
            'csv_import_valid' => $preview['valid'],
            'csv_import_type' => $type,
        ]);

        return view('clients.import', compact('preview', 'type'));
    }

    public function confirm(Request $request, CsvImportService $csvService)
    {
        Gate::authorize(Permissions::IMPORT_CLIENTS);

        $validRows = session('csv_import_valid');
        $type = session('csv_import_type', 'prospect');

        if (empty($validRows) || !is_array($validRows)) {
            return redirect()->route('clients.import')->withErrors(['error' => 'لا توجد بيانات صالحة للاستيراد أو انتهت صلاحية المعاينة. يرجى رفع الملف مجدداً.']);
        }

        $imported = $csvService->importValidRows($validRows, $type, auth()->id());

        // Clear import session
        session()->forget(['csv_import_valid', 'csv_import_type']);

        return redirect()->route('clients.index')->with('success', "تم استيراد {$imported} عميل بنجاح.");
    }

    public function downloadTemplate(string $type)
    {
        Gate::authorize(Permissions::IMPORT_CLIENTS);

        abort_unless(in_array($type, ['prospects', 'subscribers']), 404);

        $path = public_path("csv-templates/{$type}_template.csv");
        abort_unless(file_exists($path), 404, 'نموذج CSV غير موجود');

        return response()->download($path, "notifydesk_{$type}_template.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
