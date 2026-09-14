<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_categories', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('expense_categories', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }
            if (! Schema::hasColumn('expense_categories', 'name_en')) {
                $table->string('name_en')->nullable()->after('name_ar');
            }
            if (! Schema::hasColumn('expense_categories', 'description')) {
                $table->text('description')->nullable()->after('color');
            }
            if (! Schema::hasColumn('expense_categories', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_active');
            }
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recurring_expense_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('payee_name')->nullable();
            $table->string('currency', 3)->default('JOD');
            $table->bigInteger('amount_minor');
            $table->string('frequency')->index();
            $table->unsignedInteger('interval_count')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date')->index();
            $table->foreignId('default_financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('default_funding_source')->default('company_account');
            $table->foreignId('default_paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recurring_expense_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_template_id')->constrained('recurring_expense_templates')->restrictOnDelete();
            $table->date('due_date')->index();
            $table->bigInteger('expected_amount_minor');
            $table->string('currency', 3)->default('JOD');
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('category_name_snapshot');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('payee_name_snapshot')->nullable();
            $table->foreignId('default_financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('default_funding_source')->default('company_account');
            $table->string('status')->default('pending')->index();
            $table->foreignId('paid_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['recurring_expense_template_id', 'due_date'], 'recurring_obligation_template_due_unique');
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'expense_engine_version')) {
                $table->string('expense_engine_version')->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('expenses', 'amount_minor')) {
                $table->bigInteger('amount_minor')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('expenses', 'currency')) {
                $table->string('currency', 3)->nullable()->after('amount_minor');
            }
            if (! Schema::hasColumn('expenses', 'category_name_snapshot')) {
                $table->string('category_name_snapshot')->nullable()->after('category_id');
            }
            if (! Schema::hasColumn('expenses', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('category_name_snapshot')->constrained('vendors')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'payee_name_snapshot')) {
                $table->string('payee_name_snapshot')->nullable()->after('vendor_id');
            }
            if (! Schema::hasColumn('expenses', 'financial_account_id')) {
                $table->foreignId('financial_account_id')->nullable()->after('payee_name_snapshot')->constrained('financial_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'funding_source')) {
                $table->string('funding_source')->nullable()->after('financial_account_id')->index();
            }
            if (! Schema::hasColumn('expenses', 'paid_by_user_id')) {
                $table->foreignId('paid_by_user_id')->nullable()->after('funding_source')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'incurred_on')) {
                $table->date('incurred_on')->nullable()->after('paid_by_user_id')->index();
            }
            if (! Schema::hasColumn('expenses', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('incurred_on')->index();
            }
            if (! Schema::hasColumn('expenses', 'reference')) {
                $table->string('reference')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('expenses', 'recurring_expense_obligation_id')) {
                $table->foreignId('recurring_expense_obligation_id')->nullable()->after('reference')->constrained('recurring_expense_obligations')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('recurring_expense_obligation_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('expense_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->unique()->constrained('expenses')->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('reversed_at')->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_reversals');

        Schema::table('expenses', function (Blueprint $table) {
            foreach ([
                'created_by',
                'recurring_expense_obligation_id',
                'reference',
                'paid_at',
                'incurred_on',
                'paid_by_user_id',
                'funding_source',
                'financial_account_id',
                'payee_name_snapshot',
                'vendor_id',
                'category_name_snapshot',
                'currency',
                'amount_minor',
                'expense_engine_version',
            ] as $column) {
                if (Schema::hasColumn('expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('recurring_expense_obligations');
        Schema::dropIfExists('recurring_expense_templates');
        Schema::dropIfExists('vendors');

        Schema::table('expense_categories', function (Blueprint $table) {
            foreach (['archived_at', 'description', 'name_en', 'name_ar', 'code'] as $column) {
                if (Schema::hasColumn('expense_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
