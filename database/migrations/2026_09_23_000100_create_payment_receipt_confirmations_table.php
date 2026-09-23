<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('payment_receipt_confirmations')) {
            return;
        }

        Schema::create('payment_receipt_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('JOD');
            $table->string('payment_method', 32);
            $table->dateTime('received_at');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['submitted_by', 'status']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_receipt_confirmations ADD CONSTRAINT prc_amount_positive CHECK (amount_minor > 0)");
            DB::statement("ALTER TABLE payment_receipt_confirmations ADD CONSTRAINT prc_currency_jod CHECK (currency = 'JOD')");
            DB::statement("ALTER TABLE payment_receipt_confirmations ADD CONSTRAINT prc_method_v1 CHECK (payment_method IN ('cash', 'cliq'))");
            DB::statement("ALTER TABLE payment_receipt_confirmations ADD CONSTRAINT prc_status_valid CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite cannot add CHECK constraints to an existing table; enforce the same rules with triggers.
            foreach (['INSERT', 'UPDATE'] as $event) {
                $name = 'prc_guard_'.strtolower($event);
                DB::statement("CREATE TRIGGER {$name} BEFORE {$event} ON payment_receipt_confirmations
                    WHEN NEW.amount_minor <= 0
                        OR NEW.currency <> 'JOD'
                        OR NEW.payment_method NOT IN ('cash', 'cliq')
                        OR NEW.status NOT IN ('pending', 'approved', 'rejected', 'cancelled')
                    BEGIN SELECT RAISE(ABORT, 'payment_receipt_confirmations constraint violated'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipt_confirmations');
    }
};
