<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->string('schedule_engine_version')->nullable()->after('subscription_id')->index();
            $table->foreignId('invoice_id')->nullable()->after('schedule_engine_version')->constrained('invoices')->nullOnDelete();
            $table->bigInteger('amount_due_minor')->nullable()->after('amount_due');
            $table->unique(
                ['subscription_id', 'schedule_engine_version', 'invoice_id', 'sequence'],
                'payment_schedules_v2_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropUnique('payment_schedules_v2_sequence_unique');
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['schedule_engine_version', 'invoice_id', 'amount_due_minor']);
        });
    }
};
