<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Repositories\ReferralRepository;
use App\Services\SettingService;

class ReferralService
{
    private static ?ReferralService $instance = null;
    private ReferralRepository $repo;

    private function __construct()
    {
        $this->repo = new ReferralRepository();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function repository(): ReferralRepository
    {
        return $this->repo;
    }

    /**
     * Called during user registration when a referral code is provided.
     * Creates attribution (pending) and generates the new user's own code.
     */
    public function handleRegistration(User $newUser, ?string $refCode, ?string $deviceFingerprint = null): void
    {
        $ip = request()->ip() ?? '';

        // Persist fraud-detection signals on the user for later matching
        $fraudData = [];
        if ($ip !== '') {
            $fraudData['registration_ip'] = $ip;
        }
        if ($deviceFingerprint) {
            $fraudData['device_fingerprint'] = $deviceFingerprint;
        }
        if ($fraudData) {
            $newUser->forceFill($fraudData)->save();
        }

        // Always generate a code for every new user
        $this->repo->generateCodeForUser($newUser);

        if (!$refCode) {
            return;
        }

        $referralCode = $this->repo->findActiveCode($refCode);
        if (!$referralCode) {
            return;
        }

        $referrer = $referralCode->user;

        // Same person check
        if ($referrer->id === $newUser->id) {
            return;
        }

        // Already has an attribution
        if ($this->repo->getAttributionForReferred($newUser)) {
            return;
        }

        // Fraud checks — if hard block, do not create attribution
        $hardBlocked = $this->repo->runFraudChecks($referrer, $newUser, $deviceFingerprint, $ip);
        if ($hardBlocked) {
            return;
        }

        // Hard block check for referrer (pre-existing flags)
        if ($this->repo->hasUnresolvedHardBlock($referrer)) {
            return;
        }

        $this->repo->createAttribution($referrer, $newUser);
    }

    /**
     * Called when an order is marked as DELIVERED.
     * Handles: attribution activation, perks counter, earning creation.
     */
    public function handleOrderDelivered(Order $order): void
    {
        $user = $order->user;
        if (!$user) {
            return;
        }

        // Update referrer activity tracking
        $this->repo->markLastOrder($user);

        $attribution = $this->repo->getAttributionForReferred($user);
        if (!$attribution) {
            return;
        }

        $settings             = SettingService::instance()->get();
        $activationThreshold  = (int) ($settings->referral_activation_threshold ?? 2000); // cents
        $orderSubtotal        = $order->getRawOriginal('amount'); // stored in cents

        // Activate pending attribution if threshold met
        if ($attribution->isPending()) {
            if ($orderSubtotal < $activationThreshold) {
                return;
            }
            $this->repo->activateAttribution($attribution, $order);
        }

        // From here attribution is active — create earning for referrer
        $referrer = $attribution->referrer;
        if (!$referrer) {
            return;
        }

        if (!$this->repo->isReferrerActive($referrer)) {
            return;
        }

        // Guard against duplicate earnings if the order is marked delivered more than once
        if ($this->repo->earningExistsForOrder($order)) {
            return;
        }

        $tier = $this->repo->getTierForUser($referrer);

        // If referrer has an unresolved hard block, hold the earning for admin review
        if ($this->repo->hasUnresolvedHardBlock($referrer)) {
            $this->repo->createFraudHoldEarning($referrer, $user, $order, $tier, $orderSubtotal);
            return;
        }

        $earning = $this->repo->createEarning($referrer, $user, $order, $tier, $orderSubtotal);
        $this->repo->addToPendingBalance($referrer->id, $earning->amount);
    }

    /**
     * Called when an order is fully cancelled/refunded (still in pending window).
     */
    public function handleOrderCancelled(Order $order): void
    {
        // Cancel any pending referrer earning for this order
        $earning = \App\Models\Referral\ReferralEarning::where('order_id', $order->id)
            ->where('status', \App\Enum\Referral\ReferralEarningStatusEnum::PENDING)
            ->first();

        if ($earning) {
            $this->repo->cancelEarning($earning);
        }

        // Release any referred-user perks consumed by this order (plan §3:
        // cancelled orders must not count toward the 3-order limit)
        $user = $order->user;
        if ($user) {
            if ($order->referral_free_delivery_applied) {
                $this->repo->releaseFreeDeliveryPerk($user);
            }
            if ($order->referral_discount_applied) {
                $this->repo->releaseDiscountPerk($user);
            }

            // Return any referral balance spent on this order back to available_balance
            $this->repo->refundPlatformSpending($user->id, $order->id);
        }
    }

    /**
     * Called on partial refund.
     */
    public function handlePartialRefund(Order $order, int $refundedAmountCents): void
    {
        $earning = \App\Models\Referral\ReferralEarning::where('order_id', $order->id)
            ->where('status', \App\Enum\Referral\ReferralEarningStatusEnum::PENDING)
            ->first();

        if ($earning) {
            $this->repo->partialRefundEarning($earning, $refundedAmountCents);
        }
    }

    /**
     * Called after a successful card payment. Stores the card fingerprint for the
     * buyer and runs the card-based fraud hard-block check (plan §8): if the
     * referrer of this buyer has paid with the same card, flag a hard block and
     * move the referrer's pending earnings from this buyer into fraud_hold.
     *
     * Gateway metadata shapes differ; the fingerprint is extracted best-effort
     * from common masked-PAN keys. When no card data is present (e.g. cash), it
     * silently no-ops.
     */
    public function handlePaymentCard(Order $order, ?array $metadata): void
    {
        $fingerprint = $this->extractCardFingerprint($metadata);
        if (!$fingerprint) {
            return;
        }

        $user = $order->user;
        if (!$user) {
            return;
        }

        // Remember this card for the buyer
        $this->repo->storeUserCard($user->id, $fingerprint);

        $attribution = $this->repo->getAttributionForReferred($user);
        if (!$attribution || $attribution->status === \App\Enum\Referral\ReferralAttributionStatusEnum::FRAUD_BLOCKED) {
            return;
        }

        $referrer = $attribution->referrer;
        if (!$referrer || $referrer->id === $user->id) {
            return;
        }

        // Hard block: referrer and referred used the same payment card
        if ($this->repo->userHasCard($referrer->id, $fingerprint)) {
            // Avoid duplicate flags for the same pair/type
            if (!$this->repo->hasUnresolvedHardBlock($user)) {
                $this->repo->flagFraud(
                    $user,
                    $referrer,
                    \App\Enum\Referral\ReferralFraudFlagTypeEnum::CARD,
                    \App\Enum\Referral\ReferralFraudSeverityEnum::HARD_BLOCK
                );
            }

            // Hold any pending earnings the referrer received from this buyer
            $this->repo->holdPendingEarningsForReferred($referrer->id, $user->id);
        }
    }

    /**
     * Best-effort extraction of a card fingerprint from gateway metadata.
     * Looks for a masked PAN under common keys (recursively), normalizes it to
     * BIN + last4 and returns a hash. Returns null when no card data is found.
     */
    private function extractCardFingerprint(?array $metadata): ?string
    {
        if (!$metadata) {
            return null;
        }

        $maskedPan = $this->findMaskedPan($metadata);
        if (!$maskedPan) {
            return null;
        }

        // Keep only digits and mask characters, require at least BIN(6)+last4(4)
        $normalized = preg_replace('/[^0-9*xX#.]/', '', $maskedPan);
        $digits     = preg_replace('/\D/', '', $normalized);

        if (strlen($digits) < 8) {
            return null;
        }

        $bin   = substr($digits, 0, 6);
        $last4 = substr($digits, -4);

        return hash('sha256', $bin . ':' . $last4);
    }

    private function findMaskedPan(array $data): ?string
    {
        $keys = ['pan', 'maskedpan', 'cardnumber', 'card_number', 'cardmask', 'card_mask', 'maskedcardnumber'];

        foreach ($data as $key => $value) {
            if (is_string($value) && in_array(strtolower((string) $key), $keys, true)) {
                $candidate = trim($value);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            if (is_array($value)) {
                $found = $this->findMaskedPan($value);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Returns perks state for the given user (as referred).
     */
    public function getPerks(User $user): array
    {
        // Only users with an attribution have perks
        $attribution = $this->repo->getAttributionForReferred($user);
        if (!$attribution) {
            return [
                'has_perks'                  => false,
                'free_delivery_remaining'    => 0,
                'discount_orders_remaining'  => 0,
                'discount_rate'              => '1%',
            ];
        }

        $perk = $this->repo->getOrCreatePerks($user);
        return [
            'has_perks'                  => $perk->hasFreeDelivery() || $perk->hasDiscount(),
            'free_delivery_remaining'    => $perk->remainingFreeDeliveries(),
            'discount_orders_remaining'  => $perk->remainingDiscountOrders(),
            'discount_rate'              => '1%',
        ];
    }
}
