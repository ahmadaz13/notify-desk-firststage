<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('custom_project_id')->nullable()->after('subscription_id')
                ->constrained('custom_projects')->restrictOnDelete();
            $table->index(['custom_project_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['custom_project_id', 'issue_date']);
            $table->dropConstrainedForeignId('custom_project_id');
        });
    }
};
