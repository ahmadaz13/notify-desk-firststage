<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-4 (§18.2): client system credentials + append-only access log.
     * One active credential per (client_id, product_id) among non-deleted rows is enforced in
     * ClientCredentialService (MySQL has no partial unique index); a plain index backs the lookup.
     */
    public function up(): void
    {
        if (! Schema::hasTable('client_system_credentials')) {
            Schema::create('client_system_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->string('login_url', 500)->nullable();
                $table->string('username')->nullable();
                $table->text('secret');
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_revealed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['client_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('client_credential_access_logs')) {
            Schema::create('client_credential_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credential_id')->constrained('client_system_credentials')->restrictOnDelete();
                $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 16)->index();
                $table->string('channel', 16)->nullable();
                $table->string('recipient_masked', 32)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_credential_access_logs');
        Schema::dropIfExists('client_system_credentials');
    }
};
