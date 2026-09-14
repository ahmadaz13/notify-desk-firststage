<?php

namespace Tests\Feature\Phase6;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\DailyNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubPhase61InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_category_seeder_populates_default_categories(): void
    {
        $this->seed(ExpenseCategorySeeder::class);

        $expectedKeys = ['fuel', 'hospitality', 'transport', 'communications', 'office', 'other'];
        foreach ($expectedKeys as $key) {
            $this->assertDatabaseHas('expense_categories', [
                'key' => $key,
                'is_active' => true,
            ]);
        }

        $d2aKeys = [
            'transportation',
            'office_rent',
            'internet_communications',
            'hosting',
            'software_subscriptions',
            'marketing',
            'salaries_wages',
            'professional_services',
            'maintenance',
            'office_supplies',
            'legal_accounting',
        ];
        foreach ($d2aKeys as $key) {
            $this->assertDatabaseHas('expense_categories', [
                'key' => $key,
                'is_active' => true,
            ]);
        }

        $fuel = ExpenseCategory::where('key', 'fuel')->first();
        $this->assertNotNull($fuel);
        $this->assertEquals('وقود', $fuel->name);
        $this->assertEquals('⛽', $fuel->icon);

        $activeCategories = ExpenseCategory::active()->get();
        $this->assertGreaterThanOrEqual(17, $activeCategories->count());
    }

    public function test_expense_model_with_operational_fields_and_relationships(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $user = User::factory()->create(['name' => 'Ahmad']);
        $category = ExpenseCategory::where('key', 'fuel')->first();

        $expense = Expense::create([
            'amount' => 25.50,
            'category' => 'وقود',
            'category_id' => $category->id,
            'description' => 'تعبئة بنزين جولة عبدون',
            'notes' => 'محطة المناصير',
            'date' => '2026-09-12',
            'time' => '10:30',
            'frequency' => 'daily',
            'visibility' => 'shared',
            'paid_by' => $user->id,
        ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => 'تعبئة بنزين جولة عبدون',
            'visibility' => 'shared',
            'frequency' => 'daily',
        ]);

        $this->assertInstanceOf(User::class, $expense->payer);
        $this->assertEquals('Ahmad', $expense->payer->name);
        $this->assertInstanceOf(ExpenseCategory::class, $expense->categoryModel);
        $this->assertEquals('fuel', $expense->categoryModel->key);
    }

    public function test_expense_visibility_scope(): void
    {
        $ahmad = User::factory()->create(['name' => 'Ahmad']);
        $khalid = User::factory()->create(['name' => 'Khalid']);

        // Ahmad shared expense
        Expense::create([
            'amount' => 15.00,
            'category' => 'ضيافة',
            'description' => 'قهوة واجتماع مع عميل',
            'date' => '2026-09-12',
            'visibility' => 'shared',
            'paid_by' => $ahmad->id,
        ]);

        // Ahmad personal expense
        Expense::create([
            'amount' => 5.00,
            'category' => 'تنقلات',
            'description' => 'مصروف شخصي',
            'date' => '2026-09-12',
            'visibility' => 'personal',
            'paid_by' => $ahmad->id,
        ]);

        // Khalid personal expense
        Expense::create([
            'amount' => 8.00,
            'category' => 'اتصالات',
            'description' => 'شحن خط شخصي',
            'date' => '2026-09-12',
            'visibility' => 'personal',
            'paid_by' => $khalid->id,
        ]);

        // Ahmad should see his 2 expenses + Khalid's shared (none here) = 2
        $ahmadVisible = Expense::visibleTo($ahmad)->get();
        $this->assertCount(2, $ahmadVisible);

        // Khalid should see Ahmad's shared expense + his own personal = 2
        $khalidVisible = Expense::visibleTo($khalid)->get();
        $this->assertCount(2, $khalidVisible);

        // Ahmad's personal expense is NOT visible to Khalid
        $this->assertFalse($khalidVisible->contains('description', 'مصروف شخصي'));
        // Khalid's personal expense is NOT visible to Ahmad
        $this->assertFalse($ahmadVisible->contains('description', 'شحن خط شخصي'));

        // Partner user should see ZERO expenses under any circumstances
        $partner = User::factory()->create(['role' => 'partner']);
        $partnerVisible = Expense::visibleTo($partner)->get();
        $this->assertCount(0, $partnerVisible);
    }

    public function test_daily_note_model_and_unique_constraint(): void
    {
        $user = User::factory()->create();

        $note = DailyNote::create([
            'user_id' => $user->id,
            'date' => '2026-09-12',
            'content' => 'خطة اليوم: زيارة 3 مطاعم في شارع مكة وتأكيد عقدين.',
        ]);

        $this->assertDatabaseHas('daily_notes', [
            'id' => $note->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals('2026-09-12', $note->fresh()->date->toDateString());

        $this->assertEquals($user->id, $note->user->id);

        // Attempting to duplicate the exact same user_id and date should trigger unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);
        DailyNote::create([
            'user_id' => $user->id,
            'date' => '2026-09-12',
            'content' => 'محاولة تكرار الملاحظة',
        ]);
    }

    public function test_appointment_model_and_multi_user_relationship(): void
    {
        $ahmad = User::factory()->create(['name' => 'Ahmad']);
        $khalid = User::factory()->create(['name' => 'Khalid']);

        $client = Client::create([
            'business_name' => 'حلويات الشام',
            'phone' => '0799988776',
            'city_area' => 'الصويفية',
            'business_category' => 'حلويات',
            'lead_source' => 'Direct',
            'status' => 'prospect',
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-12',
            'appointment_time' => '11:00',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
            'location' => 'الصويفية',
            'notes' => 'اجتماع مشترك لأحمد وخالد',
        ]);

        // Attach both Ahmad and Khalid
        $appointment->users()->attach([$ahmad->id, $khalid->id]);

        $this->assertDatabaseHas('appointment_user', [
            'appointment_id' => $appointment->id,
            'user_id' => $ahmad->id,
        ]);
        $this->assertDatabaseHas('appointment_user', [
            'appointment_id' => $appointment->id,
            'user_id' => $khalid->id,
        ]);

        $fresh = Appointment::with('users', 'client')->find($appointment->id);
        $this->assertCount(2, $fresh->users);
        $this->assertEquals('حلويات الشام', $fresh->client->business_name);
        $this->assertTrue($fresh->users->pluck('name')->contains('Ahmad'));
        $this->assertTrue($fresh->users->pluck('name')->contains('Khalid'));

        // Check inverse relationship from User
        $this->assertCount(1, $ahmad->fresh()->appointments);
        $this->assertCount(1, $khalid->fresh()->appointments);
    }
}
