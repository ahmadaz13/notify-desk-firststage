<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_ar');
                $table->string('name_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->nullOnDelete();
            }
            if (! Schema::hasColumn('plans', 'tier')) {
                $table->unsignedTinyInteger('tier')->nullable()->after('product_id')->index();
            }
            if (! Schema::hasColumn('plans', 'offer_type')) {
                $table->string('offer_type')->nullable()->after('tier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            foreach (['offer_type', 'tier', 'product_id'] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('products');
    }
};
