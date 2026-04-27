<?php

namespace App\Console\Commands;

use App\Models\NotificationRecipient;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupNotificationsCommand extends Command
{
    protected $signature = 'notifications:cleanup';
    protected $description = 'Delete old notification recipients based on retention days setting';

    public function handle(): void
    {
        $settings = SettingService::instance()->get();
        $days = (int) ($settings->notification_retention_days ?? 90);

        $cutoff = Carbon::now()->subDays($days);

        $deleted = NotificationRecipient::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Cleanup complete. {$deleted} old notification recipient(s) deleted (retention: {$days} days).");
    }
}
