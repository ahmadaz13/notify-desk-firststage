<?php

namespace App\Http\Controllers;

use App\Models\ReferenceOption;
use App\Services\ReferenceDataService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Operational Reference Data (§15.1): Business types (client_category), Lead sources, City/area
 * suggestions. Add, edit label/order, activate/deactivate. No delete — clients store the values.
 */
class ReferenceDataController extends Controller
{
    public function __construct(private readonly ReferenceDataService $referenceData) {}

    public function index(): View
    {
        Gate::authorize(Permissions::MANAGE_REFERENCE_DATA);

        return view('administration.reference-data', [
            'lists' => $this->referenceData->listsForAdministration(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_REFERENCE_DATA);

        $data = $request->validate([
            'list_key' => ['required', Rule::in(ReferenceDataService::LISTS)],
        ] + $this->labelRules());

        $option = $this->referenceData->create($data['list_key'], $data);

        return redirect()->to(route('administration.reference-data').'#list-'.$option->list_key)
            ->with('success', __('notify.reference_data.created'));
    }

    public function update(Request $request, ReferenceOption $referenceOption): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_REFERENCE_DATA);
        abort_unless(in_array($referenceOption->list_key, ReferenceDataService::LISTS, true), 404);

        $data = $request->validate($this->labelRules());
        $this->referenceData->update($referenceOption, $data);

        return redirect()->to(route('administration.reference-data').'#list-'.$referenceOption->list_key)
            ->with('success', __('notify.reference_data.updated'));
    }

    public function setActive(Request $request, ReferenceOption $referenceOption): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_REFERENCE_DATA);
        abort_unless(in_array($referenceOption->list_key, ReferenceDataService::LISTS, true), 404);

        $active = $request->boolean('active');
        $this->referenceData->setActive($referenceOption, $active);

        return redirect()->to(route('administration.reference-data').'#list-'.$referenceOption->list_key)
            ->with('success', __($active ? 'notify.reference_data.activated' : 'notify.reference_data.deactivated'));
    }

    /** @return array<string, array<int, mixed>> */
    private function labelRules(): array
    {
        return [
            'label_ar' => ['required', 'string', 'max:120'],
            'label_en' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
