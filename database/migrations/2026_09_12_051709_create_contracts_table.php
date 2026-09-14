<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique(); // ND-YYYY-NNNN
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->string('template_version')->default('1.0');
            $table->string('status')->default('draft'); // draft, issued, superseded, voided
            $table->string('legal_review_status')->default('pending'); // pending, approved
            $table->json('snapshot_data'); // Complete immutable snapshot of client, subscription, services, pricing, clauses, dates
            $table->string('private_file_path')->nullable();
            $table->string('file_hash')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('superseded_by_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
