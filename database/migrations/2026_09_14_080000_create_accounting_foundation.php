<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('account_type')->index();
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('normal_balance');
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->boolean('allow_direct_posting')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->foreignId('chart_account_id')->nullable()->after('currency')->constrained('chart_accounts')->nullOnDelete();
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('chart_account_id')->nullable()->after('code')->constrained('chart_accounts')->nullOnDelete();
        });

        Schema::table('asset_categories', function (Blueprint $table) {
            $table->foreignId('chart_account_id')->nullable()->after('code')->constrained('chart_accounts')->nullOnDelete();
        });

        Schema::create('accounting_system_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('mapping_key')->unique();
            $table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_key')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open')->index();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number')->nullable()->unique();
            $table->string('event_type')->index();
            $table->string('event_key')->unique();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('entry_date')->index();
            $table->text('description');
            $table->string('status')->default('posted')->index();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('chart_account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['chart_account_id', 'created_at']);
            $table->index(['financial_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_system_mappings');

        Schema::table('asset_categories', function (Blueprint $table) {
            if (Schema::hasColumn('asset_categories', 'chart_account_id')) {
                $table->dropConstrainedForeignId('chart_account_id');
            }
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            if (Schema::hasColumn('expense_categories', 'chart_account_id')) {
                $table->dropConstrainedForeignId('chart_account_id');
            }
        });

        Schema::table('financial_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('financial_accounts', 'chart_account_id')) {
                $table->dropConstrainedForeignId('chart_account_id');
            }
        });

        Schema::dropIfExists('chart_accounts');
    }
};

