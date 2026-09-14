<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Data-driven Services Catalog
        if (!Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name_ar');
                $table->string('name_en');
                $table->text('description')->nullable();
                $table->decimal('default_price', 12, 3)->default(0.000);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 2. Subscription Services Pivot (with immutable price/name snapshots)
        if (!Schema::hasTable('subscription_service')) {
            Schema::create('subscription_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->string('service_key');
                $table->string('service_name_ar');
                $table->string('service_name_en');
                $table->decimal('price_contribution', 12, 3)->default(0.000);
                $table->timestamps();
                $table->unique(['subscription_id', 'service_id']);
            });
        }

        // 3. Subscriptions financial snapshot & workflow fields
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'setup_fee')) {
                $table->decimal('setup_fee', 12, 3)->default(0.000)->after('total_price');
            }
            if (!Schema::hasColumn('subscriptions', 'base_subtotal')) {
                $table->decimal('base_subtotal', 12, 3)->nullable()->after('setup_fee');
            }
            if (!Schema::hasColumn('subscriptions', 'annual_discount_percentage')) {
                $table->decimal('annual_discount_percentage', 5, 2)->default(0.00)->after('base_subtotal');
            }
            if (!Schema::hasColumn('subscriptions', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 3)->default(0.000)->after('annual_discount_percentage');
            }
            if (!Schema::hasColumn('subscriptions', 'tax_percentage')) {
                $table->decimal('tax_percentage', 5, 2)->default(0.00)->after('discount_amount');
            }
            if (!Schema::hasColumn('subscriptions', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 3)->default(0.000)->after('tax_percentage');
            }
            if (!Schema::hasColumn('subscriptions', 'grand_total')) {
                $table->decimal('grand_total', 12, 3)->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('subscriptions', 'monthly_due_day')) {
                $table->unsignedTinyInteger('monthly_due_day')->default(1)->after('grand_total');
            }
            if (!Schema::hasColumn('subscriptions', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('monthly_due_day');
            }
            if (!Schema::hasColumn('subscriptions', 'previous_subscription_id')) {
                $table->foreignId('previous_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete()->after('version');
            }
            if (!Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('subscriptions', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('subscriptions', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancellation_reason');
            }
        });

        // 4. Payment Schedules financial breakdown & reminders
        Schema::table('payment_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_schedules', 'sequence')) {
                $table->unsignedInteger('sequence')->default(1)->after('subscription_id');
            }
            if (!Schema::hasColumn('payment_schedules', 'subtotal')) {
                $table->decimal('subtotal', 12, 3)->nullable()->after('sequence');
            }
            if (!Schema::hasColumn('payment_schedules', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 3)->default(0.000)->after('subtotal');
            }
            if (!Schema::hasColumn('payment_schedules', 'setup_fee_amount')) {
                $table->decimal('setup_fee_amount', 12, 3)->default(0.000)->after('discount_amount');
            }
            if (!Schema::hasColumn('payment_schedules', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 3)->default(0.000)->after('setup_fee_amount');
            }
            if (!Schema::hasColumn('payment_schedules', 'total_amount')) {
                $table->decimal('total_amount', 12, 3)->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('payment_schedules', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 3)->default(0.000)->after('total_amount');
            }
            if (!Schema::hasColumn('payment_schedules', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('paid_amount');
            }
            if (!Schema::hasColumn('payment_schedules', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->index()->after('status');
            }
        });
    }

    public function down(): void
    {
        // Safe additive migration rollback
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'sequence',
                'subtotal',
                'discount_amount',
                'setup_fee_amount',
                'tax_amount',
                'total_amount',
                'paid_amount',
                'payment_method',
                'reminder_sent_at',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['previous_subscription_id']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'setup_fee',
                'base_subtotal',
                'annual_discount_percentage',
                'discount_amount',
                'tax_percentage',
                'tax_amount',
                'grand_total',
                'monthly_due_day',
                'version',
                'previous_subscription_id',
                'cancelled_at',
                'cancellation_reason',
                'cancelled_by',
            ]);
        });

        Schema::dropIfExists('subscription_service');
        Schema::dropIfExists('services');
    }
};
