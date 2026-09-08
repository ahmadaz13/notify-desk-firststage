<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('id')->constrained('partners')->nullOnDelete();
            $table->enum('role', ['admin', 'partner'])->default('admin')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn('role');
        });
    }
};
