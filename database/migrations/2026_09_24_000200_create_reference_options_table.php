<?php

use App\Services\ReferenceDataService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * M-3: Operational reference data (§15.1) with the frozen V1 seed values.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reference_options')) {
            Schema::create('reference_options', function (Blueprint $table) {
                $table->id();
                $table->string('list_key', 64);
                $table->string('value');
                $table->string('label_ar');
                $table->string('label_en');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['list_key', 'value']);
                $table->index(['list_key', 'is_active', 'sort_order']);
            });
        }

        app(ReferenceDataService::class)->ensureDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_options');
    }
};
