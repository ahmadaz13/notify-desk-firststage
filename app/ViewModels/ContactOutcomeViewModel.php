<?php

namespace App\ViewModels;

use App\Support\AppointmentTypes;

class ContactOutcomeViewModel
{
    public function __construct(
        public readonly array $methods,
        public readonly array $outcomes,
        public readonly array $appointmentTypes,
        public readonly string $defaultMethod,
        public readonly string $defaultOutcome
    ) {
    }

    public static function make(array $appointmentTypeLabels): self
    {
        $outcomes = [
            [
                'value' => 'appointment',
                'label' => 'موعد',
                'summary' => 'ينشئ موعداً حقيقياً ويحدث المرحلة إلى موعد.',
                'requires' => ['appointment_date', 'appointment_time'],
                'review' => false,
            ],
            [
                'value' => 'no_contact',
                'label' => 'لا يوجد تواصل',
                'summary' => 'يسجل محاولة دون موعد أو متابعة إلزامية.',
                'requires' => [],
                'review' => false,
            ],
            [
                'value' => 'callback_later',
                'label' => 'معاودة لاحقاً',
                'summary' => 'ينشئ متابعة بتاريخ ووقت محددين.',
                'requires' => ['follow_up_date_time'],
                'review' => false,
            ],
            [
                'value' => 'no_answer_busy',
                'label' => 'لا رد / مشغول',
                'summary' => 'يعيد ترتيب العميل داخل قائمة التواصل النشطة.',
                'requires' => [],
                'review' => false,
            ],
            [
                'value' => 'wrong_invalid',
                'label' => 'رقم خاطئ / غير صالح',
                'summary' => 'ينشئ بند مراجعة تشغيلية ولا يغلق العميل.',
                'requires' => [],
                'review' => true,
            ],
            [
                'value' => 'not_interested',
                'label' => 'غير مهتم',
                'summary' => 'يتطلب ملاحظة، وينشئ بند مراجعة دون إغلاق تلقائي.',
                'requires' => ['note'],
                'review' => true,
            ],
        ];

        return new self(
            methods: [
                'phone' => 'اتصال',
                'whatsapp' => 'واتساب',
                'field_visit' => 'زيارة ميدانية',
                'instagram' => 'Instagram',
                'other' => 'أخرى',
            ],
            outcomes: $outcomes,
            appointmentTypes: array_intersect_key($appointmentTypeLabels, array_flip([
                AppointmentTypes::PHONE_CALL,
                AppointmentTypes::PHYSICAL_VISIT,
                AppointmentTypes::ONLINE_DEMO,
                AppointmentTypes::INSTALLATION,
            ])),
            defaultMethod: 'phone',
            defaultOutcome: 'no_contact'
        );
    }
}
