<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('funding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('capital_funding_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('funding_number')->nullable()->unique();
            $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
            $table->string('source_name_snapshot');
            $table->string('funding_type')->index();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->string('currency', 3)->default('JOD');
            $table->bigInteger('amount_minor');
            $table->dateTime('received_at')->index();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('capital_funding_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_funding_transaction_id')->unique()->constrained('capital_funding_transactions')->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('reversed_at')->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number')->nullable()->unique();
            $table->string('name');
            $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('category_name_snapshot');
            $table->text('description')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('payee_name_snapshot')->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 3)->default('JOD');
            $table->bigInteger('acquisition_cost_minor');
            $table->string('funding_source')->index();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('acquired_at')->index();
            $table->date('in_service_at')->nullable();
            $table->string('reference')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('useful_life_months')->nullable();
            $table->bigInteger('residual_value_minor')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fixed_asset_acquisition_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->unique()->constrained('fixed_assets')->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('reversed_at')->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_acquisition_reversals');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('capital_funding_reversals');
        Schema::dropIfExists('capital_funding_transactions');
        Schema::dropIfExists('funding_sources');
    }
};
