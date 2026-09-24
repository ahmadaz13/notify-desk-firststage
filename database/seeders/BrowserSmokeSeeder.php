<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\CustomProject;
use App\Models\Product;
use App\Models\User;
use App\Services\ClientCredentialService;
use App\Services\ClientOperationalWorkflowService;
use App\Services\FreeInstallationService;
use App\Services\PaymentReceiptService;
use App\Services\SubscriptionBillingService;
use App\Support\AppointmentTypes;
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

        $this->todayBoard($owner, $staff);
        $this->customProjects($owner, $subscriber);
    }

    /** P13 review: one Custom Project linked to a client (invoiceable) and one without a client. */
    private function customProjects(User $owner, Client $client): void
    {
        CustomProject::query()->firstOrCreate(['name' => 'تصميم قائمة رقمية مخصصة'], [
            'client_id' => $client->id, 'created_by' => $owner->id, 'status' => CustomProject::STATUS_ACTIVE,
            'agreed_value_minor' => 350_000, 'start_date' => now('Asia/Amman')->subDays(10)->toDateString(),
            'target_completion_date' => now('Asia/Amman')->addDays(20)->toDateString(),
        ]);
        CustomProject::query()->firstOrCreate(['name' => 'موقع تعريفي لشركة ناشئة'], [
            'client_id' => null, 'created_by' => $owner->id, 'status' => CustomProject::STATUS_PLANNED, 'agreed_value_minor' => 0,
        ]);
    }

    /**
     * P11 Today review: an overdue-heavy mixed day on new clients (ids after the P10 fixtures),
     * created through the real workflow services. Times are relative to "now" in Asia/Amman.
     */
    private function todayBoard(User $owner, User $staff): void
    {
        $now = Carbon::now('Asia/Amman');
        $sameDay = fn (Carbon $at) => $at->isSameDay($now) ? $at : $now->copy()->endOfDay()->subMinute();
        $workflow = app(ClientOperationalWorkflowService::class);

        $cafe = $this->client('مقهى الزاوية', 'مقهى', 'جبل عمّان', '0795550101');
        $cafe->update(['primary_owner_id' => $staff->id]);
        $late = Appointment::create([
            'client_id' => $cafe->id,
            'appointment_date' => $now->copy()->subDay()->toDateString(),
            'appointment_time' => '11:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'location' => 'جبل عمّان · الدوار الأول',
        ]);
        $late->users()->sync([$staff->id]);

        $store = $this->client('متجر الريحان', 'متجر', 'خلدا', '0795550202');
        $workflow->scheduleFollowUp($store, $staff, [
            'follow_up_date_time' => $now->copy()->subDay()->setTime(16, 0)->toDateTimeString(),
            'reason' => 'متابعة عرض الأسعار',
        ]);

        // A second overdue follow-up: the P12 interaction review completes one, the Today review needs one left.
        $press = $this->client('مطبعة الأمل', 'مطبعة', 'ماركا', '0795550606');
        $workflow->scheduleFollowUp($press, $staff, [
            'follow_up_date_time' => $now->copy()->subDay()->setTime(15, 0)->toDateTimeString(),
            'reason' => 'متابعة طلب الطباعة',
        ]);

        $sweets = $this->client('حلويات الشام', 'حلويات', 'الصويفية', '0795550303');
        $next = $sameDay($now->copy()->addMinutes(40));
        $soon = Appointment::create([
            'client_id' => $sweets->id,
            'appointment_date' => $next->toDateString(),
            'appointment_time' => $next->format('H:i:s'),
            'appointment_type' => AppointmentTypes::ONLINE_DEMO,
            'status' => 'confirmed',
            'notes' => 'عرض نظام القائمة الإلكترونية',
        ]);
        $soon->users()->sync([$owner->id]);

        $bakery = $this->client('مخبز الفجر', 'مخبز', 'طبربور', '0795550404');
        $later = $sameDay($now->copy()->addHours(4));
        app(FreeInstallationService::class)->scheduleInstallation($bakery, $owner, [
            'appointment_date' => $later->toDateString(),
            'appointment_time' => $later->format('H:i'),
            'attendees' => [$staff->id],
            'notes' => 'تركيب الجهاز في الفرع الرئيسي',
        ]);

        $library = $this->client('مكتبة النور', 'مكتبة', 'الهاشمي', '0795550505');
        $workflow->createReviewItem($library, $staff, ClientReviewItem::TYPE_WRONG_INVALID, 'الرقم لا يرد منذ أسبوع');

        // Completed today for the Owner: one recorded call.
        $workflow->recordContactOutcome($bakery, $owner, ['method' => 'phone', 'result' => 'no_answer_busy']);
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
