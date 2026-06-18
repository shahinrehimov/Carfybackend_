<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Referral\ReferralBalance;
use App\Models\Referral\ReferralUsage;
use App\Models\User;
use App\Repositories\ReferralRepository;
use App\Services\ReferralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the referral-balance side of order item cancellation: when a buyer paid
 * (partly) with referral balance, cancelling an item must return that item's
 * proportional share of the spent balance, without ever over-returning.
 */
class ReferralBalanceRefundTest extends TestCase
{
    private ReferralRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // The full migration suite cannot run on an empty DB (an unrelated migration
        // requires seeded cities), so build just the tables these focused balance
        // tests need. order_id on referral_usages is left unconstrained here — we
        // exercise the balance ledger in isolation, not the full order graph.
        $this->buildSchema();

        $this->repo = ReferralService::instance()->repository();
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('referral_usages');
        Schema::dropIfExists('referral_balances');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            // User uses the ActionBy/ActionUser traits, which stamp these on create.
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
        });

        Schema::create('referral_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedInteger('pending_balance')->default(0);
            $table->unsignedInteger('available_balance')->default(0);
            $table->unsignedInteger('lifetime_earned')->default(0);
            $table->unsignedInteger('lifetime_withdrawn')->default(0);
            $table->unsignedInteger('lifetime_spent_on_platform')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('referral_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('amount_used');
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Put a user into a realistic post-checkout state: balance spent on an order.
     */
    private function createUser(): User
    {
        return User::create([
            'name'     => 'Tester',
            'email'    => 'tester' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
    }

    private function userWhoSpent(int $amountCents, int $orderId = 1): User
    {
        $user = $this->createUser();

        // Seed enough available balance, then spend it the way checkout does.
        $balance = $this->repo->getOrCreateBalance($user->id);
        $balance->update(['available_balance' => $amountCents]);

        $this->repo->spendOnPlatform($user, $amountCents, $orderId);

        return $user;
    }

    public function test_spend_on_platform_sets_up_expected_ledger_state(): void
    {
        $user = $this->userWhoSpent(2000);

        $balance = ReferralBalance::find($user->id);
        $this->assertSame(0, (int) $balance->available_balance);
        $this->assertSame(2000, (int) $balance->lifetime_spent_on_platform);

        $usage = ReferralUsage::where('order_id', 1)->first();
        $this->assertSame(2000, (int) $usage->amount_used);
        $this->assertSame(0, (int) $usage->refunded_amount);
        $this->assertNull($usage->refunded_at);
    }

    public function test_partial_refund_returns_requested_amount_and_tracks_remainder(): void
    {
        $user = $this->userWhoSpent(2000);

        $credited = $this->repo->refundPlatformSpendingPartial($user->id, 1, 1000);

        $this->assertSame(1000, $credited);

        $balance = ReferralBalance::find($user->id);
        $this->assertSame(1000, (int) $balance->available_balance);
        $this->assertSame(1000, (int) $balance->lifetime_spent_on_platform);

        $usage = ReferralUsage::where('order_id', 1)->first();
        $this->assertSame(1000, (int) $usage->refunded_amount);
        // Not fully returned yet, so the usage stays open for any later full refund.
        $this->assertNull($usage->refunded_at);
    }

    public function test_successive_partial_refunds_consume_exactly_the_spent_balance(): void
    {
        $user = $this->userWhoSpent(2000);

        $this->assertSame(1000, $this->repo->refundPlatformSpendingPartial($user->id, 1, 1000));
        $this->assertSame(1000, $this->repo->refundPlatformSpendingPartial($user->id, 1, 1000));
        // Nothing left — further requests credit nothing.
        $this->assertSame(0, $this->repo->refundPlatformSpendingPartial($user->id, 1, 500));

        $balance = ReferralBalance::find($user->id);
        $this->assertSame(2000, (int) $balance->available_balance);
        $this->assertSame(0, (int) $balance->lifetime_spent_on_platform);

        $usage = ReferralUsage::where('order_id', 1)->first();
        $this->assertSame(2000, (int) $usage->refunded_amount);
        $this->assertNotNull($usage->refunded_at);
    }

    public function test_partial_refund_never_returns_more_than_remaining(): void
    {
        $user = $this->userWhoSpent(2000);

        // Request far more than was spent — capped at the spent amount.
        $credited = $this->repo->refundPlatformSpendingPartial($user->id, 1, 9999);

        $this->assertSame(2000, $credited);
        $this->assertSame(2000, (int) ReferralBalance::find($user->id)->available_balance);
    }

    public function test_full_refund_returns_only_the_remainder_after_partials(): void
    {
        $user = $this->userWhoSpent(2000);

        // An item cancellation already returned part of the balance.
        $this->repo->refundPlatformSpendingPartial($user->id, 1, 800);

        // Cancelling the whole order returns just the rest — never double-returns.
        $this->repo->refundPlatformSpending($user->id, 1);

        $balance = ReferralBalance::find($user->id);
        $this->assertSame(2000, (int) $balance->available_balance);
        $this->assertSame(0, (int) $balance->lifetime_spent_on_platform);

        $usage = ReferralUsage::where('order_id', 1)->first();
        $this->assertSame(2000, (int) $usage->refunded_amount);
        $this->assertNotNull($usage->refunded_at);
    }

    public function test_full_refund_is_idempotent_after_being_fully_returned(): void
    {
        $user = $this->userWhoSpent(2000);

        $this->repo->refundPlatformSpending($user->id, 1);
        // A second full refund must not credit the balance again.
        $this->repo->refundPlatformSpending($user->id, 1);

        $this->assertSame(2000, (int) ReferralBalance::find($user->id)->available_balance);
    }

    public function test_handle_item_balance_refund_returns_items_proportional_share(): void
    {
        // Two items of 50.00 each (subtotal 100.00), 20.00 paid from balance.
        $user = $this->userWhoSpent(2000);

        $order = new Order();
        $order->id = 1;
        $order->user_id = $user->id;

        // itemAmount 5000, productSubtotal 10000 → share = 5000 * 2000 / 10000 = 1000.
        $credited = ReferralService::instance()->handleItemBalanceRefund($order, 5000, 10000);

        $this->assertSame(1000, $credited);
        $this->assertSame(1000, (int) ReferralBalance::find($user->id)->available_balance);
    }

    public function test_handle_item_balance_refund_is_noop_when_no_balance_was_spent(): void
    {
        $user = $this->createUser();
        $this->repo->getOrCreateBalance($user->id);

        $order = new Order();
        $order->id = 99;
        $order->user_id = $user->id;

        $credited = ReferralService::instance()->handleItemBalanceRefund($order, 5000, 10000);

        $this->assertSame(0, $credited);
        $this->assertSame(0, (int) ReferralBalance::find($user->id)->available_balance);
    }
}
