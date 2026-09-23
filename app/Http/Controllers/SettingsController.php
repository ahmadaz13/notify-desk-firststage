<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private function checkAdmin(): void
    {
        Gate::authorize(Permissions::MANAGE_COMPANY_SETTINGS);
    }

    public function index(Request $request): View
    {
        $this->checkAdmin();

        $defaults = self::defaults();
        $settings = collect($defaults)->mapWithKeys(fn ($value, $key) => [$key => Setting::get($key, $value)])->all();

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->checkAdmin();
        $data = $request->validate([
            'company_name_ar' => ['nullable', 'string', 'max:255'], 'company_name_en' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'], 'company_email' => ['nullable', 'email', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'], 'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'], 'authorized_signatory' => ['nullable', 'string', 'max:255'],
            'default_contract_terms' => ['nullable', 'string', 'max:10000'], 'contract_prefix' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:12'],
            'invoice_prefix' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:12'], 'timezone' => ['required', 'in:Asia/Amman'],
            'appointment_duration' => ['required', 'integer', 'min:5', 'max:480'], 'free_installation_duration' => ['required', 'integer', 'min:5', 'max:480'],
            'post_install_followup_days' => ['required', 'integer', 'min:0', 'max:365'], 'workday_start' => ['required', 'date_format:H:i'],
            'workday_end' => ['required', 'date_format:H:i', 'after:workday_start'], 'currency' => ['required', 'in:JOD'],
            'default_billing_cycle' => ['required', 'in:monthly,annual'],
            'company_logo' => ['nullable', 'image', 'max:2048'],
        ]);
        if ($request->hasFile('company_logo')) {
            $previous = Setting::get('company_logo');
            $data['company_logo'] = $request->file('company_logo')->store('company', 'public');
            if ($previous) Storage::disk('public')->delete($previous);
        }
        foreach (['auto_contract_on_paid_subscription', 'allow_monthly', 'allow_annual_installments'] as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }
        foreach ($data as $key => $value) Setting::set($key, $value ?? '');

        return back()->with('success', __('notify.settings.saved'));
    }

    public static function defaults(): array
    {
        return [
            'company_name_ar' => 'نوتيفاي', 'company_name_en' => 'Notify', 'company_logo' => '', 'company_phone' => '', 'company_email' => '', 'company_address' => '',
            'registration_number' => '', 'tax_number' => '', 'authorized_signatory' => '', 'default_contract_terms' => '', 'contract_prefix' => 'ND', 'invoice_prefix' => 'INV',
            'timezone' => 'Asia/Amman', 'appointment_duration' => '60', 'free_installation_duration' => '60', 'post_install_followup_days' => '3',
            'workday_start' => '09:00', 'workday_end' => '17:00', 'currency' => 'JOD', 'default_billing_cycle' => 'monthly',
            'auto_contract_on_paid_subscription' => '1', 'allow_monthly' => '1', 'allow_annual_installments' => '1',
        ];
    }
}
