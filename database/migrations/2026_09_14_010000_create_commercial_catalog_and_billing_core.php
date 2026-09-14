<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_ar');
                $table->string('name_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plan_service')) {
            Schema::create('plan_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['plan_id', 'service_id']);
            });
        }

        if (! Schema::hasTable('plan_prices')) {
            Schema::create('plan_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->string('billing_interval');
                $table->string('currency', 3)->default('JOD');
                $table->bigInteger('amount_minor');
                $table->bigInteger('setup_fee_minor')->default(0);
                $table->unsignedInteger('included_branch_quantity')->default(1);
                $table->bigInteger('additional_branch_price_minor')->nullable();
                $table->unsignedInteger('default_tax_rate_bps')->nullable();
                $table->dateTime('effective_from');
                $table->dateTime('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['plan_id', 'billing_interval', 'is_active', 'effective_from']);
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'plan_id')) {
                $table->foreignId('plan_id')->nullable()->after('client_id')->constrained('plans')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'plan_price_id')) {
                $table->foreignId('plan_price_id')->nullable()->after('plan_id')->constrained('plan_prices')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'billing_engine_version')) {
                $table->string('billing_engine_version')->nullable()->after('plan_price_id');
            }
            if (! Schema::hasColumn('subscriptions', 'billing_interval_v2')) {
                $table->string('billing_interval_v2')->nullable()->after('billing_engine_version');
            }
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 3)->nullable()->after('billing_interval_v2');
            }
            if (! Schema::hasColumn('subscriptions', 'quantity')) {
                $table->unsignedInteger('quantity')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('subscriptions', 'plan_code_snapshot')) {
                $table->string('plan_code_snapshot')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('subscriptions', 'plan_name_snapshot')) {
                $table->string('plan_name_snapshot')->nullable()->after('plan_code_snapshot');
            }
            if (! Schema::hasColumn('subscriptions', 'unit_price_minor')) {
                $table->bigInteger('unit_price_minor')->nullable()->after('plan_name_snapshot');
            }
            if (! Schema::hasColumn('subscriptions', 'setup_fee_minor_v2')) {
                $table->bigInteger('setup_fee_minor_v2')->nullable()->after('unit_price_minor');
            }
            if (! Schema::hasColumn('subscriptions', 'subtotal_minor')) {
                $table->bigInteger('subtotal_minor')->nullable()->after('setup_fee_minor_v2');
            }
            if (! Schema::hasColumn('subscriptions', 'discount_minor')) {
                $table->bigInteger('discount_minor')->nullable()->after('subtotal_minor');
            }
            if (! Schema::hasColumn('subscriptions', 'tax_rate_bps')) {
                $table->unsignedInteger('tax_rate_bps')->nullable()->after('discount_minor');
            }
            if (! Schema::hasColumn('subscriptions', 'tax_minor_v2')) {
                $table->bigInteger('tax_minor_v2')->nullable()->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('subscriptions', 'total_minor')) {
                $table->bigInteger('total_minor')->nullable()->after('tax_minor_v2');
            }
            if (! Schema::hasColumn('subscriptions', 'current_period_start')) {
                $table->date('current_period_start')->nullable()->after('total_minor');
            }
            if (! Schema::hasColumn('subscriptions', 'current_period_end')) {
                $table->date('current_period_end')->nullable()->after('current_period_start');
            }
            if (! Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable()->after('current_period_end');
            }
        });

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->nullable()->unique();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->string('currency', 3)->default('JOD');
                $table->string('status')->default('draft');
                $table->date('issue_date');
                $table->date('due_date');
                $table->bigInteger('subtotal_minor')->default(0);
                $table->bigInteger('discount_minor')->default(0);
                $table->bigInteger('tax_minor')->default(0);
                $table->bigInteger('total_minor')->default(0);
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->text('void_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['client_id', 'status', 'issue_date']);
            });
        }

        if (! Schema::hasTable('invoice_lines')) {
            Schema::create('invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->string('line_type');
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->foreignId('plan_price_id')->nullable()->constrained('plan_prices')->nullOnDelete();
                $table->string('item_code_snapshot')->nullable();
                $table->text('description_snapshot');
                $table->unsignedInteger('quantity')->default(1);
                $table->bigInteger('unit_price_minor');
                $table->bigInteger('subtotal_minor');
                $table->bigInteger('discount_minor')->default(0);
                $table->unsignedInteger('tax_rate_bps')->nullable();
                $table->bigInteger('tax_minor')->default(0);
                $table->bigInteger('total_minor');
                $table->json('metadata')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['invoice_id', 'line_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach ([
                'next_billing_date',
                'current_period_end',
                'current_period_start',
                'total_minor',
                'tax_minor_v2',
                'tax_rate_bps',
                'discount_minor',
                'subtotal_minor',
                'setup_fee_minor_v2',
                'unit_price_minor',
                'plan_name_snapshot',
                'plan_code_snapshot',
                'quantity',
                'currency',
                'billing_interval_v2',
                'billing_engine_version',
                'plan_price_id',
                'plan_id',
            ] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plan_service');
        Schema::dropIfExists('plans');
    }
};
