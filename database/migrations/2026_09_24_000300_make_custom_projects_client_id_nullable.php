<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-6: custom_projects.client_id becomes nullable (§20a). The restrictOnDelete foreign key is kept:
     * Laravel 11 rebuilds the SQLite table with its foreign keys, and MySQL alters the column in place.
     */
    public function up(): void
    {
        Schema::table('custom_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });
    }

    /**
     * Reversible only while every project still has a client.
     */
    public function down(): void
    {
        if (DB::table('custom_projects')->whereNull('client_id')->exists()) {
            throw new RuntimeException('Cannot make custom_projects.client_id NOT NULL: projects without a client exist.');
        }

        Schema::table('custom_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });
    }
};
