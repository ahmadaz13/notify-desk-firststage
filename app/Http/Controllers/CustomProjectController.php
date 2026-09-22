<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomProject;
use App\Support\FinancialPermissions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomProjectController extends Controller
{
    /**
     * Gate check – manage_commercial_catalog covers project management.
     * Admins always pass; staff are blocked.
     */
    private function authorizeManage(): void
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);
    }

    // ─── Index (all projects) ─────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorizeManage();

        $status  = $request->query('status', 'all');
        $query   = CustomProject::with('client')
            ->orderByDesc('created_at');

        if ($status !== 'all' && in_array($status, CustomProject::STATUSES, true)) {
            $query->where('status', $status);
        }

        $projects = $query->paginate(25)->withQueryString();

        return view('custom-projects.index', compact('projects', 'status'));
    }

    // ─── Show ─────────────────────────────────────────────────────────

    public function show(CustomProject $customProject): View
    {
        $this->authorizeManage();
        $customProject->load(['client', 'creator', 'invoices']);

        return view('custom-projects.show', ['project' => $customProject]);
    }

    // ─── Create ───────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $this->authorizeManage();

        // Allow pre-selecting a client from query string
        $client = null;
        if ($request->filled('client_id')) {
            $client = Client::find($request->integer('client_id'));
        }

        $clients = Client::orderBy('business_name')->get(['id', 'business_name']);

        return view('custom-projects.create', compact('clients', 'client'));
    }

    // ─── Store ────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'client_id'              => 'required|exists:clients,id',
            'name'                   => 'required|string|max:255',
            'agreed_value_jod'       => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'start_date'             => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            'status'                 => ['required', Rule::in(CustomProject::STATUSES)],
            'notes'                  => 'nullable|string|max:5000',
        ]);

        $agreedValueMinor = 0;
        if (!empty($validated['agreed_value_jod'])) {
            $agreedValueMinor = Money::fromJod($validated['agreed_value_jod'])->minorUnits();
        }

        $project = CustomProject::create([
            'client_id'              => $validated['client_id'],
            'created_by'             => auth()->id(),
            'name'                   => $validated['name'],
            'agreed_value_minor'     => $agreedValueMinor,
            'start_date'             => $validated['start_date'] ?? null,
            'target_completion_date' => $validated['target_completion_date'] ?? null,
            'status'                 => $validated['status'],
            'notes'                  => $validated['notes'] ?? null,
        ]);

        return redirect()->route('custom-projects.show', $project)
            ->with('success', __('custom_projects.created'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────

    public function edit(CustomProject $customProject): View
    {
        $this->authorizeManage();

        $clients = Client::orderBy('business_name')->get(['id', 'business_name']);

        return view('custom-projects.edit', ['project' => $customProject, 'clients' => $clients]);
    }

    // ─── Update ───────────────────────────────────────────────────────

    public function update(Request $request, CustomProject $customProject): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'agreed_value_jod'       => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'start_date'             => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            'status'                 => ['required', Rule::in(CustomProject::STATUSES)],
            'notes'                  => 'nullable|string|max:5000',
        ]);

        $agreedValueMinor = $customProject->agreed_value_minor;
        if (isset($validated['agreed_value_jod'])) {
            $agreedValueMinor = empty($validated['agreed_value_jod'])
                ? 0
                : Money::fromJod($validated['agreed_value_jod'])->minorUnits();
        }

        $customProject->update([
            'name'                   => $validated['name'],
            'agreed_value_minor'     => $agreedValueMinor,
            'start_date'             => $validated['start_date'] ?? null,
            'target_completion_date' => $validated['target_completion_date'] ?? null,
            'status'                 => $validated['status'],
            'notes'                  => $validated['notes'] ?? null,
        ]);

        return redirect()->route('custom-projects.show', $customProject)
            ->with('success', __('custom_projects.updated'));
    }

    // ─── Archive (soft-delete via archived_at) ────────────────────────

    public function archive(CustomProject $customProject): RedirectResponse
    {
        $this->authorizeManage();

        $customProject->update([
            'status'      => CustomProject::STATUS_CANCELLED,
            'archived_at' => now(),
        ]);

        return redirect()->route('custom-projects.index')
            ->with('success', __('custom_projects.archived'));
    }

    // ─── Client Projects (called from client workspace) ───────────────

    public function clientIndex(Client $client): View
    {
        $this->authorizeManage();

        $projects = CustomProject::where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->get();

        return view('custom-projects.client-index', compact('client', 'projects'));
    }
}
