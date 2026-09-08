<?php

namespace App\Notifications;

use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConflictDetected extends Notification
{
    use Queueable;

    public function __construct(
        public Partner $partner,
        public string $phone,
        public ?ConflictResolutionRequest $conflictRequest = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'conflict_detected',
            'title' => 'تعارض عميل جديد',
            'message' => "طلب ربط عميل [{$this->phone}] من الشريك [{$this->partner->company_name}]. هذا الرقم مسجل مسبقاً. يرجى المراجعة.",
            'action_url' => route('conflicts.index'),
            'source_type' => 'conflict_resolution_request',
            'source_id' => $this->conflictRequest?->id,
        ];
    }
}
