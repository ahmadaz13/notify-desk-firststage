<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('type')->index();
            $table->string('currency', 3)->default('JOD');
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('archived_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->nullable()->unique();
            $table->foreignId('from_financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('to_financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->string('currency', 3)->default('JOD');
            $table->bigInteger('amount_minor');
            $table->dateTime('transferred_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['from_financial_account_id', 'transferred_at']);
            $table->index(['to_financial_account_id', 'transferred_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('JOD');
            $table->string('event_type')->index();
            $table->string('event_key')->unique();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('occurred_at');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['financial_account_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('financial_transfer_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transfer_id')->unique()->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('reversed_at');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transfer_reversals');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('financial_transfers');
        Schema::dropIfExists('financial_accounts');
    }
};
