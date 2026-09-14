<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickExpenseFABTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_fab_expense_creation_returns_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);
        $cat = ExpenseCategory::where('key', 'fuel')->first();

        $payload = [
            'amount' => 22.50,
            'category_id' => $cat->id,
            'description' => 'بنزين جولة المبيعات الصباحية',
            'date' => Carbon::today()->toDateString(),
            'time' => '09:30',
            'visibility' => 'shared',
            'frequency' => 'one_time',
        ];

        $response = $this->actingAs($admin)
            ->postJson(route('expenses.store'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم تسجيل المصروف بنجاح.',
            ])
            ->assertJsonPath('expense.amount', '22.50')
            ->assertJsonPath('expense.description', 'بنزين جولة المبيعات الصباحية');

        $this->assertDatabaseHas('expenses', [
            'amount' => 22.50,
            'category_id' => $cat->id,
            'description' => 'بنزين جولة المبيعات الصباحية',
            'paid_by' => $admin->id,
            'visibility' => 'shared',
        ]);
    }

    public function test_fab_expense_validation_rejects_empty_amount(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cat = ExpenseCategory::first();

        $payload = [
            'amount' => '',
            'category_id' => $cat->id,
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'shared',
        ];

        $response = $this->actingAs($admin)
            ->postJson(route('expenses.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_partner_cannot_access_admin_expenses(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك تجاري خارجي',
            'email' => 'partner@test.com',
            'phone' => '0798888888',
        ]);
        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);
        $cat = ExpenseCategory::first();

        $payload = [
            'amount' => 15.00,
            'category_id' => $cat->id,
            'description' => 'محاولة شريك تسجيل مصروف',
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'shared',
        ];

        $response = $this->actingAs($partnerUser)
            ->postJson(route('expenses.store'), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseCount('expenses', 0);
    }
}
