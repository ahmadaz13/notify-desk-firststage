<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-8 (schema only): products.requires_credentials, default false (§6, §18.1).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'requires_credentials')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('requires_credentials')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'requires_credentials')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('requires_credentials'));
        }
    }
};
