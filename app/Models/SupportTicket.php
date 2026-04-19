<?php

namespace App\Models;

use App\Enum\SupportTicketStatusEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $company_id
 * @property mixed $subject
 * @property SupportTicketStatusEnum $status
 * @property string $opened_by
 * @property mixed $opened_by_id
 * @property mixed $assigned_to_admin_id
 * @property mixed $last_message_at
 * @property mixed $company
 * @property mixed $assignedAdmin
 */
class SupportTicket extends Model
{
    use SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'company_id',
        'subject',
        'status',
        'opened_by',
        'opened_by_id',
        'assigned_to_admin_id',
        'last_message_at',
    ];

    protected $casts = [
        'status' => SupportTicketStatusEnum::class,
        'last_message_at' => 'datetime',
    ];

    protected array $cascadeDeletes = ['messages'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === SupportTicketStatusEnum::CLOSED;
    }
}
