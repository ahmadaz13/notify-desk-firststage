<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildPaymentsForSqlite();
        } else {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'amount_minor')) {
                    $table->bigInteger('amount_minor')->nullable()->after('amount');
                }
                if (! Schema::hasColumn('payments', 'currency')) {
                    $table->string('currency', 3)->nullable()->after('amount_minor');
                }
                if (! Schema::hasColumn('payments', 'payment_engine_version')) {
                    $table->string('payment_engine_version')->nullable()->after('currency')->index();
                }
                if (! Schema::hasColumn('payments', 'reference')) {
                    $table->string('reference')->nullable()->after('payment_method')->index();
                }
                if (! Schema::hasColumn('payments', 'received_at')) {
                    $table->dateTime('received_at')->nullable()->after('paid_at');
                }
            });

            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('subscription_id')->nullable()->change();
                $table->decimal('amount', 12, 3)->change();
                $table->index(['client_id', 'payment_engine_version']);
            });
        }

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->dateTime('allocated_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'allocated_at']);
            $table->index(['payment_id', 'allocated_at']);
            $table->index(['client_id', 'allocated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');

        Schema::table('payments', function (Blueprint $table) {
            foreach (['amount_minor', 'currency', 'payment_engine_version', 'reference', 'received_at'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function rebuildPaymentsForSqlite(): void
    {
        $columns = collect(DB::select('PRAGMA table_info(payments)'));
        $select = fn (string $column, string $fallback = 'NULL') => $this->sqliteColumnExpression($columns, $column, $fallback);

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('DROP TABLE IF EXISTS payments_c1_tmp');
        DB::statement(<<<'SQL'
CREATE TABLE payments_c1_tmp (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    client_id INTEGER NOT NULL,
    subscription_id INTEGER NULL,
    payment_schedule_id INTEGER NULL,
    amount NUMERIC NOT NULL,
    amount_minor INTEGER NULL,
    currency VARCHAR(3) NULL,
    payment_engine_version VARCHAR NULL,
    payment_method VARCHAR NOT NULL,
    reference VARCHAR NULL,
    paid_at DATETIME NOT NULL,
    received_at DATETIME NULL,
    recorded_by INTEGER NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY(subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY(payment_schedule_id) REFERENCES payment_schedules(id) ON DELETE SET NULL,
    FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE CASCADE
)
SQL);

        DB::statement(sprintf(
            <<<'SQL'
INSERT INTO payments_c1_tmp (
    id, client_id, subscription_id, payment_schedule_id, amount, amount_minor, currency,
    payment_engine_version, payment_method, reference, paid_at, received_at, recorded_by,
    notes, created_at, updated_at
)
SELECT
    id, client_id, subscription_id, payment_schedule_id, amount, %s, %s,
    %s, payment_method, %s, paid_at, %s, recorded_by,
    notes, created_at, updated_at
FROM payments
SQL,
            $select('amount_minor'),
            $select('currency'),
            $select('payment_engine_version'),
            $select('reference'),
            $select('received_at'),
        ));

        DB::statement('DROP TABLE payments');
        DB::statement('ALTER TABLE payments_c1_tmp RENAME TO payments');
        DB::statement('CREATE INDEX payments_client_id_index ON payments (client_id)');
        DB::statement('CREATE INDEX payments_subscription_id_index ON payments (subscription_id)');
        DB::statement('CREATE INDEX payments_payment_schedule_id_index ON payments (payment_schedule_id)');
        DB::statement('CREATE INDEX payments_paid_at_index ON payments (paid_at)');
        DB::statement('CREATE INDEX payments_reference_index ON payments (reference)');
        DB::statement('CREATE INDEX payments_client_id_payment_engine_version_index ON payments (client_id, payment_engine_version)');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    private function sqliteColumnExpression(Collection $columns, string $column, string $fallback): string
    {
        return $columns->contains(fn ($item) => $item->name === $column) ? $column : $fallback;
    }
};
