<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\DailyOperationalService;
use App\Support\FinancialPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    /**
     * Store a new operational expense.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_EXPENSES);

        // If legacy form provided category name instead of category_id, map it
        if (!$request->filled('category_id') && $request->filled('category')) {
            $cat = ExpenseCategory::where('name', $request->input('category'))
                ->orWhere('key', $request->input('category'))
                ->first();
            if (!$cat) {
                $cat = ExpenseCategory::firstOrCreate(
                    ['key' => 'other'],
                    ['name' => 'أخرى', 'icon' => '📦', 'color' => '#64748b', 'sort_order' => 6, 'is_active' => true]
                );
            }
            $request->merge(['category_id' => $cat->id]);
        }

        // Default visibility to shared if not provided
        if (!$request->has('visibility')) {
            $request->merge(['visibility' => 'shared']);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'visibility' => 'required|in:shared,personal',
            'frequency' => 'nullable|in:one_time,daily,weekly,monthly',
            'related_client_id' => 'nullable|exists:clients,id',
            'notes' => 'nullable|string',
        ]);

        $category = ExpenseCategory::find($validated['category_id']);

        $expense = Expense::create([
            'amount' => $validated['amount'],
            'category_id' => $validated['category_id'],
            'category' => $category ? $category->name : 'أخرى',
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'],
            'time' => $validated['time'] ?? null,
            'visibility' => $validated['visibility'],
            'frequency' => $validated['frequency'] ?? 'one_time',
            'paid_by' => auth()->id(),
            'related_client_id' => $validated['related_client_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Invalidate daily snapshot cache for the authenticated user
        app(DailyOperationalService::class)->clearSnapshotCache(auth()->user());

        if ($request->expectsJson() || $request->ajax() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل المصروف بنجاح.',
                'expense' => $expense->load(['categoryModel', 'payer']),
            ], 201);
        }

        return back()->with('success', 'تم تسجيل المصروف بنجاح.');
    }
}
