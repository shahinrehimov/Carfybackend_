<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property mixed $id
 * @property mixed $support_ticket_id
 * @property string $sender_type
 * @property mixed $sender_id
 * @property string $body
 * @property bool $is_read_by_seller
 * @property bool $is_read_by_admin
 * @property mixed $sender
 * @property mixed $ticket
 */
class SupportMessage extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia;

    protected $fillable = [
        'support_ticket_id',
        'sender_type',
        'sender_id',
        'body',
        'is_read_by_seller',
        'is_read_by_admin',
    ];

    protected $casts = [
        'is_read_by_seller' => 'boolean',
        'is_read_by_admin' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('support_files');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        //
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo('sender', 'sender_type', 'sender_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->sender_type === Admin::class;
    }
}
