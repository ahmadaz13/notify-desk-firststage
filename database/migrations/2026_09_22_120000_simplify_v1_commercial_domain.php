<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'referred_by_name')) {
            Schema::table('clients', fn (Blueprint $table) => $table->string('referred_by_name')->nullable());
        }
        if (! Schema::hasColumn('clients', 'referral_commission_bps')) {
            Schema::table('clients', fn (Blueprint $table) => $table->unsignedInteger('referral_commission_bps')->nullable());
        }
        if (! Schema::hasColumn('clients', 'referral_note')) {
            Schema::table('clients', fn (Blueprint $table) => $table->text('referral_note')->nullable());
        }

        if (! Schema::hasColumn('products', 'default_monthly_price_minor')) {
            Schema::table('products', fn (Blueprint $table) => $table->bigInteger('default_monthly_price_minor')->nullable());
        }
        if (! Schema::hasColumn('products', 'default_annual_price_minor')) {
            Schema::table('products', fn (Blueprint $table) => $table->bigInteger('default_annual_price_minor')->nullable());
        }

        if (! Schema::hasTable('client_system')) Schema::create('client_system', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('access_type')->default('free');
            $table->date('granted_at');
            $table->date('revoked_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'product_id']);
            $table->index(['client_id', 'revoked_at']);
        });

        if (! Schema::hasColumn('subscriptions', 'agreed_value_minor')) {
            Schema::table('subscriptions', fn (Blueprint $table) => $table->bigInteger('agreed_value_minor')->nullable());
        }
        if (! Schema::hasColumn('subscriptions', 'payment_terms')) {
            Schema::table('subscriptions', fn (Blueprint $table) => $table->string('payment_terms')->nullable());
        }

        if (! Schema::hasTable('subscription_system')) Schema::create('subscription_system', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('system_code_snapshot');
            $table->string('system_name_ar_snapshot');
            $table->string('system_name_en_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'product_id']);
        });

        Schema::dropIfExists('client_partner_attributions');
        Schema::dropIfExists('conflict_resolution_requests');

        if (Schema::hasColumn('clients', 'partner_id')) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::table('clients', fn (Blueprint $table) => $table->dropForeign(['partner_id']));
                DB::statement('DROP INDEX IF EXISTS clients_partner_id_index');
                Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('partner_id'));
            } else {
                Schema::table('clients', fn (Blueprint $table) => $table->dropConstrainedForeignId('partner_id'));
            }
        }
        if (Schema::hasColumn('users', 'partner_id')) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['partner_id']));
                DB::statement('DROP INDEX IF EXISTS users_partner_id_index');
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn('partner_id'));
            } else {
                Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('partner_id'));
            }
        }
        Schema::dropIfExists('partners');
    }

    public function down(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('status')->default('active');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->decimal('profit_share_percentage', 5, 2)->nullable();
            $table->decimal('deduction_percentage', 5, 2)->default(20.00)->nullable();
            $table->unsignedInteger('default_commission_bps')->nullable();
            $table->string('public_uuid')->unique();
            $table->timestamps();
            $table->timestamp('onboarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', fn (Blueprint $table) => $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete());
        Schema::table('clients', fn (Blueprint $table) => $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete());

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

        Schema::create('conflict_resolution_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('submitted_phone');
            $table->string('submitted_name');
            $table->string('submitted_area')->nullable();
            $table->string('submitted_source')->nullable();
            $table->enum('status', ['pending', 'resolved_transferred', 'resolved_updated', 'rejected'])->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('subscription_system');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['agreed_value_minor', 'payment_terms']));
        Schema::dropIfExists('client_system');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['default_monthly_price_minor', 'default_annual_price_minor']));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['referred_by_name', 'referral_commission_bps', 'referral_note']));
    }
};
