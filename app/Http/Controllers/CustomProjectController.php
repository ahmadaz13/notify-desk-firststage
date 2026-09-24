<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomProject;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Custom Projects (§20a, D-14): a separate top-level area for one-time custom work, outside MRR/ARR.
 * A Client link is optional; invoicing requires one and always goes through the existing one-time
 * invoice path (BillingController::storeOneTimeInvoice → InvoiceService). Linking or unlinking a
 * Client only writes a Client Activity event. Staff read-only; Owner-level users manage.
 */
class CustomProjectController extends Controller
{
    private function authorizeView(): void
    {
        Gate::authorize(Permissions::VIEW_CUSTOM_PROJECTS);
    }

    private function authorizeManage(): void
    {
        Gate::authorize(Permissions::MANAGE_CUSTOM_PROJECTS);
    }

    // ─── Index (all projects) ─────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorizeView();

        $status = $request->query('status', 'all');
        $status = in_array($status, ['all', ...CustomProject::STATUSES], true) ? $status : 'all';
        $search = trim((string) $request->query('q', ''));

        $projects = CustomProject::query()
            ->with('client:id,business_name')
            ->withCount('invoices')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)
                    ->orWhereHas('client', fn ($client) => $client->where('business_name', 'like', $term)));
            })
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('custom-projects.index', compact('projects', 'status', 'search'));
    }

    // ─── Show ─────────────────────────────────────────────────────────

    public function show(CustomProject $customProject): View
    {
        $this->authorizeView();
        $customProject->load(['client:id,business_name', 'creator:id,name', 'invoices']);

        return view('custom-projects.show', ['project' => $customProject]);
    }

    // ─── Create ───────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $this->authorizeManage();

        $client = $request->filled('client_id') ? Client::find($request->integer('client_id'), ['id', 'business_name']) : null;

        return view('custom-projects.create', [
            'clients' => $this->clientOptions(),
            'client' => $client,
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate($this->rules() + [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $project = DB::transaction(function () use ($validated) {
            $project = CustomProject::create([
                'client_id' => $validated['client_id'] ?? null,
                'created_by' => auth()->id(),
                'name' => $validated['name'],
                'agreed_value_minor' => $this->agreedValueMinor($validated['agreed_value_jod'] ?? null),
                'start_date' => $validated['start_date'] ?? null,
                'target_completion_date' => $validated['target_completion_date'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($project->client_id) {
                $this->logClientActivity((int) $project->client_id, 'custom_project_linked', $project);
            }

            return $project;
        });

        return redirect()->route('custom-projects.show', $project)
            ->with('success', __('custom_projects.created'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────

    public function edit(CustomProject $customProject): View
    {
        $this->authorizeManage();
        $customProject->loadCount('invoices');

        return view('custom-projects.edit', [
            'project' => $customProject,
            'clients' => $this->clientOptions(),
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────

    public function update(Request $request, CustomProject $customProject): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate($this->rules() + [
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
        ]);

        $previousClientId = $customProject->client_id ? (int) $customProject->client_id : null;
        $clientId = array_key_exists('client_id', $validated)
            ? ($validated['client_id'] !== null ? (int) $validated['client_id'] : null)
            : $previousClientId;

        // Invoices belong to the client they were issued to; the link is fixed once invoiced.
        if ($clientId !== $previousClientId && $customProject->invoices()->exists()) {
            throw ValidationException::withMessages(['client_id' => __('custom_projects.client_locked')]);
        }

        DB::transaction(function () use ($customProject, $validated, $request, $clientId, $previousClientId) {
            $customProject->update([
                'client_id' => $clientId,
                'name' => $validated['name'],
                'agreed_value_minor' => $request->has('agreed_value_jod')
                    ? $this->agreedValueMinor($validated['agreed_value_jod'] ?? null)
                    : $customProject->agreed_value_minor,
                'start_date' => $validated['start_date'] ?? null,
                'target_completion_date' => $validated['target_completion_date'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($clientId !== $previousClientId) {
                if ($previousClientId) {
                    $this->logClientActivity($previousClientId, 'custom_project_unlinked', $customProject);
                }
                if ($clientId) {
                    $this->logClientActivity($clientId, 'custom_project_linked', $customProject);
                }
            }
        });

        return redirect()->route('custom-projects.show', $customProject)
            ->with('success', __('custom_projects.updated'));
    }

    // ─── Archive (soft-delete via archived_at) ────────────────────────

    public function archive(CustomProject $customProject): RedirectResponse
    {
        $this->authorizeManage();

        $customProject->update([
            'status' => CustomProject::STATUS_CANCELLED,
            'archived_at' => now(),
        ]);

        return redirect()->route('custom-projects.index')
            ->with('success', __('custom_projects.archived'));
    }

    // ─── Client Projects (contextual link from the client workspace) ──

    public function clientIndex(Client $client): View
    {
        $this->authorizeView();

        $projects = CustomProject::where('client_id', $client->id)
            ->withCount('invoices')
            ->orderByDesc('created_at')
            ->get();

        return view('custom-projects.client-index', compact('client', 'projects'));
    }

    /** @return array<string, array<int, mixed>|string> */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'agreed_value_jod' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(CustomProject::STATUSES)],
            'notes' => 'nullable|string|max:5000',
        ];
    }

    private function agreedValueMinor(?string $jod): int
    {
        return filled($jod) ? Money::fromJod($jod)->minorUnits() : 0;
    }

    /** Client options for the optional link: id + name only (no heavy client rows). */
    private function clientOptions()
    {
        return Client::query()->orderBy('business_name')->get(['id', 'business_name']);
    }

    /** Human-readable Client Activity event (§20a). No financial side effect. */
    private function logClientActivity(int $clientId, string $type, CustomProject $project): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $project->name,
            'metadata' => json_encode(['custom_project_id' => $project->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
