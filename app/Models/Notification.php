<?php

namespace App\Models;

use App\Enum\NotificationActionTypeEnum;
use App\Enum\NotificationChannelEnum;
use App\Enum\NotificationSourceEnum;
use App\Enum\NotificationStatusEnum;
use App\Enum\NotificationTargetTypeEnum;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property NotificationSourceEnum $source
 * @property string|null $event_type
 * @property NotificationStatusEnum $status
 * @property NotificationTargetTypeEnum $target_type
 * @property array|null $target_filters
 * @property array $channels
 * @property NotificationActionTypeEnum $action_type
 * @property string|null $action_id
 * @property string|null $image
 * @property string|null $sender_type
 * @property int|null $sender_id
 * @property int|null $approved_by_id
 * @property \Carbon\Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property \Carbon\Carbon|null $scheduled_at
 * @property \Carbon\Carbon|null $sent_at
 * @property int $total_recipients
 * @property \Illuminate\Database\Eloquent\Collection $recipients
 * @property Admin|null $approvedBy
 * @property Model|null $sender
 */
class Notification extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, CascadeSoftDeletes;

    protected $fillable = [
        'uuid',
        'source',
        'event_type',
        'status',
        'target_type',
        'target_filters',
        'channels',
        'action_type',
        'action_id',
        'image',
        'sender_type',
        'sender_id',
        'approved_by_id',
        'approved_at',
        'rejection_reason',
        'scheduled_at',
        'sent_at',
        'total_recipients',
    ];

    protected $casts = [
        'source' => NotificationSourceEnum::class,
        'status' => NotificationStatusEnum::class,
        'target_type' => NotificationTargetTypeEnum::class,
        'action_type' => NotificationActionTypeEnum::class,
        'target_filters' => 'array',
        'channels' => 'array',
        'approved_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected array $translatableFields = ['title', 'body'];

    protected array $cascadeDeletes = ['translatable', 'recipients'];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function sender(): MorphTo
    {
        return $this->morphTo('sender');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_id');
    }

    public function hasChannel(NotificationChannelEnum $channel): bool
    {
        return in_array($channel->value, $this->channels ?? []);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
