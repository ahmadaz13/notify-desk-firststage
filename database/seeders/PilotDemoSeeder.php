<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PilotDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Note: Idempotent seeder safe to execute multiple times.
     */
    public function run(): void
    {
        // 1. Ensure Expense Categories exist
        if (DB::table('expense_categories')->count() === 0) {
            $this->call(ExpenseCategorySeeder::class);
        }

        // 2. Admin users (Ahmad & Khalid)
        $ahmad = User::firstOrCreate(
            ['email' => 'ahmad@example.com'],
            [
                'name' => 'Ahmad',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        if ($ahmad->role !== 'admin') {
            $ahmad->update(['role' => 'admin']);
        }

        $khalid = User::firstOrCreate(
            ['email' => 'khalid@example.com'],
            [
                'name' => 'Khalid',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        if ($khalid->role !== 'admin') {
            $khalid->update(['role' => 'admin']);
        }

        // 3. Three Partners with different profit share percentages (20%, 25%, 30%)
        $partner1 = Partner::firstOrCreate(
            ['email' => 'contact@al-ofuq.com'],
            [
                'company_name' => 'شركة الأفق الرقمي للتسويق',
                'phone' => '0791112233',
                'profit_share_percentage' => 20.00,
                'deduction_percentage' => 20.00,
                'public_uuid' => (string) Str::uuid(),
            ]
        );

        $partner2 = Partner::firstOrCreate(
            ['email' => 'info@al-qimmah.com'],
            [
                'company_name' => 'مؤسسة القمة للحلول الذكية',
                'phone' => '0792223344',
                'profit_share_percentage' => 25.00,
                'deduction_percentage' => 20.00,
                'public_uuid' => (string) Str::uuid(),
            ]
        );

        $partner3 = Partner::firstOrCreate(
            ['email' => 'partners@al-ruwad.com'],
            [
                'company_name' => 'وكالة الرواد لخدمات الأعمال',
                'phone' => '0793334455',
                'profit_share_percentage' => 30.00,
                'deduction_percentage' => 20.00,
                'public_uuid' => (string) Str::uuid(),
            ]
        );

        // 4. Partner user with known password for testing
        $partnerUser = User::firstOrCreate(
            ['email' => 'partner@demo.com'],
            [
                'name' => 'طارق - شريك الأفق',
                'password' => Hash::make('password123'),
                'role' => 'partner',
                'partner_id' => $partner1->id,
            ]
        );
        if ($partnerUser->partner_id !== $partner1->id || $partnerUser->role !== 'partner') {
            $partnerUser->update(['role' => 'partner', 'partner_id' => $partner1->id]);
        }

        // 5. 15 Clients distributed across partners and admin
        $clientsData = [
            // 5 Direct clients (Admins)
            [
                'phone' => '0790001001',
                'business_name' => 'مطعم الياسمين الدمشقي',
                'contact_person' => 'عمر الحلبي',
                'city_area' => 'عمان - الجبيهة',
                'business_category' => 'مطاعم وكافيهات',
                'lead_source' => 'Direct Visit',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => null,
                'status' => 'subscriber',
                'notes' => 'مشترك بحزمة متقدمة مع دعم فني ميداني.',
            ],
            [
                'phone' => '0790001002',
                'business_name' => 'صيدلية الرعاية الحديثة',
                'contact_person' => 'د. سارة عيسى',
                'city_area' => 'عمان - عبدون',
                'business_category' => 'صيدليات ومراكز طبية',
                'lead_source' => 'Referral',
                'primary_owner_id' => $khalid->id,
                'partner_id' => null,
                'status' => 'subscriber',
                'notes' => 'نظام الفوترة شهري.',
            ],
            [
                'phone' => '0790001003',
                'business_name' => 'مركز النخبة للتدريب',
                'contact_person' => 'م. حازم',
                'city_area' => 'عمان - شارع المدينة',
                'business_category' => 'تعليم وتدريب',
                'lead_source' => 'Google Maps',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => null,
                'status' => 'prospect',
                'notes' => 'بانتظار موافقة مجلس الإدارة على العرض.',
            ],
            [
                'phone' => '0790001004',
                'business_name' => 'مخبز وباتيسري اللوتس',
                'contact_person' => 'أبو كريم',
                'city_area' => 'عمان - تلاع العلي',
                'business_category' => 'مخابز وحلويات',
                'lead_source' => 'Direct Visit',
                'primary_owner_id' => $khalid->id,
                'partner_id' => null,
                'status' => 'prospect',
                'notes' => 'زيارة ميدانية أولى إيجابية جداً.',
            ],
            [
                'phone' => '0790001005',
                'business_name' => 'شركة البناء الهندسي',
                'contact_person' => 'سامر الزعبي',
                'city_area' => 'عمان - الصويفية',
                'business_category' => 'مقاولات وهندسة',
                'lead_source' => 'Direct',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => null,
                'status' => 'subscriber',
                'notes' => 'اشتراك سنوي كامل مدفوع مقدماً.',
            ],

            // 4 Clients for Partner 1 (الأفق الرقمي)
            [
                'phone' => '0790001006',
                'business_name' => 'معرض السعادة للإلكترونيات',
                'contact_person' => 'فادي الشريف',
                'city_area' => 'إربد - شارع الجامعة',
                'business_category' => 'أجهزة وإلكترونيات',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => $partner1->id,
                'status' => 'subscriber',
                'notes' => 'تم استقطابه عبر مندوب شركة الأفق.',
            ],
            [
                'phone' => '0790001007',
                'business_name' => 'مجمع الفيروز الطبي',
                'contact_person' => 'د. فراس النمري',
                'city_area' => 'إربد - الحي الشرقي',
                'business_category' => 'طبي',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $khalid->id,
                'partner_id' => $partner1->id,
                'status' => 'subscriber',
                'notes' => 'نظام التقسيط المخصص على 3 دفعات.',
            ],
            [
                'phone' => '0790001008',
                'business_name' => 'كافيه ومكتبة الأندلس',
                'contact_person' => 'روان منصور',
                'city_area' => 'عمان - اللويبيدة',
                'business_category' => 'كافيهات',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => $partner1->id,
                'status' => 'prospect',
                'notes' => 'مهتم بباقة إدارة الطاولات والطلبات.',
            ],
            [
                'phone' => '0790001009',
                'business_name' => 'سوبرماركت البركة',
                'contact_person' => 'تيسير قاسم',
                'city_area' => 'الزرقاء - الزرقاء الجديدة',
                'business_category' => 'تجارة تجزئة',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $khalid->id,
                'partner_id' => $partner1->id,
                'status' => 'prospect',
                'notes' => 'متردد بخصوص وقت الربط الفني.',
            ],

            // 3 Clients for Partner 2 (القمة للحلول)
            [
                'phone' => '0790001010',
                'business_name' => 'صالون رتوش للتجميل',
                'contact_person' => 'رنا المصري',
                'city_area' => 'عمان - دير غبار',
                'business_category' => 'صالونات ومراكز تجميل',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => $partner2->id,
                'status' => 'subscriber',
                'notes' => 'مشترك نشط - التجديد القادم بعد شهرين.',
            ],
            [
                'phone' => '0790001011',
                'business_name' => 'مغسلة أوتوكير الذهبية',
                'contact_person' => 'بلال العبداللات',
                'city_area' => 'عمان - البيادر',
                'business_category' => 'خدمات سيارات',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $khalid->id,
                'partner_id' => $partner2->id,
                'status' => 'prospect',
                'notes' => 'موعد تجريبي قادم هذا الأسبوع.',
            ],
            [
                'phone' => '0790001012',
                'business_name' => 'أكاديمية المستقبل الرياضية',
                'contact_person' => 'كابتن وسام',
                'city_area' => 'عمان - خلدا',
                'business_category' => 'أندية ورياضة',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => $partner2->id,
                'status' => 'prospect',
                'notes' => 'بانتظار تجهيز العرض المالي للموسم الجديد.',
            ],

            // 3 Clients for Partner 3 (الرواد لخدمات الأعمال)
            [
                'phone' => '0790001013',
                'business_name' => 'فندق أوركيد فيو',
                'contact_person' => 'فادي غانم',
                'city_area' => 'العقبة - الشاطئ الشمالي',
                'business_category' => 'فندقة وسياحة',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => $partner3->id,
                'status' => 'subscriber',
                'notes' => 'عقد سنوي عالي القيمة.',
            ],
            [
                'phone' => '0790001014',
                'business_name' => 'مطبعة النور الفنية',
                'contact_person' => 'منذر حداد',
                'city_area' => 'عمان - المقابلين',
                'business_category' => 'طباعة وتصميم',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $khalid->id,
                'partner_id' => $partner3->id,
                'status' => 'prospect',
                'notes' => 'زيارة ميدانية مجدولة.',
            ],
            [
                'phone' => '0790001015',
                'business_name' => 'مكتب المستشار القانوني',
                'contact_person' => 'أ. جمال عريقات',
                'city_area' => 'عمان - الشميساني',
                'business_category' => 'خدمات قانونية',
                'lead_source' => 'Delegate Partner',
                'primary_owner_id' => $ahmad->id,
                'partner_id' => $partner3->id,
                'status' => 'prospect',
                'notes' => 'يرغب بحزمة إدارة قضايا وإشعارات.',
            ],
        ];

        $clientModels = [];
        foreach ($clientsData as $c) {
            $client = Client::firstOrCreate(['phone' => $c['phone']], $c);
            $clientModels[$c['phone']] = $client;
        }

        // 6. Five active subscriptions with different billing types
        $subConfig = [
            // Client 1 (monthly)
            [
                'client_id' => $clientModels['0790001001']->id,
                'user_id' => $ahmad->id,
                'billing_type' => 'monthly',
                'total_price' => 600.00,
                'start_date' => Carbon::now()->subMonths(3)->toDateString(),
                'renewal_date' => Carbon::now()->addMonths(9)->toDateString(),
                'status' => 'active',
            ],
            // Client 2 (monthly)
            [
                'client_id' => $clientModels['0790001002']->id,
                'user_id' => $khalid->id,
                'billing_type' => 'monthly',
                'total_price' => 720.00,
                'start_date' => Carbon::now()->subMonths(2)->toDateString(),
                'renewal_date' => Carbon::now()->addMonths(10)->toDateString(),
                'status' => 'active',
            ],
            // Client 5 (annual)
            [
                'client_id' => $clientModels['0790001005']->id,
                'user_id' => $ahmad->id,
                'billing_type' => 'annual',
                'total_price' => 1200.00,
                'start_date' => Carbon::now()->subMonths(1)->toDateString(),
                'renewal_date' => Carbon::now()->addMonths(11)->toDateString(),
                'status' => 'active',
            ],
            // Client 6 (Partner 1 - installment)
            [
                'client_id' => $clientModels['0790001006']->id,
                'user_id' => $ahmad->id,
                'billing_type' => 'installment',
                'total_price' => 900.00,
                'start_date' => Carbon::now()->subDays(20)->toDateString(),
                'renewal_date' => Carbon::now()->addYear()->toDateString(),
                'status' => 'active',
            ],
            // Client 13 (Partner 3 - annual)
            [
                'client_id' => $clientModels['0790001013']->id,
                'user_id' => $ahmad->id,
                'billing_type' => 'annual',
                'total_price' => 1800.00,
                'start_date' => Carbon::now()->subDays(10)->toDateString(),
                'renewal_date' => Carbon::now()->addMonths(12)->toDateString(),
                'status' => 'active',
            ],
        ];

        $subscriptionIds = [];
        foreach ($subConfig as $sc) {
            $existing = DB::table('subscriptions')
                ->where('client_id', $sc['client_id'])
                ->first();

            if ($existing) {
                $subId = $existing->id;
            } else {
                $subId = DB::table('subscriptions')->insertGetId(array_merge($sc, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                // Generate schedule
                $count = $sc['billing_type'] === 'annual' ? 1 : ($sc['billing_type'] === 'installment' ? 3 : 12);
                $per = round($sc['total_price'] / $count, 2);
                for ($i = 0; $i < $count; $i++) {
                    DB::table('payment_schedules')->insert([
                        'subscription_id' => $subId,
                        'amount_due' => $i === $count - 1 ? $sc['total_price'] - ($per * ($count - 1)) : $per,
                        'due_date' => Carbon::parse($sc['start_date'])->addMonths($sc['billing_type'] === 'annual' ? 0 : $i)->toDateString(),
                        'status' => $i === 0 ? 'paid' : 'upcoming',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $subscriptionIds[] = $subId;
        }

        // 7. Twenty Payments distributed over the last 30 days
        $paymentClients = [
            $clientModels['0790001001'],
            $clientModels['0790001002'],
            $clientModels['0790001005'],
            $clientModels['0790001006'],
            $clientModels['0790001007'],
            $clientModels['0790001010'],
            $clientModels['0790001013'],
        ];

        $paymentMethods = ['cash', 'bank_transfer', 'cliq', 'card'];
        for ($i = 1; $i <= 20; $i++) {
            $cl = $paymentClients[$i % count($paymentClients)];
            $paidDate = Carbon::now()->subDays(29 - $i)->setTime(10 + ($i % 8), ($i * 7) % 60);
            $amount = 50.00 + (($i * 25) % 350);
            $method = $paymentMethods[$i % count($paymentMethods)];
            $note = "دفعة تجريبية #{$i} من {$cl->business_name}";

            $already = DB::table('payments')
                ->where('client_id', $cl->id)
                ->where('notes', $note)
                ->exists();

            if (!$already) {
                DB::table('payments')->insert([
                    'client_id' => $cl->id,
                    'subscription_id' => $subscriptionIds[$i % count($subscriptionIds)] ?? null,
                    'payment_schedule_id' => null,
                    'amount' => $amount,
                    'payment_method' => $method,
                    'paid_at' => $paidDate,
                    'recorded_by' => ($i % 2 === 0) ? $ahmad->id : $khalid->id,
                    'notes' => $note,
                    'created_at' => $paidDate,
                    'updated_at' => $paidDate,
                ]);
            }
        }

        // 8. 10 Expenses across categories over the last 14 days
        $categories = DB::table('expense_categories')->get();
        $expenseSamples = [
            ['وقود', 25.00, 'تعبئة بنزين زيارات الشمال الميدانية', 'shared', 1],
            ['ضيافة', 8.50, 'قهوة واجتماع عمل مع عميل الياسمين', 'shared', 2],
            ['تنقلات', 15.00, 'رسوم مواقف وتطبيق تنقل ميداني', 'shared', 3],
            ['اتصالات', 20.00, 'شحن رصيد باقة اتصالات المبيعات', 'shared', 5],
            ['مكتب ومستلزمات', 35.00, 'أوراق عقود وأقلام للزيارات الميدانية', 'shared', 6],
            ['وقود', 30.00, 'بنزين جولة مقابلات عمان الغربية', 'shared', 8],
            ['ضيافة', 12.00, 'ضيافة وفد شريك الأفق للتسويق', 'shared', 9],
            ['تنقلات', 18.00, 'تنقلات إضافية وفحص فروع الزبائن', 'shared', 10],
            ['أخرى', 45.00, 'صيانة دورية لحقيبة المعاينة والأجهزة', 'shared', 12],
            ['ضيافة', 6.00, 'مشروبات أثناء زيارة الميدان', 'personal', 13],
        ];

        foreach ($expenseSamples as $idx => $exp) {
            $cat = $categories->firstWhere('name', $exp[0]);
            $date = Carbon::now()->subDays($exp[4])->toDateString();
            $desc = $exp[2];

            $already = DB::table('expenses')
                ->where('description', $desc)
                ->where('date', $date)
                ->exists();

            if (!$already) {
                DB::table('expenses')->insert([
                    'amount' => $exp[1],
                    'category' => $exp[0],
                    'category_id' => $cat?->id,
                    'description' => $desc,
                    'date' => $date,
                    'time' => sprintf('%02d:%02d', 9 + ($idx % 8), ($idx * 13) % 60),
                    'paid_by' => ($idx % 2 === 0) ? $ahmad->id : $khalid->id,
                    'visibility' => $exp[3],
                    'frequency' => 'one_time',
                    'notes' => 'سجلت عبر PilotDemoSeeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 9. Five Appointments (some with both attendees Ahmad and Khalid)
        $appointmentsData = [
            // Appointment 1: Today, both Ahmad & Khalid
            [
                'client_id' => $clientModels['0790001003']->id,
                'appointment_date' => Carbon::today()->toDateString(),
                'appointment_time' => '11:00',
                'appointment_type' => 'physical_visit',
                'status' => 'scheduled',
                'location' => 'مقر مركز النخبة - شارع المدينة',
                'notes' => 'عرض تجريبي نهائي ومناقشة تفاصيل العقد.',
                'attendees' => [$ahmad->id, $khalid->id],
            ],
            // Appointment 2: Today, Ahmad only
            [
                'client_id' => $clientModels['0790001004']->id,
                'appointment_date' => Carbon::today()->toDateString(),
                'appointment_time' => '14:30',
                'appointment_type' => 'physical_visit',
                'status' => 'confirmed',
                'location' => 'تلاع العلي - قرب مخبز اللوتس',
                'notes' => 'متابعة شروط الربط والتدريب.',
                'attendees' => [$ahmad->id],
            ],
            // Appointment 3: Tomorrow, both Ahmad & Khalid
            [
                'client_id' => $clientModels['0790001008']->id,
                'appointment_date' => Carbon::tomorrow()->toDateString(),
                'appointment_time' => '10:00',
                'appointment_type' => 'physical_visit',
                'status' => 'scheduled',
                'location' => 'اللويبيدة - كافيه الأندلس',
                'notes' => 'زيارة مشتركة مع مندوب شريك الأفق.',
                'attendees' => [$ahmad->id, $khalid->id],
            ],
            // Appointment 4: Yesterday, Completed by Khalid
            [
                'client_id' => $clientModels['0790001011']->id,
                'appointment_date' => Carbon::yesterday()->toDateString(),
                'appointment_time' => '16:00',
                'appointment_type' => 'online_demo',
                'status' => 'completed',
                'location' => 'Google Meet',
                'notes' => 'تم العرض التوضيحي بنجاح والعميل متحمس.',
                'attendees' => [$khalid->id],
            ],
            // Appointment 5: Next week, Khalid
            [
                'client_id' => $clientModels['0790001014']->id,
                'appointment_date' => Carbon::today()->addDays(4)->toDateString(),
                'appointment_time' => '12:00',
                'appointment_type' => 'phone_call',
                'status' => 'scheduled',
                'location' => 'اتصال هاتفي',
                'notes' => 'تأكيد موعد الزيارة لمطبعة النور.',
                'attendees' => [$khalid->id],
            ],
        ];

        foreach ($appointmentsData as $apt) {
            $appointment = Appointment::where('client_id', $apt['client_id'])
                ->where('notes', $apt['notes'])
                ->first();

            if (!$appointment) {
                $appointment = Appointment::create([
                    'client_id' => $apt['client_id'],
                    'appointment_date' => $apt['appointment_date'],
                    'appointment_time' => $apt['appointment_time'],
                    'appointment_type' => $apt['appointment_type'],
                    'status' => $apt['status'],
                    'location' => $apt['location'],
                    'notes' => $apt['notes'],
                ]);
            }

            // Sync attendees via pivot
            if (isset($apt['attendees'])) {
                $appointment->users()->syncWithoutDetaching($apt['attendees']);
            }
        }

        // 10. Three Pending Follow-ups
        $followUpsData = [
            [
                'client_id' => $clientModels['0790001003']->id,
                'user_id' => $ahmad->id,
                'method' => 'phone_call',
                'reason' => 'متابعة قرار الشراء بعد تقديم العرض',
                'next_action' => 'معاودة الاتصال بمدير المشتريات',
                'next_follow_up_date' => Carbon::today()->addDays(2)->toDateString(),
                'follow_up_date_time' => Carbon::now()->addDays(2),
                'notes' => 'يرغبون بخصم إضافي عند الدفع السنوي.',
            ],
            [
                'client_id' => $clientModels['0790001009']->id,
                'user_id' => $khalid->id,
                'method' => 'whatsapp',
                'reason' => 'إرسال رابط تجريبي وكتالوج الميزات',
                'next_action' => 'إرسال رسالة واتساب مع رابط العرض التوضيحي',
                'next_follow_up_date' => Carbon::today()->addDays(1)->toDateString(),
                'follow_up_date_time' => Carbon::now()->addDays(1),
                'notes' => 'مهتم جداً بميزة إشعارات الواتساب.',
            ],
            [
                'client_id' => $clientModels['0790001012']->id,
                'user_id' => $ahmad->id,
                'method' => 'physical_visit',
                'reason' => 'زيارة ثانية لمقابلة الشركاء الماليين',
                'next_action' => 'حضور اجتماع مشترك في مقر الأكاديمية',
                'next_follow_up_date' => Carbon::today()->addDays(3)->toDateString(),
                'follow_up_date_time' => Carbon::now()->addDays(3),
                'notes' => 'تحديد خيارات التقسيط الممكنة.',
            ],
        ];

        foreach ($followUpsData as $fu) {
            $already = DB::table('follow_ups')
                ->where('client_id', $fu['client_id'])
                ->where('reason', $fu['reason'])
                ->exists();

            if (!$already) {
                DB::table('follow_ups')->insert(array_merge($fu, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // 11. Two Conflict Resolution Requests
        $conflictsData = [
            [
                'partner_id' => $partner1->id,
                'client_id' => $clientModels['0790001001']->id,
                'submitted_phone' => '0790001001',
                'submitted_name' => 'مطعم الياسمين فرع 2',
                'submitted_area' => 'عمان - الجبيهة',
                'submitted_source' => 'مندوب الأفق (حسام)',
                'status' => 'pending',
            ],
            [
                'partner_id' => $partner2->id,
                'client_id' => $clientModels['0790001004']->id,
                'submitted_phone' => '0790001004',
                'submitted_name' => 'مخبز اللوتس تلاع العلي',
                'submitted_area' => 'تلاع العلي',
                'submitted_source' => 'مندوب القمة (ليث)',
                'status' => 'pending',
            ],
        ];

        foreach ($conflictsData as $conf) {
            ConflictResolutionRequest::firstOrCreate(
                [
                    'partner_id' => $conf['partner_id'],
                    'client_id' => $conf['client_id'],
                    'submitted_phone' => $conf['submitted_phone'],
                ],
                $conf
            );
        }
    }
}
