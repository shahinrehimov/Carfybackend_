<?php

namespace App\Models;

use App\Notifications\CustomChangePasswordNotificationOTP;
use App\Notifications\CustomVerifyEmailNotification;
use App\Notifications\CustomVerifyEmailNotificationOTP;
use App\Notifications\ResetPasswordNotification;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property mixed $is_active
 * @property mixed|string $token
 * @property mixed $password
 * @property mixed $id
 * @property mixed $email
 * @property mixed $email_verified_at
 * @property mixed $google_id
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'gender',
        'birth_date',
        'password',
        'email_verified_at',
        'email_verified',
        'is_active',
        'google_id',
        'google_avatar',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
        'birth_date' => 'date',
        'email_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    public const EMAIL_VERIFICATION_TYPE = 'otp'; // otp or link

    protected array $cascadeDeletes = [
        'addresses',
        'otpCodes',
        'wishlists',
        'baskets',
        'orders',
        'thirdPartyProviderLoginTokens',
    ];

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmailNotification);
    }

    public function sendEmailVerificationNotificationOtp(int $otp): void
    {
        $this->notify(new CustomVerifyEmailNotificationOTP($otp));
    }

    public function sendChangePasswordNotificationOtp(int $otp): void
    {
        $this->notify(new CustomChangePasswordNotificationOTP($otp));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'email_verified' => 'yes',
        ])->save();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function scopeGoogleUser(Builder $builder): void
    {
        $builder->whereNotNull('google_id');
    }

    public function scopeIsEmailVerified(Builder $builder): void
    {
        $builder->whereNotNull('email_verified_at');
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function isGoogleUser(): bool
    {
        return $this->google_id !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(UserOtpCode::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wishlists(): MorphMany
    {
        return $this->morphMany(Wishlist::class, 'userable');
    }

    public function wishlistProducts(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'userable', Wishlist::class)
            ->whereNull('wishlists.deleted_at')
            ->orderBy('wishlists.id');
    }

    public function wishlistProductVariants(): MorphToMany
    {
        return $this->morphToMany(ProductVariant::class, 'userable', Wishlist::class)
            ->whereNull('wishlists.deleted_at')
            ->orderBy('wishlists.id');
    }

    public function baskets(): MorphMany
    {
        return $this->morphMany(Basket::class, 'userable');
    }

    public function basketProducts(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'userable', Basket::class)
            ->whereNull('baskets.deleted_at')
            ->orderBy('baskets.id')
            ->withPivot(['quantity', 'sets', 'id']);
    }

    public function basketProductVariants(): MorphToMany
    {
        return $this->morphToMany(ProductVariant::class, 'userable', Basket::class)
            ->whereNull('baskets.deleted_at')
            ->orderBy('baskets.id')
            ->withPivot(['quantity', 'sets', 'id']);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function thirdPartyProviderLoginTokens(): HasMany
    {
        return $this->hasMany(ThirdPartyProviderLoginToken::class);
    }
}
