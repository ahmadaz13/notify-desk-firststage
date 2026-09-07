<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan upcoming appointments and payment schedules and generate in-app reminders';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('بدء فحص التذكيرات المجدولة...');

        $appointmentNotifications = $notificationService->sendAppointmentReminders();
        $this->info("تم إنشاء {$appointmentNotifications} إشعار لمواعيد قادمة.");

        $paymentNotifications = $notificationService->sendPaymentReminders();
        $this->info("تم إنشاء {$paymentNotifications} إشعار لمدفوعات مستحقة/متأخرة.");

        $total = $appointmentNotifications + $paymentNotifications;
        $this->info("اكتمل الفحص بنجاح. إجمالي الإشعارات المنشأة: {$total}");

        return Command::SUCCESS;
    }
}
