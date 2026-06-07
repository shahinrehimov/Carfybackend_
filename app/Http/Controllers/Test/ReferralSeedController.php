<?php

namespace App\Http\Controllers\Test;

use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\Referral\ReferralAttributionStatusEnum;
use App\Enum\Referral\ReferralEarningStatusEnum;
use App\Enum\Referral\ReferralTierEnum;
use App\Enum\Referral\ReferralWithdrawalStatusEnum;
use App\Enum\UserRegisterTypesEnum;
use App\Http\Controllers\Controller;
use App\Models\AddressType;
use App\Models\City;
use App\Models\Referral\ReferralActivityStatus;
use App\Models\Referral\ReferralAttribution;
use App\Models\Referral\ReferralBalance;
use App\Models\Referral\ReferralCode;
use App\Models\Referral\ReferralEarning;
use App\Models\Referral\ReferralWithdrawal;
use App\Models\Referral\ReferredUserPerk;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test-only endpoint that injects mock referral/bonus data for a given user
 * so the frontend can exercise every panel without running the real order flow.
 *
 * Guarded by env: REFERRAL_TEST_SEED_ENABLED=true + REFERRAL_TEST_SEED_KEY must
 * match the ?key= query param. Not tied to the app environment on purpose.
 *
 * Re-running for the same email is idempotent: previously seeded fake users and
 * the target user's referral rows are wiped and rebuilt.
 */
