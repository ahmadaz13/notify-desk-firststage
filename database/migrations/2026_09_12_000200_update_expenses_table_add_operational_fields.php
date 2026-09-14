<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'description')) {
                $table->string('description', 255)->nullable()->after('category');
            }
            if (!Schema::hasColumn('expenses', 'time')) {
                $table->time('time')->nullable()->after('date');
            }
            if (!Schema::hasColumn('expenses', 'frequency')) {
                $table->string('frequency', 50)->default('one_time')->after('time');
            }
            if (!Schema::hasColumn('expenses', 'visibility')) {
                $table->string('visibility', 20)->default('shared')->after('frequency');
            }
            if (!Schema::hasColumn('expenses', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('expense_categories')->nullOnDelete();
            }
            $table->index('date');
            $table->index(['date', 'visibility']);
            $table->index(['paid_by', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['date', 'visibility']);
            $table->dropIndex(['paid_by', 'visibility']);
            if (Schema::hasColumn('expenses', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
            if (Schema::hasColumn('expenses', 'visibility')) {
                $table->dropColumn('visibility');
            }
            if (Schema::hasColumn('expenses', 'frequency')) {
                $table->dropColumn('frequency');
            }
            if (Schema::hasColumn('expenses', 'time')) {
                $table->dropColumn('time');
            }
            if (Schema::hasColumn('expenses', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
