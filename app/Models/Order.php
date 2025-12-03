<?php

namespace App\Models;

use App\Casts\Orders\AmountCast;
use App\Casts\Orders\CodeCast;
use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Interfaces\Payment\PaymentMethodInterface;
use App\Services\LangService;
use App\Services\Payment\PaymentMethodFactory;
use App\Services\Payment\PaymentMethodHelper;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @property mixed $uuid
 * @property mixed $amount_final
 * @property mixed $payment_method
 * @property mixed $id
 * @property mixed $payment_status
 * @property mixed $status
 * @property mixed $user_id
 * @property mixed $statuses
 * @property array|mixed $statuses_tree
 * @property mixed $transaction_id
 */
class Order extends Model
{
    use SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'payment_status',
        'payment_method',
        'transaction_id',
        'paid_at', // nullable
        'refund_at', // nullable
        'amount',
        'amount_final',
        'total_discount_in_basket',
        'shipping',
        'total_quantity',
        'address_type_id',
        'city_id',
        'address_line_1',
        'address_line_2', // nullable
        'latitude',
        'longitude',
        'phone',
    ];

    protected $casts = [
        'code' => CodeCast::class,
        'status' => OrderStatusEnum::class,
        'payment_status' => OrderPaymentStatusEnum::class,
        'paid_at' => 'datetime',
        'refund_at' => 'datetime',
        'amount' => AmountCast::class,
        'amount_final' => AmountCast::class,
        'total_discount_in_basket' => AmountCast::class,
        'shipping' => AmountCast::class,
    ];

    protected array $cascadeDeletes = [
        'items',
        'statuses',
        'paymentStatuses',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            if (!$order->uuid) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addressType(): BelongsTo
    {
        return $this->belongsTo(AddressType::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(OrderStatus::class)->orderBy('id');
    }

    public function paymentStatuses(): HasMany
    {
        return $this->hasMany(OrderPaymentStatus::class);
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class);
    }

    // Payment method helper methods
    public function getPaymentMethodInstance(): PaymentMethodInterface
    {
        if (!$this->payment_method) {
            throw new BadRequestHttpException(LangService::instance()->setDefault('Payment method not set for order')->getLang('payment_method_not_set'));
        }

        return PaymentMethodFactory::create($this->payment_method);
    }

    public function getPaymentMethodDisplayName(): ?string
    {
        if (!$this->payment_method) {
            return null;
        }

        $details = PaymentMethodHelper::getMethodDetails($this->payment_method);
        return $details['display_name'] ?? $this->payment_method;
    }

    public function setPaymentMethodAttribute($value): void
    {
        if ($value && !$this->isValidPaymentMethod($value)) {
            throw new BadRequestHttpException(LangService::instance()->setDefault("Invalid payment method: @payment_method")->getLang('invalid_payment_method', ['@payment_method' => $value]));
        }

        $this->attributes['payment_method'] = $value;
    }

    private function isValidPaymentMethod(string $method): bool
    {
        try {
            $paymentMethod = PaymentMethodFactory::create($method);
            return $paymentMethod->isEnabled();
        } catch (\Exception) {
            return false;
        }
    }

    // Helpers
    public function getFormattedAmount(): string
    {
        return PaymentMethodHelper::formatAmount($this->amount_final);
    }

    public function canUsePaymentMethod(string $methodName): bool
    {
        return PaymentMethodHelper::isMethodAvailableForAmount($methodName, $this->amount_final);
    }

    // Scopes
    public function scopeWithPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', OrderPaymentStatusEnum::PENDING);
    }

    public function scopeCompletedPayment($query)
    {
        return $query->where('payment_status', OrderPaymentStatusEnum::PAID);
    }

    public function scopeFailedPayment($query)
    {
        return $query->where('payment_status', OrderPaymentStatusEnum::FAILED);
    }

    // Status checks
    public function isPaid(): bool
    {
        return $this->payment_status === OrderPaymentStatusEnum::PAID;
    }

    public function isPaymentPending(): bool
    {
        return $this->payment_status === OrderPaymentStatusEnum::PENDING;
    }

    public function hasPaymentFailed(): bool
    {
        return $this->payment_status === OrderPaymentStatusEnum::FAILED;
    }
}
