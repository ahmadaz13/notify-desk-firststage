<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\OperationalSettings;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Settings (§16): four sections, and every visible setting drives behaviour.
 *
 * Company & Contracts   → contract snapshot at Issue (ContractService::COMPANY_FIELDS), contract and
 *                         invoice numbering, default terms at draft creation
 * Operations            → OperationalSettings (Today timing, default times, post-install follow-up,
 *                         reminder hours)
 * Subscriptions & Contracts → start-subscription sheet + server validation, Draft contract creation
 * Optional Features     → Features (route gate + navigation)
 *
 * `timezone` (Asia/Amman) and `currency` (JOD) are fixed in V1: shown as information, never editable.
 */
class SettingsController extends Controller
{
    public const BOOLEAN_KEYS = ['auto_contract_on_paid_subscription', 'allow_monthly', 'allow_annual_installments', 'feature_capital_financing'];

    /** Fixed V1 values (§16); stored for compatibility, never taken from the request. */
    public const FIXED = ['timezone' => 'Asia/Amman', 'currency' => 'JOD'];

    private function authorizeSettings(): void
    {
        Gate::authorize(Permissions::MANAGE_COMPANY_SETTINGS);
    }

    public function index(): View
    {
        $this->authorizeSettings();

        $settings = self::current();
        $logoUrl = filled($settings['company_logo']) && Storage::disk('public')->exists($settings['company_logo'])
            ? Storage::disk('public')->url($settings['company_logo'])
            : null;

        return view('settings.index', compact('settings', 'logoUrl'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $data = $request->validate([
            // Company & Contracts
            'company_name_ar' => ['required', 'string', 'max:255'],
            'company_name_en' => ['required', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'company_national_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'authorized_signatory' => ['nullable', 'string', 'max:255'],
            'default_contract_terms' => ['nullable', 'string', 'max:10000'],
            'contract_prefix' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:12'],
            'invoice_prefix' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:12'],
            'company_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'remove_company_logo' => ['nullable', 'boolean'],
            // Operations
            'appointment_duration' => ['required', 'integer', 'min:'.OperationalSettings::MIN_DURATION, 'max:'.OperationalSettings::MAX_DURATION],
            'free_installation_duration' => ['required', 'integer', 'min:'.OperationalSettings::MIN_DURATION, 'max:'.OperationalSettings::MAX_DURATION],
            'post_install_followup_days' => ['required', 'integer', 'min:'.OperationalSettings::MIN_FOLLOW_UP_DAYS, 'max:'.OperationalSettings::MAX_FOLLOW_UP_DAYS],
            'workday_start' => ['required', 'date_format:H:i'],
            'workday_end' => ['required', 'date_format:H:i', 'after:workday_start'],
            // Subscriptions & Contracts
            'default_billing_cycle' => ['required', 'in:monthly,annual'],
            // Legacy fixed values: accepted only as their one V1 value.
            'timezone' => ['sometimes', 'in:Asia/Amman'],
            'currency' => ['sometimes', 'in:JOD'],
        ], [
            'workday_end.after' => __('notify.settings.validation.workday_end_after_start'),
        ]);

        // A monthly default cycle must be sellable (§7: cycle options follow allow_monthly).
        if (! $request->boolean('allow_monthly') && $data['default_billing_cycle'] === 'monthly') {
            return back()->withInput()->withErrors(['default_billing_cycle' => __('notify.settings.validation.monthly_default_requires_monthly')]);
        }

        $previousLogo = (string) Setting::get('company_logo', '');
        unset($data['company_logo'], $data['remove_company_logo'], $data['timezone'], $data['currency']);

        if ($request->hasFile('company_logo')) {
            $data['company_logo'] = $request->file('company_logo')->store('company', 'public');
        } elseif ($request->boolean('remove_company_logo')) {
            $data['company_logo'] = '';
        }

        foreach (self::BOOLEAN_KEYS as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }

        foreach ($data + self::FIXED as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        // Only a file this page stored is removed; issued contracts keep their frozen snapshot.
        if (array_key_exists('company_logo', $data) && $previousLogo !== '' && $previousLogo !== $data['company_logo'] && str_starts_with($previousLogo, 'company/')) {
            Storage::disk('public')->delete($previousLogo);
        }

        return redirect()->route('settings.index')->with('success', __('notify.settings.saved'));
    }

    /** @return array<string, string> Current values with defaults for missing rows. */
    public static function current(): array
    {
        $stored = Setting::query()->whereIn('key', array_keys(self::defaults()))->pluck('value', 'key')->all();

        return array_map('strval', array_merge(self::defaults(), array_intersect_key($stored, self::defaults())));
    }

    public static function defaults(): array
    {
        return [
            'company_name_ar' => 'نوتيفاي', 'company_name_en' => 'Notify', 'company_logo' => '', 'company_phone' => '', 'company_email' => '', 'company_address' => '',
            'registration_number' => '', 'company_national_number' => '', 'tax_number' => '', 'authorized_signatory' => '', 'default_contract_terms' => '', 'contract_prefix' => 'ND', 'invoice_prefix' => 'INV',
            'timezone' => 'Asia/Amman',
            'appointment_duration' => (string) OperationalSettings::DEFAULTS['appointment_duration'],
            'free_installation_duration' => (string) OperationalSettings::DEFAULTS['free_installation_duration'],
            'post_install_followup_days' => (string) OperationalSettings::DEFAULTS['post_install_followup_days'],
            'workday_start' => OperationalSettings::DEFAULTS['workday_start'], 'workday_end' => OperationalSettings::DEFAULTS['workday_end'],
            'currency' => 'JOD', 'default_billing_cycle' => 'monthly',
            'auto_contract_on_paid_subscription' => '1', 'allow_monthly' => '1', 'allow_annual_installments' => '1',
            'feature_capital_financing' => '0',
        ];
    }
}
