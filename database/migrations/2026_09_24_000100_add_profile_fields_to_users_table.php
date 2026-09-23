<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-2: Staff work profile fields (§17). Profile UX is P13.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 50)->nullable();
            }
            if (! Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['avatar_path', 'job_title', 'phone'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
