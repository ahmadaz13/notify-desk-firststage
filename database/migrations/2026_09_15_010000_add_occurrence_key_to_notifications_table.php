<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'occurrence_key')) {
                $table->string('occurrence_key')->nullable()->after('source_id');
            }

            $table->unique(
                ['user_id', 'type', 'source_type', 'source_id', 'occurrence_key'],
                'notifications_occurrence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_occurrence_unique');

            if (Schema::hasColumn('notifications', 'occurrence_key')) {
                $table->dropColumn('occurrence_key');
            }
        });
    }
};
