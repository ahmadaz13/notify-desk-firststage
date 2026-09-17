<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('company_name');
            $table->unsignedInteger('default_commission_bps')->nullable()->after('profit_share_percentage');
            $table->text('notes')->nullable()->after('onboarded_at');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        DB::table('partners')
            ->whereNull('default_commission_bps')
            ->whereNotNull('profit_share_percentage')
            ->update(['default_commission_bps' => DB::raw('ROUND(profit_share_percentage * 100)')]);

        Schema::create('client_partner_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('commission_bps_snapshot');
            $table->dateTime('attributed_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'attributed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_partner_attributions');

        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['contact_name', 'default_commission_bps', 'notes']);
        });
    }
};
