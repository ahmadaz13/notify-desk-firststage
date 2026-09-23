<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FreeInstallationService
{
    public function scheduleInstallation(Client $client, User $actor, array $data): Appointment
    {
        return DB::transaction(function () use ($client, $actor, $data) {
            $appointment = Appointment::create([
                'client_id' => $client->id,
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'appointment_type' => AppointmentTypes::INSTALLATION,
                'location' => $data['location'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
            ]);

            $appointment->users()->sync($data['attendees'] ?? [$actor->id]);

            $stage = ClientLifecycle::normalizeStage($client->stage, $client->status);
            if (!in_array($stage, ClientLifecycle::PIPELINE_LOCKED_STAGES, true)) {
                app(ClientOperationalWorkflowService::class)->transition($client, ClientLifecycle::INSTALLATION_SCHEDULED, $actor);
            }

            $this->log($client->id, $actor->id, 'installation_scheduled', 'تم جدولة تركيب مجاني للعميل', [
                'appointment_id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date?->toDateString(),
                'appointment_time' => $appointment->appointment_time,
                'branch_name' => $appointment->branch_name,
            ]);

            return $appointment;
        });
    }

    public function rescheduleAppointment(Appointment $appointment, User $actor, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $data) {
            if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'لا يمكن إعادة جدولة موعد مكتمل أو ملغى.',
                ]);
            }

            $previous = [
                'appointment_date' => $appointment->appointment_date?->toDateString(),
                'appointment_time' => $appointment->appointment_time,
                'location' => $appointment->location,
                'branch_name' => $appointment->branch_name,
            ];

            $appointment->update([
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'location' => $data['location'] ?? $appointment->location,
                'branch_name' => $data['branch_name'] ?? $appointment->branch_name,
                'notes' => $data['notes'] ?? $appointment->notes,
                'status' => 'scheduled',
            ]);

            if (array_key_exists('attendees', $data)) {
                $appointment->users()->sync($data['attendees'] ?: [$actor->id]);
            }

            $client = $appointment->client;
            if ($appointment->appointment_type === AppointmentTypes::INSTALLATION) {
                $stage = ClientLifecycle::normalizeStage($client->stage, $client->status);
                if (!in_array($stage, ClientLifecycle::PIPELINE_LOCKED_STAGES, true)) {
                    app(ClientOperationalWorkflowService::class)->transition($client, ClientLifecycle::INSTALLATION_SCHEDULED, $actor);
                }
            }

            $this->log($client->id, $actor->id, 'appointment_rescheduled', 'تمت إعادة جدولة الموعد', [
                'appointment_id' => $appointment->id,
                'appointment_type' => $appointment->appointment_type,
                'previous' => $previous,
                'new' => [
                    'appointment_date' => $appointment->appointment_date?->toDateString(),
                    'appointment_time' => $appointment->appointment_time,
                    'location' => $appointment->location,
                    'branch_name' => $appointment->branch_name,
                ],
            ]);

            return $appointment->refresh();
        });
    }

    public function cancelInstallationAppointment(Appointment $appointment, User $actor, ?string $nextStage = null): void
    {
        DB::transaction(function () use ($appointment, $actor, $nextStage) {
            $client = $appointment->client;

            $appointment->update(['status' => 'cancelled']);

            $activeOtherInstallations = Appointment::query()
                ->where('client_id', $client->id)
                ->where('id', '!=', $appointment->id)
                ->where('appointment_type', AppointmentTypes::INSTALLATION)
                ->whereIn('status', AppointmentTypes::activeStatuses())
                ->whereDate('appointment_date', '>=', Carbon::today())
                ->exists();

            $hasCompletedInstallation = Installation::where('client_id', $client->id)->exists();
            $currentStage = ClientLifecycle::normalizeStage($client->stage, $client->status);

            if (!$activeOtherInstallations && !$hasCompletedInstallation && $currentStage === ClientLifecycle::INSTALLATION_SCHEDULED) {
                $allowedNextStages = [
                    ClientLifecycle::PROSPECT,
                    ClientLifecycle::CONTACTING,
                    ClientLifecycle::APPOINTMENT,
                    ClientLifecycle::DECISION_PENDING,
                ];

                if (!in_array($nextStage, $allowedNextStages, true)) {
                    throw ValidationException::withMessages([
                        'next_stage' => 'اختر المرحلة التالية بعد إلغاء موعد التركيب.',
                    ]);
                }

                app(ClientOperationalWorkflowService::class)->transition($client, $nextStage, $actor);
            }

            $this->log($client->id, $actor->id, 'appointment_cancelled', 'تم إلغاء موعد التركيب المجاني', [
                'appointment_id' => $appointment->id,
                'next_stage' => $client->fresh()->stage,
            ]);
        });
    }

    public function completeInstallation(Client $client, User $actor, array $data): Installation
    {
        return DB::transaction(function () use ($client, $actor, $data) {
            $appointment = null;
            if (!empty($data['appointment_id'])) {
                $appointment = Appointment::where('id', $data['appointment_id'])->lockForUpdate()->firstOrFail();

                if ((int) $appointment->client_id !== (int) $client->id) {
                    throw ValidationException::withMessages([
                        'appointment_id' => 'موعد التركيب لا يتبع هذا العميل.',
                    ]);
                }

                if ($appointment->appointment_type !== AppointmentTypes::INSTALLATION) {
                    throw ValidationException::withMessages([
                        'appointment_id' => 'الموعد المحدد ليس موعد تركيب.',
                    ]);
                }

                if (Installation::where('appointment_id', $appointment->id)->exists()) {
                    throw ValidationException::withMessages([
                        'appointment_id' => 'تم تسجيل تركيب لهذا الموعد مسبقاً.',
                    ]);
                }
            }

            $serviceIds = collect($data['service_ids'] ?? [])->filter()->unique()->values();
            $customItems = collect(preg_split('/\r\n|\r|\n/', (string) ($data['custom_item_names'] ?? '')))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values();

            if ($serviceIds->isEmpty() && $customItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'installed_items' => 'أدخل عنصراً واحداً على الأقل مما تم تركيبه.',
                ]);
            }

            $installation = Installation::create([
                'client_id' => $client->id,
                'appointment_id' => $appointment?->id,
                'installed_by' => $data['installed_by'] ?? $actor->id,
                'installed_at' => Carbon::parse($data['installed_at']),
                'branch_name' => $data['branch_name'] ?? $appointment?->branch_name,
                'notes' => $data['notes'] ?? null,
            ]);

            $itemNames = [];
            if ($serviceIds->isNotEmpty()) {
                Service::whereIn('id', $serviceIds)->get()->each(function (Service $service) use ($installation, &$itemNames) {
                    $name = $service->name_ar ?: $service->name_en;
                    $installation->items()->create([
                        'service_id' => $service->id,
                        'service_key' => $service->key,
                        'service_name_snapshot' => $name,
                    ]);
                    $itemNames[] = $name;
                });
            }

            foreach ($customItems as $customItem) {
                $installation->items()->create([
                    'service_id' => null,
                    'service_key' => null,
                    'service_name_snapshot' => $customItem,
                ]);
                $itemNames[] = $customItem;
            }

            if ($appointment) {
                $appointment->update(['status' => 'completed']);
            }

            app(ClientOperationalWorkflowService::class)->transition($client, ClientLifecycle::INSTALLED_FREE, $actor);

            $followUpId = null;
            $followUpAt = !empty($data['next_follow_up_date'])
                ? Carbon::parse($data['next_follow_up_date'])
                : Carbon::parse($installation->installed_at)->addDays(3)->setTime(10, 0);

            $followUpId = app(ClientOperationalWorkflowService::class)->scheduleFollowUp($client, $actor, [
                'installation_id' => $installation->id,
                'user_id' => $installation->installed_by ?: $actor->id,
                'method' => 'phone',
                'reason' => 'متابعة ما بعد التركيب المجاني',
                'next_action' => $data['next_action'] ?? 'متابعة قرار العميل بعد التركيب',
                'follow_up_date_time' => $followUpAt->toDateTimeString(),
                'notes' => $data['follow_up_notes'] ?? null,
            ]);

            $this->log($client->id, $actor->id, 'trial_followup_scheduled', 'تمت جدولة متابعة تجربة 3 أيام بعد التركيب المجاني', [
                'installation_id' => $installation->id,
                'follow_up_id' => $followUpId,
                'follow_up_date_time' => $followUpAt->toDateTimeString(),
            ]);

            $this->log($client->id, $actor->id, 'free_installation_completed', 'تم تسجيل اكتمال التركيب المجاني', [
                'installation_id' => $installation->id,
                'appointment_id' => $appointment?->id,
                'installed_by' => $installation->installed_by,
                'installed_at' => $installation->installed_at->toDateTimeString(),
                'branch_name' => $installation->branch_name,
                'installed_item_names' => $itemNames,
                'post_install_follow_up_date' => $followUpAt->toDateString(),
                'follow_up_id' => $followUpId,
            ]);

            return $installation->load(['items', 'installedBy', 'appointment']);
        });
    }

    private function log(int $clientId, int $userId, string $type, string $description, array $metadata = []): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
