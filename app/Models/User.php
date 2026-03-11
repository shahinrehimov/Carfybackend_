<?php

namespace App\Models;

use App\Casts\Users\FullNameCast;
use App\Notifications\CustomChangePasswordNotificationOTP;
use App\Notifications\CustomVerifyEmailNotification;
use App\Notifications\CustomVerifyEmailNotificationOTP;
use App\Notifications\ResetPasswordNotification;
use App\Services\UserService;
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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property mixed $is_active
 * @property mixed|string $token
 * @property mixed $password
 * @property mixed $id
 * @property mixed $email
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $email_verified_at
 * @property mixed $google_id
 * @property mixed $email_verified
 * @property mixed $created_at
 * @property mixed $register_type
 * @property mixed $phone_verified_at
 * @property mixed $phone
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'uuid',
        'register_type', // UserRegisterTypesEnum
        'name',
        'surname',
        'gender', // GenderEnum
        'birth_date',
        'email',
        'email_old',
        'email_verified_at',
        'email_unique_key', // if email exists and verified then md5(email) else md5(uuid)
        'phone',
        'phone_old',
        'phone_verified_at',
        'phone_unique_key', // if phone exists and verified then md5(phone) else md5(uuid)
        'password',
        'is_active',
        'google_id',
        'google_avatar',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
        'birth_date' => 'date',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'full_name' => FullNameCast::class,
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
        $_this = $this;

        return DB::transaction(function () use($_this) {
            $res = $_this
                ->forceFill([
                    'email_verified_at' => $_this->freshTimestamp(),
                    'email_unique_key' => md5($_this->email),
                ])
                ->save();

            UserService::instance()->removeFakeEmails($_this);

            return $res;
        });
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
            ->orderBy('wishlists.id')
            ->withPivot(['company_product_variant_id']);
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
            ->withPivot(['quantity', 'sets', 'id', 'company_product_variant_id']);
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