class ReferralSeedController extends Controller
{
    /** Earning statuses we want represented at least once in the referrer panel. */
    private const EARNING_STATUSES = [
        ReferralEarningStatusEnum::PENDING,
        ReferralEarningStatusEnum::AVAILABLE,
        ReferralEarningStatusEnum::REFUNDED,
        ReferralEarningStatusEnum::CANCELLED,
        ReferralEarningStatusEnum::FRAUD_HOLD,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $config = config('services.referral_test_seed');

        if (empty($config['enabled'])) {
            return response()->json(['message' => 'Referral test seeder is not active.'], 403);
        }

        if (empty($config['key']) || $request->query('key') !== $config['key']) {
            return response()->json(['message' => 'Invalid seeder key.'], 403);
        }

        $email = (string) $request->query('email');
        if ($email === '') {
            return response()->json(['message' => 'email query param is required.'], 422);
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => "No user found with email {$email}."], 404);
        }

        // Active attribution count drives the tier (bronze/silver/gold/platinum).
        $activeCount = (int) $request->query('active', 3);
        $activeCount = max(1, min(40, $activeCount));

        $addressTypeId = AddressType::query()->value('id');
        $cityId = City::query()->value('id');
        if (!$addressTypeId || !$cityId) {
            return response()->json([
                'message' => 'At least one AddressType and one City must exist to seed mock orders.',
            ], 422);
        }

        $summary = DB::transaction(function () use ($user, $activeCount, $addressTypeId, $cityId) {
            $this->cleanup($user);

            $tier = ReferralTierEnum::fromActiveCount($activeCount);

            $referrerData = $this->seedAsReferrer($user, $activeCount, $tier, $addressTypeId, $cityId);
            $referredData = $this->seedAsReferred($user);

            return array_merge($referrerData, $referredData, [
                'tier'         => $tier->value,
                'rate'         => $tier->rate() / 100 . '%',
                'active_count' => $activeCount,
            ]);
        });

        return response()->json([
            'message' => "Mock referral data seeded for {$email}.",
            'user_id' => $user->id,
            'data'    => $summary,
        ]);
    }

    /**
     * Wipe previously seeded fake users (cascades their orders/earnings/attributions)
     * and reset the target user's own referral rows so re-runs stay clean.
     */
    private function cleanup(User $user): void
    {
        // Previously seeded fake users (withTrashed: the ActionBy trait soft-marks
        // on delete, so they may linger). Hard-delete via the query builder to skip
        // model events — the ActionBy `deleted` hook re-saves the row otherwise.
        $fakeIds = User::withTrashed()
            ->where('email', 'like', $this->fakeEmailPrefix($user) . '%')
            ->pluck('id')
            ->all();

        if (!empty($fakeIds)) {
            ReferralEarning::query()
                ->whereIn('referrer_id', $fakeIds)
                ->orWhereIn('referred_id', $fakeIds)
                ->delete();

            ReferralAttribution::query()
                ->whereIn('referrer_id', $fakeIds)
                ->orWhereIn('referred_id', $fakeIds)
                ->delete();

            DB::table('orders')->whereIn('user_id', $fakeIds)->delete();
            DB::table('users')->whereIn('id', $fakeIds)->delete();
        }

        ReferralEarning::query()
            ->where('referrer_id', $user->id)
            ->orWhere('referred_id', $user->id)
            ->delete();

        ReferralAttribution::query()
            ->where('referrer_id', $user->id)
            ->orWhere('referred_id', $user->id)
            ->delete();

        ReferralWithdrawal::query()->where('user_id', $user->id)->delete();
        ReferralBalance::query()->where('user_id', $user->id)->delete();
        ReferredUserPerk::query()->where('user_id', $user->id)->delete();
        ReferralActivityStatus::query()->where('user_id', $user->id)->delete();
        ReferralCode::query()->where('user_id', $user->id)->delete();
    }

    /**
     * The target user as a referrer: code, balance, referrals, earnings,
     * activity status and withdrawals.
     */
    private function seedAsReferrer(
        User $user,
        int $activeCount,
        ReferralTierEnum $tier,
        int $addressTypeId,
        int $cityId
    ): array {
        // Referral code (regenerated by cleanup, so always create fresh).
        ReferralCode::create([
            'user_id'   => $user->id,
            'code'      => $this->uniqueCode($user),
            'is_custom' => false,
            'is_active' => true,
        ]);

        // Active referrals: one fake referred user + delivered order each.
        $actives = [];
        for ($i = 1; $i <= $activeCount; $i++) {
            $fake = $this->makeFakeUser($user, "active-{$i}", 'Aktiv', "Referral{$i}");
            $orderId = $this->makeMockOrder($fake->id, $addressTypeId, $cityId, 2500);

            ReferralAttribution::create([
                'referrer_id'               => $user->id,
                'referred_id'               => $fake->id,
                'status'                    => ReferralAttributionStatusEnum::ACTIVE,
                'first_qualifying_order_id' => $orderId,
                'activated_at'              => now()->subDays($i),
            ]);

            $actives[] = ['user' => $fake, 'order_id' => $orderId];
        }

        // Earnings across all statuses; cycle fakes when activeCount < statuses.
        $earningCount = max($activeCount, count(self::EARNING_STATUSES));
        for ($i = 0; $i < $earningCount; $i++) {
            $target = $actives[$i % $activeCount];
            $status = self::EARNING_STATUSES[$i % count(self::EARNING_STATUSES)];
            $this->makeEarning($user, $target['user'], $target['order_id'], $tier, $status);
        }

        // One pending and one fraud-blocked attribution (no earnings).
        $pendingFake = $this->makeFakeUser($user, 'pending-1', 'Gözləyən', 'Referral');
        ReferralAttribution::create([
            'referrer_id' => $user->id,
            'referred_id' => $pendingFake->id,
            'status'      => ReferralAttributionStatusEnum::PENDING,
        ]);

        $blockedFake = $this->makeFakeUser($user, 'blocked-1', 'Bloklu', 'Referral');
        ReferralAttribution::create([
            'referrer_id' => $user->id,
            'referred_id' => $blockedFake->id,
            'status'      => ReferralAttributionStatusEnum::FRAUD_BLOCKED,
        ]);

        // Balance (cents). Illustrative values covering every field.
        ReferralBalance::create([
            'user_id'                    => $user->id,
            'pending_balance'            => 150,
            'available_balance'          => 2500,
            'lifetime_earned'            => 4000,
            'lifetime_withdrawn'         => 600,
            'lifetime_spent_on_platform' => 70,
            'updated_at'                 => now(),
        ]);

        ReferralActivityStatus::create([
            'user_id'                => $user->id,
            'is_active'              => true,
            'last_order_at'          => now()->subDays(2),
            'new_referrals_last_30d' => $activeCount,
            'last_check_at'          => now(),
            'updated_at'             => now(),
        ]);

        // Withdrawals: one pending, one paid.
        ReferralWithdrawal::create([
            'user_id'          => $user->id,
            'amount'           => 2000,
            'fee'              => 40,
            'net_amount'       => 1960,
            'card_last4'       => '1234',
            'card_holder_name' => 'TEST USER',
            'status'           => ReferralWithdrawalStatusEnum::PENDING,
            'requested_at'     => now()->subDay(),
        ]);

        ReferralWithdrawal::create([
            'user_id'          => $user->id,
            'amount'           => 1000,
            'fee'              => 20,
            'net_amount'       => 980,
            'card_last4'       => '5678',
            'card_holder_name' => 'TEST USER',
            'status'           => ReferralWithdrawalStatusEnum::PAID,
            'requested_at'     => now()->subDays(10),
            'processed_at'     => now()->subDays(8),
        ]);

        return [
            'code'         => ReferralCode::query()->where('user_id', $user->id)->value('code'),
            'referrals'    => $activeCount + 2, // active + pending + blocked
            'earnings'     => $earningCount,
            'withdrawals'  => 2,
        ];
    }

    /**
     * The target user as a referred user: invited by a fake referrer, with perks.
     */
    private function seedAsReferred(User $user): array
    {
        $referrer = $this->makeFakeUser($user, 'referrer', 'Dəvət', 'Edən');

        ReferralAttribution::create([
            'referrer_id'  => $referrer->id,
            'referred_id'  => $user->id,
            'status'       => ReferralAttributionStatusEnum::ACTIVE,
            'activated_at' => now()->subDays(5),
        ]);

        ReferredUserPerk::create([
            'user_id'                  => $user->id,
            'free_delivery_used_count' => 1, // 2 remaining
            'discount_orders_count'    => 0, // 3 remaining
            'updated_at'               => now(),
        ]);

        return [
            'invited_by'                => $referrer->full_name,
            'free_delivery_remaining'   => 2,
            'discount_orders_remaining' => 3,
        ];
    }

    private function makeEarning(
        User $referrer,
        User $referred,
        int $orderId,
        ReferralTierEnum $tier,
        ReferralEarningStatusEnum $status
    ): void {
        $orderAmount = 2500;
        $rate = $tier->rate();
        $amount = (int) round($orderAmount * $rate / 10000);

        $payload = [
            'referrer_id'  => $referrer->id,
            'referred_id'  => $referred->id,
            'order_id'     => $orderId,
            'tier_at_time' => $tier->value,
            'rate'         => $rate,
            'order_amount' => $orderAmount,
            'amount'       => $amount,
            'status'       => $status,
        ];

        $payload += match ($status) {
            ReferralEarningStatusEnum::PENDING => [
                'delivered_at' => now()->subDays(2),
                'available_at' => now()->addDays(12),
            ],
            ReferralEarningStatusEnum::AVAILABLE => [
                'delivered_at' => now()->subDays(20),
                'available_at' => now()->subDays(6),
                'credited_at'  => now()->subDays(6),
            ],
            ReferralEarningStatusEnum::REFUNDED => [
                'delivered_at' => now()->subDays(30),
                'available_at' => now()->subDays(16),
                'credited_at'  => now()->subDays(16),
                'refunded_at'  => now()->subDays(5),
            ],
            ReferralEarningStatusEnum::CANCELLED => [
                'delivered_at' => now()->subDays(10),
                'available_at' => now()->addDays(4),
            ],
            ReferralEarningStatusEnum::FRAUD_HOLD => [
                'delivered_at' => now()->subDays(3),
                'available_at' => now()->addDays(11),
            ],
        };

        ReferralEarning::create($payload);
    }

    /**
     * Create a throwaway user identifiable by the seed-owner-scoped email prefix.
     */
    private function makeFakeUser(User $owner, string $slug, string $name, string $surname): User
    {
        $uuid = (string) Str::uuid();

        return User::create([
            'uuid'              => $uuid,
            'register_type'     => UserRegisterTypesEnum::EMAIL->value,
            'name'              => $name,
            'surname'           => $surname,
            'email'             => $this->fakeEmailPrefix($owner) . $slug . '@carfytest.local',
            'email_unique_key'  => md5($uuid),
            'phone_unique_key'  => md5($uuid),
            'password'          => Hash::make('password'),
            'is_active'         => true,
        ]);
    }

    /**
     * Minimal order row via raw insert: bypasses OrderObserver (so no real
     * referral hooks fire) and AmountCast (raw cents stored directly).
     */
    private function makeMockOrder(int $userId, int $addressTypeId, int $cityId, int $amountCents): int
    {
        return (int) DB::table('orders')->insertGetId([
            'uuid'                     => (string) Str::uuid(),
            'user_id'                  => $userId,
            'status'                   => OrderStatusEnum::DELIVERED->value,
            'payment_status'           => OrderPaymentStatusEnum::PAID->value,
            'payment_method'           => 'kapitalbank',
            'amount'                   => $amountCents,
            'amount_final'             => $amountCents,
            'total_discount_in_basket' => 0,
            'shipping'                 => 0,
            'total_quantity'           => 1,
            'address_type_id'          => $addressTypeId,
            'city_id'                  => $cityId,
            'address_line_1'           => 'Test address',
            'latitude'                 => '40.4093',
            'longitude'                => '49.8671',
            'phone'                    => '994500000000',
            'is_deleted'               => 'false',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    private function fakeEmailPrefix(User $owner): string
    {
        return "refseed-{$owner->id}-";
    }

    private function uniqueCode(User $user): string
    {
        do {
            $code = 'TEST' . strtoupper(Str::random(4));
        } while (ReferralCode::query()->where('code', $code)->exists());

        return $code;
    }
}
