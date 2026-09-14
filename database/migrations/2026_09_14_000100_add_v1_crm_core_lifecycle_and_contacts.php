<?php

use App\Support\ClientLifecycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'stage')) {
                $table->string('stage')->default(ClientLifecycle::PROSPECT)->index()->after('status');
            }
            if (!Schema::hasColumn('clients', 'business_type')) {
                $table->string('business_type')->nullable()->after('business_category');
            }
            if (!Schema::hasColumn('clients', 'business_phone')) {
                $table->string('business_phone')->nullable()->index()->after('phone');
            }
            if (!Schema::hasColumn('clients', 'city')) {
                $table->string('city')->nullable()->after('city_area');
            }
            if (!Schema::hasColumn('clients', 'area')) {
                $table->string('area')->nullable()->after('city');
            }
            if (!Schema::hasColumn('clients', 'number_of_branches')) {
                $table->unsignedInteger('number_of_branches')->default(1)->after('area');
            }
            if (!Schema::hasColumn('clients', 'instagram')) {
                $table->string('instagram')->nullable()->after('maps_url');
            }
            if (!Schema::hasColumn('clients', 'website')) {
                $table->string('website')->nullable()->after('instagram');
            }
            if (!Schema::hasColumn('clients', 'source_reference')) {
                $table->string('source_reference')->nullable()->after('lead_source');
            }
            if (!Schema::hasColumn('clients', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->index()->after('notes');
            }
            if (!Schema::hasColumn('clients', 'closed_reason')) {
                $table->string('closed_reason')->nullable()->after('closed_at');
            }
        });

        if (!Schema::hasTable('client_contacts')) {
            Schema::create('client_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('name');
                $table->string('role')->nullable();
                $table->string('primary_phone')->nullable();
                $table->string('secondary_phone')->nullable();
                $table->string('whatsapp_number')->nullable();
                $table->string('preferred_contact_method')->nullable();
                $table->boolean('is_primary')->default(false)->index();
                $table->timestamps();

                $table->index(['client_id', 'is_primary']);
            });
        }

        Schema::table('contact_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_attempts', 'next_follow_up_date')) {
                $table->date('next_follow_up_date')->nullable()->index()->after('next_action');
            }
        });

        DB::table('clients')->orderBy('id')->chunkById(100, function ($clients) {
            foreach ($clients as $client) {
                $stage = ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
                $cityArea = (string) ($client->city_area ?? '');
                $city = $client->city ?? null;
                $area = $client->area ?? null;

                if (!$city && str_contains($cityArea, ' - ')) {
                    [$city, $area] = array_pad(array_map('trim', explode(' - ', $cityArea, 2)), 2, null);
                } elseif (!$city && $cityArea !== '') {
                    $city = $cityArea;
                }

                DB::table('clients')->where('id', $client->id)->update([
                    'stage' => $stage,
                    'business_phone' => $client->business_phone ?? $client->phone,
                    'business_type' => $client->business_type ?? $client->business_category,
                    'city' => $city,
                    'area' => $area,
                    'closed_at' => ($stage === ClientLifecycle::CLOSED && empty($client->closed_at)) ? now() : ($client->closed_at ?? null),
                    'updated_at' => $client->updated_at ?? now(),
                ]);

                if (!empty($client->contact_person)) {
                    $exists = DB::table('client_contacts')
                        ->where('client_id', $client->id)
                        ->where('name', $client->contact_person)
                        ->exists();

                    if (!$exists) {
                        DB::table('client_contacts')->insert([
                            'client_id' => $client->id,
                            'name' => $client->contact_person,
                            'role' => null,
                            'primary_phone' => null,
                            'secondary_phone' => null,
                            'whatsapp_number' => null,
                            'preferred_contact_method' => null,
                            'is_primary' => !DB::table('client_contacts')->where('client_id', $client->id)->where('is_primary', true)->exists(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('contact_attempts', 'next_follow_up_date')) {
                $table->dropColumn('next_follow_up_date');
            }
        });

        Schema::dropIfExists('client_contacts');

        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'closed_reason',
                'closed_at',
                'source_reference',
                'website',
                'instagram',
                'number_of_branches',
                'area',
                'city',
                'business_phone',
                'business_type',
                'stage',
            ] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
