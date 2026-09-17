<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'status')) {
                $table->string('status')->default('active')->after('company_name');
            }
            if (!Schema::hasColumn('partners', 'onboarded_at')) {
                $table->timestamp('onboarded_at')->nullable()->after('updated_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_login_at')) {
                $table->timestamp('first_login_at')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'reset_expires_at')) {
                $table->timestamp('reset_expires_at')->nullable()->after('first_login_at');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'client_id')) {
                $table->foreignId('client_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('partners', 'onboarded_at')) {
                $table->dropColumn('onboarded_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'first_login_at')) {
                $table->dropColumn('first_login_at');
            }
            if (Schema::hasColumn('users', 'reset_expires_at')) {
                $table->dropColumn('reset_expires_at');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            // Revert nullable
        });
    }
};
