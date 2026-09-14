<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('revenue_recognition_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_line_id')->unique()->constrained('invoice_lines')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('revenue_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->string('policy')->index();
            $table->string('currency', 3)->default('JOD');
            $table->bigInteger('original_recognizable_minor');
            $table->date('recognition_start_date')->nullable();
            $table->date('recognition_end_date')->nullable();
            $table->unsignedInteger('period_count')->nullable();
            $table->string('status')->index();
            $table->boolean('requires_manual_confirmation')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['revenue_account_id', 'status']);
        });

        Schema::create('revenue_recognition_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_recognition_schedule_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('period_index');
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('scheduled_minor');
            $table->bigInteger('recognized_minor')->default(0);
            $table->string('status')->default('pending')->index();
            $table->dateTime('recognized_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['revenue_recognition_schedule_id', 'period_index'], 'rr_period_schedule_index_unique');
            $table->index(['period_end', 'status']);
        });

        Schema::create('revenue_recognition_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_recognition_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('credit_note_line_id')->constrained('credit_note_lines')->restrictOnDelete();
            $table->string('adjustment_type')->index();
            $table->bigInteger('amount_minor');
            $table->date('effective_date');
            $table->foreignId('revenue_recognition_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['credit_note_line_id', 'adjustment_type'], 'rr_adjust_credit_type_index');
            $table->index(['revenue_recognition_schedule_id', 'effective_date'], 'rr_adjust_schedule_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_recognition_adjustments');
        Schema::dropIfExists('revenue_recognition_periods');
        Schema::dropIfExists('revenue_recognition_schedules');
    }
};
