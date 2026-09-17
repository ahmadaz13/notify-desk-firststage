<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_metric_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('subscription_event_id')->nullable()->unique()->constrained('subscription_events')->nullOnDelete();
            $table->dateTime('effective_at')->index();
            $table->string('movement_type')->index();
            $table->bigInteger('arr_before_minor')->default(0);
            $table->bigInteger('arr_after_minor')->default(0);
            $table->bigInteger('arr_delta_minor')->default(0);
            $table->foreignId('plan_before_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('plan_after_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_interval_before')->nullable();
            $table->string('billing_interval_after')->nullable();
            $table->unsignedInteger('quantity_before')->nullable();
            $table->unsignedInteger('quantity_after')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['source_type', 'source_id', 'movement_type'], 'subscription_metric_source_unique');
            $table->index(['subscription_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_metric_events');
    }
};
