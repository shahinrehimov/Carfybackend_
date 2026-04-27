<?php

namespace App\Enum;

enum NotificationStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SENDING = 'sending';
    case SENT = 'sent';
    case CANCELLED = 'cancelled';

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_APPROVAL, self::REJECTED]);
    }

    public function isSendable(): bool
    {
        return in_array($this, [self::APPROVED, self::DRAFT]);
    }
}
