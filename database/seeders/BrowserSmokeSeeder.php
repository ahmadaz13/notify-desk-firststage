<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use App\Services\ClientCredentialService;
use App\Services\ClientOperationalWorkflowService;
use App\Services\PaymentReceiptService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Browser smoke tests only (§32, FROZEN D-23): a Staff login next to the demo Owner from
 * DatabaseSeeder, plus one client per lifecycle segment created through the real services
 * (P10 review). Runs against the throw-away e2e database, never production.
 */
class BrowserSmokeSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Sara', 'password' => Hash::make('password'), 'role' => User::ROLE_STAFF, 'is_active' => true],
        );
        $owner = User::query()->where('email', 'ahmad@example.com')->firstOrFail();
        // Demo rows from DatabaseSeeder carry only the legacy status; derive stage the same way M-11 does.
        Client::query()->whereNull('stage')->get()->each(fn (Client $client) => $client->update([
            'stage' => ClientLifecycle::normalizeStage(null, $client->status),
        ]));
        $billing = app(SubscriptionBillingService::class);
        $smartLink = Product::query()->where('code', Product::CODE_SMART_LINK)->first();
        $eMenu = Product::query()->where('code', Product::CODE_E_MENU)->first();
        $autoSms = Product::query()->where('code', Product::CODE_AUTO_SMS)->first();

        // Subscriber: active monthly subscription with money due, a stored credential and a Staff receipt awaiting confirmation.
        $subscriber = $this->client('مطعم الياسمين', 'مطعم', 'اللويبدة', '0791112233');
        $billing->startAgreedSubscription($subscriber, collect([$smartLink, $autoSms])->filter(), [
            'billing_interval' => 'monthly',
            'agreed_value_minor' => 45_000,
            'start_date' => now('Asia/Amman')->subDays(20)->toDateString(),
            'payment_terms' => 'full',
            'installments_count' => null,
            'installment_due_day' => null,
            'notes' => null,
        ], $owner->id);
        if ($smartLink) {
            app(ClientCredentialService::class)->store($subscriber->fresh(), $smartLink, [
                'login_url' => 'https://example.test/login',
                'username' => 'yasmin@example.test',
                'secret' => 'Browser-Smoke-Secret-9',
            ], $owner);
        }
        app(PaymentReceiptService::class)->submit($subscriber->fresh(), [
            'amount' => '20.000',
            'payment_method' => 'cash',
            'received_at' => now('Asia/Amman')->subHour()->format('Y-m-d H:i'),
        ], $staff, 'rcpt_browser_smoke_1');

        // Former subscriber: a monthly subscription that was cancelled and has ended.
        $former = $this->client('صالون ريم', 'صالون', 'الصويفية', '0794445566');
        [$ended] = $billing->startAgreedSubscription($former, collect([$eMenu])->filter(), [
            'billing_interval' => 'monthly',
            'agreed_value_minor' => 25_000,
            'start_date' => now('Asia/Amman')->subMonths(3)->toDateString(),
            'payment_terms' => 'full',
            'installments_count' => null,
            'installment_due_day' => null,
            'notes' => null,
        ], $owner->id);
        $billing->scheduleCancellation($ended, 'Budget', $owner->id);
        $billing->generateRenewals(Carbon::now('Asia/Amman')->addMonths(2), false, $owner->id);

        // Closed prospect.
        $closed = $this->client('مكتبة الأفق', 'مكتبة', 'ماركا', '0797778899');
        app(ClientOperationalWorkflowService::class)->closeClient($closed, $owner, 'not_interested', null);

        // Prospect with free access to one System.
        $prospect = Client::query()->where('stage', ClientLifecycle::PROSPECT)->orderBy('id')->first();
        if ($prospect && $eMenu) {
            $prospect->systems()->syncWithoutDetaching([$eMenu->id => [
                'access_type' => 'free',
                'granted_at' => now('Asia/Amman')->toDateString(),
                'revoked_at' => null,
                'granted_by' => $staff->id,
            ]]);
        }
    }

    private function client(string $name, string $category, string $area, string $phone): Client
    {
        return Client::query()->create([
            'business_name' => $name,
            'business_category' => $category,
            'city_area' => $area,
            'phone' => $phone,
            'business_phone' => $phone,
            'primary_phone_type' => 'business',
            'lead_source' => 'Google Maps',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ]);
    }
}
