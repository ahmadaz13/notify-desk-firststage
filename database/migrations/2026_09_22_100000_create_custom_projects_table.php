<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('name', 255);
            $table->bigInteger('agreed_value_minor')->default(0)->comment('Project agreed value in fils (integer minor units, 1 JOD = 1000 fils)');
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->string('status', 30)->default('planned')->comment('planned|active|completed|cancelled');
            $table->text('notes')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_projects');
    }
};
