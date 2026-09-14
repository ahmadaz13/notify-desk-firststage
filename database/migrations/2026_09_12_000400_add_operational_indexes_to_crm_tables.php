<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_at');
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->index('next_follow_up_date');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['appointment_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['appointment_date', 'status']);
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex(['next_follow_up_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
        });
    }
};
