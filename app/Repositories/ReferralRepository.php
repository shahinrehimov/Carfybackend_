<?php

namespace App\Repositories;

use App\Enum\Referral\ReferralAttributionStatusEnum;
use App\Enum\Referral\ReferralEarningStatusEnum;
use App\Enum\Referral\ReferralFraudFlagTypeEnum;
use App\Enum\Referral\ReferralFraudSeverityEnum;
use App\Enum\Referral\ReferralTierEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Order;
use App\Models\Referral\ReferralActivityStatus;
use App\Models\Referral\ReferralAttribution;
use App\Models\Referral\ReferralBalance;
use App\Models\Referral\ReferralCode;
use App\Models\Referral\ReferralEarning;
use App\Models\Referral\ReferralFraudFlag;
use App\Models\Referral\ReferredUserPerk;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralRepository
{
    // -------------------------------------------------------------------------
    // Code management
    // -------------------------------------------------------------------------

    public function generateCodeForUser(User $user): ReferralCode
    {
        $code = $this->generateUniqueCode();

        return ReferralCode::create([
            'user_id'   => $user->id,
            'code'      => $code,
            'is_custom' => false,
            'is_active' => true,
        ]);
    }

    public function updateUserCode(User $user, string $newCode): ReferralCode
    {
        $newCode = strtoupper(trim($newCode));

        return DB::transaction(function () use ($user, $newCode) {
            ReferralCode::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return ReferralCode::create([
                'user_id'   => $user->id,
                'code'      => $newCode,
                'is_custom' => true,
                'is_active' => true,
            ]);
        });
    }

    public function findActiveCode(string $code): ?ReferralCode
    {
        return ReferralCode::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }

    public function codeExistsForOtherUser(string $code, int $excludeUserId): bool
    {
        return ReferralCode::where('code', strtoupper(trim($code)))
            ->where('user_id', '!=', $excludeUserId)
            ->exists();
    }

    public function getUserActiveCode(User $user): ?ReferralCode
    {
        return ReferralCode::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    // -------------------------------------------------------------------------
    // Attribution
    // -------------------------------------------------------------------------

    public function createAttribution(User $referrer, User $referred): ReferralAttribution
    {
        return ReferralAttribution::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'status'      => ReferralAttributionStatusEnum::PENDING,
        ]);
    }

    public function getAttributionForReferred(User $referred): ?ReferralAttribution
    {
        return ReferralAttribution::where('referred_id', $referred->id)->first();
    }

    public function activateAttribution(ReferralAttribution $attribution, Order $order): void
    {
        $attribution->update([
            'status'                   => ReferralAttributionStatusEnum::ACTIVE,
            'first_qualifying_order_id' => $order->id,
            'activated_at'             => now(),
        ]);
    }

    public function getActiveAttributionCountForReferrer(User $referrer): int
    {
        return ReferralAttribution::where('referrer_id', $referrer->id)
            ->where('status', ReferralAttributionStatusEnum::ACTIVE)
            ->count();
    }

    // -------------------------------------------------------------------------
    // Perks (referred user)
    // -------------------------------------------------------------------------

    public function getOrCreatePerks(User $user): ReferredUserPerk
    {
        return ReferredUserPerk::firstOrCreate(
            ['user_id' => $user->id],
            ['free_delivery_used_count' => 0, 'discount_orders_count' => 0, 'updated_at' => now()]
        );
    }

    public function useFreeDeliveryPerk(User $user): bool
    {
        $perk = $this->getOrCreatePerks($user);
        if (!$perk->hasFreeDelivery()) {
            return false;
        }

        $perk->increment('free_delivery_used_count');
        $perk->touch('updated_at');
        return true;
    }

    public function useDiscountPerk(User $user): bool
    {
        $perk = $this->getOrCreatePerks($user);
        if (!$perk->hasDiscount()) {
            return false;
        }

        $perk->increment('discount_orders_count');
        $perk->touch('updated_at');
        return true;
    }

    /**
     * Release a consumed perk slot when its order is fully cancelled.
     * Plan §3: cancelled/refunded orders must not count toward the 3-order limit.
     */
    public function releaseFreeDeliveryPerk(User $user): void
    {
        $perk = $this->getOrCreatePerks($user);
        if ($perk->free_delivery_used_count > 0) {
            $perk->decrement('free_delivery_used_count');
            $perk->touch('updated_at');
        }
    }

    public function releaseDiscountPerk(User $user): void
    {
        $perk = $this->getOrCreatePerks($user);
        if ($perk->discount_orders_count > 0) {
            $perk->decrement('discount_orders_count');
            $perk->touch('updated_at');
        }
    }

    // -------------------------------------------------------------------------
    // Earnings
    // -------------------------------------------------------------------------

    public function createEarning(
        User $referrer,
        User $referred,
        Order $order,
        ReferralTierEnum $tier,
        int $orderAmountCents
    ): ReferralEarning {
        $rate   = $tier->rate(); // basis points
        $amount = (int) round($orderAmountCents * $rate / 10000);

        $pendingDays  = (int) (SettingService::instance()->get()->referral_pending_days ?? 14);
        $deliveredAt  = now();
        $availableAt  = $deliveredAt->copy()->addDays($pendingDays);

        return ReferralEarning::create([
            'referrer_id'  => $referrer->id,
            'referred_id'  => $referred->id,
            'order_id'     => $order->id,
            'tier_at_time' => $tier->value,
            'rate'         => $rate,
            'order_amount' => $orderAmountCents,
            'amount'       => $amount,
            'status'       => ReferralEarningStatusEnum::PENDING,
            'delivered_at' => $deliveredAt,
            'available_at' => $availableAt,
        ]);
    }

    public function creditPendingEarning(ReferralEarning $earning): void
    {
        DB::transaction(function () use ($earning) {
            $earning->update([
                'status'      => ReferralEarningStatusEnum::AVAILABLE,
                'credited_at' => now(),
            ]);

            $balance = $this->getOrCreateBalance($earning->referrer_id);
            $balance->decrement('pending_balance', $earning->amount);
            $balance->increment('available_balance', $earning->amount);
            $balance->increment('lifetime_earned', $earning->amount);
            $balance->update(['updated_at' => now()]);
        });
    }

    /**
     * Release a FRAUD_HOLD earning to available (admin approved the flag).
     * Unlike creditPendingEarning, this does NOT touch pending_balance since
     * fraud_hold earnings were never added to pending_balance.
     */
    public function releaseFraudHoldEarning(ReferralEarning $earning): void
    {
        DB::transaction(function () use ($earning) {
            $earning->update([
                'status'      => ReferralEarningStatusEnum::AVAILABLE,
                'credited_at' => now(),
            ]);

            $balance = $this->getOrCreateBalance($earning->referrer_id);
            $balance->increment('available_balance', $earning->amount);
            $balance->increment('lifetime_earned', $earning->amount);
            $balance->update(['updated_at' => now()]);
        });
    }

    /**
     * Create a FRAUD_HOLD earning (referrer has unresolved hard block).
     * Does NOT add to pending_balance — held until admin resolves the flag.
     */
    public function createFraudHoldEarning(
        User $referrer,
        User $referred,
        Order $order,
        ReferralTierEnum $tier,
        int $orderAmountCents
    ): ReferralEarning {
        $rate   = $tier->rate();
        $amount = (int) round($orderAmountCents * $rate / 10000);

        return ReferralEarning::create([
            'referrer_id'  => $referrer->id,
            'referred_id'  => $referred->id,
            'order_id'     => $order->id,
            'tier_at_time' => $tier->value,
            'rate'         => $rate,
            'order_amount' => $orderAmountCents,
            'amount'       => $amount,
            'status'       => ReferralEarningStatusEnum::FRAUD_HOLD,
            'delivered_at' => now(),
            'available_at' => null,
        ]);
    }

    public function cancelEarning(ReferralEarning $earning): void
    {
        if ($earning->status !== ReferralEarningStatusEnum::PENDING) {
            return;
        }

        DB::transaction(function () use ($earning) {
            $earning->update(['status' => ReferralEarningStatusEnum::CANCELLED]);

            $balance = $this->getOrCreateBalance($earning->referrer_id);
            $balance->decrement('pending_balance', $earning->amount);
            $balance->update(['updated_at' => now()]);
        });
    }

    public function partialRefundEarning(ReferralEarning $earning, int $refundedAmountCents): void
    {
        if ($earning->status !== ReferralEarningStatusEnum::PENDING) {
            return;
        }

        $deduction        = (int) round($refundedAmountCents * $earning->rate / 10000);
        $deduction        = min($deduction, $earning->amount);
        $orderAmountDrop  = min($refundedAmountCents, (int) $earning->order_amount);

        DB::transaction(function () use ($earning, $deduction, $orderAmountDrop) {
            $earning->decrement('amount', $deduction);
            $earning->decrement('order_amount', $orderAmountDrop);

            $balance = $this->getOrCreateBalance($earning->referrer_id);
            $balance->decrement('pending_balance', $deduction);
            $balance->update(['updated_at' => now()]);
        });
    }

    public function earningExistsForOrder(Order $order): bool
    {
        return ReferralEarning::where('order_id', $order->id)->exists();
    }

    public function addToPendingBalance(int $userId, int $amountCents): void
    {
        $balance = $this->getOrCreateBalance($userId);
        $balance->increment('pending_balance', $amountCents);
        $balance->update(['updated_at' => now()]);
    }

    public function getEarningsForUser(User $user, array $validated = []): LengthAwarePaginator
    {
        return ReferralEarning::where('referrer_id', $user->id)
            ->with('order:id,uuid')
            ->when($validated['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 15);
    }

    // -------------------------------------------------------------------------
    // Balance
    // -------------------------------------------------------------------------

    public function getOrCreateBalance(int $userId): ReferralBalance
    {
        return ReferralBalance::firstOrCreate(
            ['user_id' => $userId],
            [
                'pending_balance'           => 0,
                'available_balance'         => 0,
                'lifetime_earned'           => 0,
                'lifetime_withdrawn'        => 0,
                'lifetime_spent_on_platform' => 0,
                'updated_at'               => now(),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Activity status
    // -------------------------------------------------------------------------

    public function getOrCreateActivityStatus(User $user): ReferralActivityStatus
    {
        return ReferralActivityStatus::firstOrCreate(
            ['user_id' => $user->id],
            ['is_active' => true, 'new_referrals_last_30d' => 0, 'updated_at' => now()]
        );
    }

    public function markLastOrder(User $user): void
    {
        $status = $this->getOrCreateActivityStatus($user);
        $status->update(['last_order_at' => now(), 'updated_at' => now()]);
    }

    public function refreshActivityStatus(User $user): void
    {
        $settings = SettingService::instance()->get();
        $windowDays   = (int) ($settings->referral_activity_window_days ?? 30);
        $minOrders    = (int) ($settings->referral_activity_min_orders ?? 1);
        $minReferrals = (int) ($settings->referral_activity_min_referrals ?? 2);

        $windowStart = now()->subDays($windowDays);

        $hasRecentOrder = Order::where('user_id', $user->id)
            ->where('status', OrderStatusEnum::DELIVERED)
            ->where('created_at', '>=', $windowStart)
            ->count() >= $minOrders;

        $recentReferrals = ReferralAttribution::where('referrer_id', $user->id)
            ->where('created_at', '>=', $windowStart)
            ->count();

        $isActive = $hasRecentOrder || ($recentReferrals >= $minReferrals);
        $status   = $this->getOrCreateActivityStatus($user);

        $updateData = [
            'is_active'              => $isActive,
            'new_referrals_last_30d' => $recentReferrals,
            'last_check_at'          => now(),
            'updated_at'             => now(),
        ];

        if (!$isActive && $status->is_active) {
            $updateData['became_passive_at'] = now();
        }

        $status->update($updateData);
    }

    public function isReferrerActive(User $user): bool
    {
        $status = ReferralActivityStatus::where('user_id', $user->id)->first();
        return $status ? $status->is_active : true;
    }

    // -------------------------------------------------------------------------
    // Tier
    // -------------------------------------------------------------------------

    public function getTierForUser(User $user): ReferralTierEnum
    {
        $activeCount = $this->getActiveAttributionCountForReferrer($user);
        return ReferralTierEnum::fromActiveCount($activeCount);
    }

    // -------------------------------------------------------------------------
    // Fraud flags
    // -------------------------------------------------------------------------

    public function flagFraud(
        User $user,
        ?User $matchedWith,
        ReferralFraudFlagTypeEnum $type,
        ReferralFraudSeverityEnum $severity
    ): ReferralFraudFlag {
        return ReferralFraudFlag::create([
            'user_id'              => $user->id,
            'matched_with_user_id' => $matchedWith?->id,
            'flag_type'            => $type->value,
            'severity'             => $severity->value,
            'resolved'             => false,
        ]);
    }

    public function hasUnresolvedHardBlock(User $user): bool
    {
        return ReferralFraudFlag::where('user_id', $user->id)
            ->where('severity', ReferralFraudSeverityEnum::HARD_BLOCK)
            ->where('resolved', false)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Payment card fraud (checked at payment time)
    // -------------------------------------------------------------------------

    public function storeUserCard(int $userId, string $fingerprint): void
    {
        \App\Models\Referral\UserPaymentCard::firstOrCreate([
            'user_id'     => $userId,
            'fingerprint' => $fingerprint,
        ]);
    }

    public function userHasCard(int $userId, string $fingerprint): bool
    {
        return \App\Models\Referral\UserPaymentCard::where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->exists();
    }

    /**
     * Move all PENDING earnings the referrer received from a specific referred
     * user into FRAUD_HOLD (admin must review). Removes them from pending_balance.
     */
    public function holdPendingEarningsForReferred(int $referrerId, int $referredId): void
    {
        $earnings = ReferralEarning::where('referrer_id', $referrerId)
            ->where('referred_id', $referredId)
            ->where('status', ReferralEarningStatusEnum::PENDING)
            ->get();

        if ($earnings->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($earnings, $referrerId) {
            $total = (int) $earnings->sum('amount');

            ReferralEarning::whereIn('id', $earnings->pluck('id'))
                ->update(['status' => ReferralEarningStatusEnum::FRAUD_HOLD]);

            $balance = $this->getOrCreateBalance($referrerId);
            $balance->decrement('pending_balance', $total);
            $balance->update(['updated_at' => now()]);
        });
    }

    /**
     * Run all fraud checks when a new attribution is being created.
     * Returns true if a hard block was found (attribution should be blocked).
     */
    public function runFraudChecks(User $referrer, User $newUser, ?string $deviceFingerprint, string $ip): bool
    {
        $hardBlocked = false;

        // Hard block: referrer and referred share the same phone number
        if ($newUser->phone && $referrer->phone === $newUser->phone) {
            $this->flagFraud($newUser, $referrer, ReferralFraudFlagTypeEnum::PHONE, ReferralFraudSeverityEnum::HARD_BLOCK);
            $hardBlocked = true;
        }

        // Hard block: referrer and referred share the same device fingerprint
        if ($deviceFingerprint && $referrer->device_fingerprint === $deviceFingerprint) {
            $this->flagFraud($newUser, $referrer, ReferralFraudFlagTypeEnum::DEVICE, ReferralFraudSeverityEnum::HARD_BLOCK);
            $hardBlocked = true;
        }

        // Soft flag: referrer and referred registered from the same IP
        if ($ip !== '' && $referrer->registration_ip === $ip) {
            $this->flagFraud($newUser, $referrer, ReferralFraudFlagTypeEnum::IP, ReferralFraudSeverityEnum::SOFT_FLAG);
        }

        // Soft flag: velocity — many accounts created from the same IP in the last 24h
        if ($ip !== '') {
            $sameIpRecent = User::where('id', '!=', $newUser->id)
                ->where('registration_ip', $ip)
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            if ($sameIpRecent >= 3) {
                $this->flagFraud($newUser, null, ReferralFraudFlagTypeEnum::VELOCITY, ReferralFraudSeverityEnum::SOFT_FLAG);
            }
        }

        return $hardBlocked;
    }

    // -------------------------------------------------------------------------
    // Withdrawals
    // -------------------------------------------------------------------------

    public function createWithdrawal(User $user, int $amountCents, string $cardLast4, string $cardHolderName): \App\Models\Referral\ReferralWithdrawal
    {
        $settings   = SettingService::instance()->get();
        $feePercent = (float) ($settings->referral_withdrawal_fee_percent ?? 2);
        $feeCents   = (int) round($amountCents * $feePercent / 100);
        $netCents   = $amountCents - $feeCents;

        return DB::transaction(function () use ($user, $amountCents, $feeCents, $netCents, $cardLast4, $cardHolderName) {
            $balance = $this->getOrCreateBalance($user->id);
            $balance->decrement('available_balance', $amountCents);
            $balance->update(['updated_at' => now()]);

            return \App\Models\Referral\ReferralWithdrawal::create([
                'user_id'          => $user->id,
                'amount'           => $amountCents,
                'fee'              => $feeCents,
                'net_amount'       => $netCents,
                'card_last4'       => $cardLast4,
                'card_holder_name' => $cardHolderName,
                'status'           => \App\Enum\Referral\ReferralWithdrawalStatusEnum::PENDING,
                'requested_at'     => now(),
            ]);
        });
    }

    public function restoreWithdrawalBalance(\App\Models\Referral\ReferralWithdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            $balance = $this->getOrCreateBalance($withdrawal->user_id);
            $balance->increment('available_balance', $withdrawal->amount);
            $balance->update(['updated_at' => now()]);

            $withdrawal->update([
                'status'       => \App\Enum\Referral\ReferralWithdrawalStatusEnum::REJECTED,
                'processed_at' => now(),
            ]);
        });
    }

    public function markWithdrawalPaid(\App\Models\Referral\ReferralWithdrawal $withdrawal, int $adminId): void
    {
        DB::transaction(function () use ($withdrawal, $adminId) {
            $balance = $this->getOrCreateBalance($withdrawal->user_id);
            $balance->increment('lifetime_withdrawn', $withdrawal->net_amount);
            $balance->update(['updated_at' => now()]);

            $withdrawal->update([
                'status'       => \App\Enum\Referral\ReferralWithdrawalStatusEnum::PAID,
                'admin_id'     => $adminId,
                'processed_at' => now(),
            ]);
        });
    }

    public function getWithdrawalsForUser(User $user, int $limit = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        return \App\Models\Referral\ReferralWithdrawal::where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate($limit);
    }

    // -------------------------------------------------------------------------
    // Platform balance spending (checkout)
    // -------------------------------------------------------------------------

    public function spendOnPlatform(User $user, int $amountCents, int $orderId): void
    {
        DB::transaction(function () use ($user, $amountCents, $orderId) {
            $balance = $this->getOrCreateBalance($user->id);
            $balance->decrement('available_balance', $amountCents);
            $balance->increment('lifetime_spent_on_platform', $amountCents);
            $balance->update(['updated_at' => now()]);

            \App\Models\Referral\ReferralUsage::create([
                'user_id'     => $user->id,
                'order_id'    => $orderId,
                'amount_used' => $amountCents,
            ]);
        });
    }

    /**
     * Return referral balance that was spent on an order back to the user's
     * available_balance when that order is fully cancelled / refunded.
     * Idempotent: only un-refunded usages are processed.
     */
    public function refundPlatformSpending(int $userId, int $orderId): void
    {
        $usages = \App\Models\Referral\ReferralUsage::where('user_id', $userId)
            ->where('order_id', $orderId)
            ->whereNull('refunded_at')
            ->get();

        if ($usages->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($usages, $userId) {
            $total = (int) $usages->sum('amount_used');

            $balance = $this->getOrCreateBalance($userId);
            $balance->increment('available_balance', $total);
            // Reverse the lifetime spend counter, never below zero
            $newSpent = max(0, (int) $balance->lifetime_spent_on_platform - $total);
            $balance->update([
                'lifetime_spent_on_platform' => $newSpent,
                'updated_at'                 => now(),
            ]);

            \App\Models\Referral\ReferralUsage::whereIn('id', $usages->pluck('id'))
                ->update(['refunded_at' => now()]);
        });
    }

    // -------------------------------------------------------------------------
    // Stats helpers
    // -------------------------------------------------------------------------

    public function getReferralStats(User $user): array
    {
        $activeCount = $this->getActiveAttributionCountForReferrer($user);
        $tier        = ReferralTierEnum::fromActiveCount($activeCount);
        $balance     = $this->getOrCreateBalance($user->id);
        $activity    = $this->getOrCreateActivityStatus($user);

        $totalReferrals = ReferralAttribution::where('referrer_id', $user->id)->count();
        $pendingCount   = ReferralAttribution::where('referrer_id', $user->id)
            ->where('status', ReferralAttributionStatusEnum::PENDING)
            ->count();

        return [
            'tier'               => $tier->value,
            'rate'               => $tier->rate() / 100 . '%',
            'is_active'          => $activity->is_active,
            'total_referrals'    => $totalReferrals,
            'active_referrals'   => $activeCount,
            'pending_referrals'  => $pendingCount,
            'pending_balance'    => round($balance->pending_balance / 100, 2),
            'available_balance'  => round($balance->available_balance / 100, 2),
            'lifetime_earned'    => round($balance->lifetime_earned / 100, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function generateUniqueCode(): string
    {
        $settings = SettingService::instance()->get();
        $length   = max(4, (int) ($settings->referral_code_min_length ?? 6));

        do {
            $code = strtoupper(Str::random($length));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }
}
