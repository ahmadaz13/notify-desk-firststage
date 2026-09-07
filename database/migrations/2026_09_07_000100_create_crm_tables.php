<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('phone')->index();
            $table->string('contact_person')->nullable();
            $table->string('city_area');
            $table->string('business_category');
            $table->string('lead_source');
            $table->foreignId('primary_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('prospect')->index();
            $table->string('location_text')->nullable();
            $table->string('maps_url')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('result');
            $table->text('note')->nullable();
            $table->string('next_action')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('appointment_date')->index();
            $table->time('appointment_time');
            $table->string('appointment_type');
            $table->string('status')->default('scheduled')->index();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['appointment_id', 'user_id']);
        });

        Schema::create('meeting_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('attendance_status');
            $table->dateTime('meeting_date_time');
            $table->string('meeting_type');
            $table->boolean('demo_performed')->default(false);
            $table->string('interest_level');
            $table->text('customer_needs')->nullable();
            $table->text('main_objections')->nullable();
            $table->boolean('price_discussed')->default(false);
            $table->string('package_discussed')->nullable();
            $table->text('customer_response')->nullable();
            $table->string('next_action');
            $table->date('next_follow_up_date')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('reason');
            $table->text('result')->nullable();
            $table->string('next_action');
            $table->date('next_follow_up_date');
            $table->dateTime('follow_up_date_time');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package');
            $table->string('billing_period');
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('final_agreed_price', 12, 2);
            $table->date('offer_date');
            $table->date('decision_deadline')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('billing_type');
            $table->decimal('total_price', 12, 2);
            $table->date('start_date');
            $table->date('renewal_date')->nullable();
            $table->unsignedInteger('installments_count')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_due', 12, 2);
            $table->date('due_date')->index();
            $table->string('status')->default('upcoming')->index();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->string('category');
            $table->date('date');
            $table->foreignId('paid_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('related_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('related_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('commercial_offers');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('meeting_outcomes');
        Schema::dropIfExists('appointment_user');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('contact_attempts');
        Schema::dropIfExists('clients');
    }
};
