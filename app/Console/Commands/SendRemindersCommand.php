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
    protected $description = 'Generate due operational reminders and commercial finance alert notifications';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('بدء فحص التذكيرات والتنبيهات المجدولة...');

        $total = $notificationService->sendScheduledAutomationNotifications();
        $this->info("اكتمل الفحص بنجاح. إجمالي الإشعارات المنشأة: {$total}");

        return Command::SUCCESS;
    }
}
