<?php

namespace App\Console\Commands;

use App\Enum\NotificationEventTypeEnum;
use App\Models\NotificationTypeSetting;
use Illuminate\Console\Command;

class SyncNotificationEventTypesCommand extends Command
{
    protected $signature = 'notifications:sync-event-types';
    protected $description = 'Sync notification_type_settings with all NotificationEventTypeEnum cases';

    public function handle(): void
    {
        $existing = NotificationTypeSetting::query()->pluck('event_type')->toArray();
        $added = 0;

        foreach (NotificationEventTypeEnum::cases() as $case) {
            if (!in_array($case->value, $existing)) {
                NotificationTypeSetting::query()->create([
                    'event_type' => $case->value,
                    'is_active' => true,
                    'channels' => NotificationEventTypeEnum::getDefaultChannels($case),
                ]);
                $added++;
                $this->line("  Added: {$case->value}");
            }
        }

        $this->info("Sync complete. {$added} new event type(s) added.");
    }
}
