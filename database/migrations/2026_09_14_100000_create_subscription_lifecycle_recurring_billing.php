<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('cancelled_by');
            }
            if (! Schema::hasColumn('subscriptions', 'cancellation_requested_at')) {
                $table->dateTime('cancellation_requested_at')->nullable()->after('cancel_at_period_end');
            }
            if (! Schema::hasColumn('subscriptions', 'ended_at')) {
                $table->dateTime('ended_at')->nullable()->after('cancellation_requested_at');
            }
            if (! Schema::hasColumn('subscriptions', 'pending_plan_id')) {
                $table->foreignId('pending_plan_id')->nullable()->after('ended_at')->constrained('plans')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'pending_plan_price_id')) {
                $table->foreignId('pending_plan_price_id')->nullable()->after('pending_plan_id')->constrained('plan_prices')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'pending_quantity')) {
                $table->unsignedInteger('pending_quantity')->nullable()->after('pending_plan_price_id');
            }
            if (! Schema::hasColumn('subscriptions', 'pending_change_effective_at')) {
                $table->dateTime('pending_change_effective_at')->nullable()->after('pending_quantity');
            }
        });

        Schema::create('subscription_billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('period_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('billing_interval');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('plan_price_id')->nullable()->constrained('plan_prices')->nullOnDelete();
            $table->string('plan_name_snapshot')->nullable();
            $table->bigInteger('price_snapshot_minor')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('JOD');
            $table->foreignId('invoice_id')->nullable()->unique()->constrained('invoices')->nullOnDelete();
            $table->string('status')->default('scheduled')->index();
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_start', 'period_end'], 'subscription_period_unique');
            $table->index(['status', 'period_start']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->dateTime('effective_at')->index();
            $table->foreignId('from_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->bigInteger('from_price_minor')->nullable();
            $table->bigInteger('to_price_minor')->nullable();
            $table->string('billing_interval')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['subscription_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscription_billing_periods');

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach ([
                'pending_change_effective_at',
                'pending_quantity',
                'pending_plan_price_id',
                'pending_plan_id',
                'ended_at',
                'cancellation_requested_at',
                'cancel_at_period_end',
            ] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    if (in_array($column, ['pending_plan_price_id', 'pending_plan_id'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
