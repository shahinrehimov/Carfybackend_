<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum SupportTicketStatusEnum: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title(str_replace('_', ' ', $case->value)))
                    ->getLang('support_ticket_status_' . $case->value),
            ];
        }

        return $array;
    }
}
