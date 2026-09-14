<?php

namespace Tests\Feature;

use App\Models\DailyNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_note_auto_save_creates_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);

        $response = $this->actingAs($admin)
            ->putJson(route('daily-notes.save'), [
                'content' => 'اجتماع رائع مع عميل الصويفية اليوم، مهتم باشتراك سنوي.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['success', 'saved_at', 'note']);

        $this->assertDatabaseHas('daily_notes', [
            'user_id' => $admin->id,
            'content' => 'اجتماع رائع مع عميل الصويفية اليوم، مهتم باشتراك سنوي.',
        ]);

        $note = DailyNote::where('user_id', $admin->id)->first();
        $this->assertNotNull($note);
        $this->assertEquals(Carbon::today()->toDateString(), $note->date->toDateString());
    }

    public function test_daily_note_auto_save_updates_existing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);
        $today = Carbon::today()->toDateString();

        DailyNote::create([
            'user_id' => $admin->id,
            'date' => $today,
            'content' => 'الملاحظة الأولى قبل التحديث',
        ]);

        $response = $this->actingAs($admin)
            ->putJson(route('daily-notes.save'), [
                'content' => 'الملاحظة المحدثة بعد انتهاء الجولة الميدانية',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('daily_notes', 1);
        $this->assertDatabaseHas('daily_notes', [
            'user_id' => $admin->id,
            'content' => 'الملاحظة المحدثة بعد انتهاء الجولة الميدانية',
        ]);
    }

    public function test_daily_note_is_per_user(): void
    {
        $ahmad = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);
        $khalid = User::factory()->create(['role' => 'admin', 'name' => 'Khalid']);
        $today = Carbon::today()->toDateString();

        $this->actingAs($ahmad)
            ->putJson(route('daily-notes.save'), [
                'content' => 'ملاحظات أحمد الميدانية',
            ])
            ->assertStatus(200);

        $this->actingAs($khalid)
            ->putJson(route('daily-notes.save'), [
                'content' => 'ملاحظات خالد الميدانية',
            ])
            ->assertStatus(200);

        $this->assertDatabaseCount('daily_notes', 2);

        $this->assertDatabaseHas('daily_notes', [
            'user_id' => $ahmad->id,
            'content' => 'ملاحظات أحمد الميدانية',
        ]);

        $this->assertDatabaseHas('daily_notes', [
            'user_id' => $khalid->id,
            'content' => 'ملاحظات خالد الميدانية',
        ]);

        // When Ahmad visits dashboard, he sees Ahmad's notes
        $responseAhmad = $this->actingAs($ahmad)->get(route('dashboard'));
        $responseAhmad->assertOk();
        $responseAhmad->assertSee('ملاحظات أحمد الميدانية');
        $responseAhmad->assertDontSee('ملاحظات خالد الميدانية');
    }
}
