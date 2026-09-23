<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-10: client_contacts.name becomes nullable (an owner name is never required) and
     * client_contacts.email is added (§28.1).
     */
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });

        if (! Schema::hasColumn('client_contacts', 'email')) {
            Schema::table('client_contacts', function (Blueprint $table) {
                $table->string('email')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_contacts', 'email')) {
            Schema::table('client_contacts', fn (Blueprint $table) => $table->dropColumn('email'));
        }

        if (DB::table('client_contacts')->whereNull('name')->exists()) {
            throw new RuntimeException('Cannot make client_contacts.name NOT NULL: unnamed contacts exist.');
        }

        Schema::table('client_contacts', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
