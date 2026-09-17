<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'branch_name')) {
                $table->string('branch_name')->nullable()->after('location');
            }
        });

        if (!Schema::hasTable('installations')) {
            Schema::create('installations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
                $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments')->nullOnDelete();
                $table->foreignId('installed_by')->constrained('users')->restrictOnDelete();
                $table->dateTime('installed_at')->index();
                $table->string('branch_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'installed_at']);
            });
        }

        if (!Schema::hasTable('installation_items')) {
            Schema::create('installation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installation_id')->constrained('installations')->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->string('service_key')->nullable();
                $table->string('service_name_snapshot');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('service_key');
            });
        }

        Schema::table('follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_ups', 'installation_id')) {
                $table->foreignId('installation_id')->nullable()->after('client_id')->constrained('installations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'installation_id')) {
                $table->dropConstrainedForeignId('installation_id');
            }
        });

        Schema::dropIfExists('installation_items');
        Schema::dropIfExists('installations');

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'branch_name')) {
                $table->dropColumn('branch_name');
            }
        });
    }
};
