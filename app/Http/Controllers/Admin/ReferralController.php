<?php

namespace App\Http\Controllers\Admin;

use App\Enum\Referral\ReferralAttributionStatusEnum;
use App\Enum\Referral\ReferralEarningStatusEnum;
use App\Enum\Referral\ReferralWithdrawalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\GeneralResource;
use App\Models\Referral\ReferralAttribution;
use App\Models\Referral\ReferralCode;
use App\Models\Referral\ReferralEarning;
use App\Models\Referral\ReferralFraudFlag;
use App\Models\Referral\ReferralWithdrawal;
use App\Models\User;
use App\Repositories\ReferralRepository;
use App\Services\ReferralService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    private ReferralRepository $repo;

    public function __construct()
    {
        $this->repo = ReferralService::instance()->repository();
    }

    // -------------------------------------------------------------------------
    // Overview
    // -------------------------------------------------------------------------

    public function overview(): JsonResponse
    {
        $totalReferrers    = ReferralAttribution::distinct()->count('referrer_id');
        $activeReferrers   = ReferralAttribution::where('status', ReferralAttributionStatusEnum::ACTIVE)
            ->distinct()->count('referrer_id');
        $totalAttributions = ReferralAttribution::count();
        $activeAttr        = ReferralAttribution::where('status', ReferralAttributionStatusEnum::ACTIVE)->count();

        $pendingEarnings   = ReferralEarning::where('status', ReferralEarningStatusEnum::PENDING)->sum('amount');
        $availableEarnings = ReferralEarning::where('status', ReferralEarningStatusEnum::AVAILABLE)->sum('amount');
        $totalPaid         = ReferralWithdrawal::where('status', ReferralWithdrawalStatusEnum::PAID)->sum('net_amount');

        $pendingWithdrawals = ReferralWithdrawal::where('status', ReferralWithdrawalStatusEnum::PENDING)->count();
        $unresolvedFlags    = ReferralFraudFlag::where('resolved', false)->count();

        return GeneralResource::make([
            'total_referrers'       => $totalReferrers,
            'active_referrers'      => $activeReferrers,
            'total_attributions'    => $totalAttributions,
            'active_attributions'   => $activeAttr,
            'pending_earnings_azn'  => round($pendingEarnings / 100, 2),
            'available_earnings_azn' => round($availableEarnings / 100, 2),
            'total_paid_azn'        => round($totalPaid / 100, 2),
            'pending_withdrawals'   => $pendingWithdrawals,
            'unresolved_fraud_flags' => $unresolvedFlags,
        ])->response();
    }

    // -------------------------------------------------------------------------
    // Withdrawals
    // -------------------------------------------------------------------------

    public function withdrawalIndex(Request $request): JsonResponse
    {
        $withdrawals = ReferralWithdrawal::with('user:id,name,surname,email,phone')
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate($request->integer('limit', 20));

        return response()->json($withdrawals);
    }

    public function withdrawalApprove(ReferralWithdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->status !== ReferralWithdrawalStatusEnum::PENDING) {
            return response()->json(['message' => 'Withdrawal is not in pending status'], 422);
        }

        $withdrawal->update([
            'status'   => ReferralWithdrawalStatusEnum::APPROVED,
            'admin_id' => auth()->id(),
        ]);

        return GeneralResource::make(['status' => $withdrawal->status])->response();
    }

    public function withdrawalReject(Request $request, ReferralWithdrawal $withdrawal): JsonResponse
    {
        if (!in_array($withdrawal->status, [ReferralWithdrawalStatusEnum::PENDING, ReferralWithdrawalStatusEnum::APPROVED])) {
            return response()->json(['message' => 'Withdrawal cannot be rejected in current status'], 422);
        }

        $this->repo->restoreWithdrawalBalance($withdrawal);

        $withdrawal->refresh()->update([
            'admin_note' => $request->input('note'),
            'admin_id'   => auth()->id(),
        ]);

        return GeneralResource::make(['status' => $withdrawal->status])->response();
    }

    public function withdrawalMarkPaid(ReferralWithdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->status !== ReferralWithdrawalStatusEnum::APPROVED) {
            return response()->json(['message' => 'Withdrawal must be approved before marking as paid'], 422);
        }

        $this->repo->markWithdrawalPaid($withdrawal, auth()->id());

        return GeneralResource::make(['status' => $withdrawal->status])->response();
    }

    // -------------------------------------------------------------------------
    // Fraud flags
    // -------------------------------------------------------------------------

    public function fraudFlagIndex(Request $request): JsonResponse
    {
        $flags = ReferralFraudFlag::with([
            'user:id,name,surname,email,phone',
            'matchedWithUser:id,name,surname,email',
        ])
            ->when($request->boolean('unresolved_only', true), fn($q) => $q->where('resolved', false))
            ->when($request->input('severity'), fn($q, $s) => $q->where('severity', $s))
            ->orderByDesc('id')
            ->paginate($request->integer('limit', 20));

        return response()->json($flags);
    }

    public function fraudFlagResolve(Request $request, ReferralFraudFlag $flag): JsonResponse
    {
        $request->validate([
            'resolution' => ['required', 'in:approved,rejected'],
        ]);

        $resolution = $request->input('resolution');

        DB::transaction(function () use ($flag, $resolution, $request) {
            $flag->update([
                'resolved'    => true,
                'resolution'  => $resolution,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            // The flag is on the referred user ($flag->user_id); the referrer is
            // $flag->matched_with_user_id. Held earnings belong to the referrer,
            // earned from this referred user.
            $referredId = $flag->user_id;
            $referrerId = $flag->matched_with_user_id;

            // Soft flags (no matched referrer) carry no held earnings to act on
            if (!$referrerId) {
                return;
            }

            $heldEarnings = ReferralEarning::where('referrer_id', $referrerId)
                ->where('referred_id', $referredId)
                ->where('status', ReferralEarningStatusEnum::FRAUD_HOLD);

            if ($resolution === 'approved') {
                // Release held earnings to the referrer's available balance
                $heldEarnings->get()->each(fn($e) => $this->repo->releaseFraudHoldEarning($e));
            } else {
                // Confirmed fraud: cancel held earnings and block the attribution
                $heldEarnings->update(['status' => ReferralEarningStatusEnum::CANCELLED]);

                ReferralAttribution::where('referred_id', $referredId)
                    ->whereIn('status', [ReferralAttributionStatusEnum::PENDING, ReferralAttributionStatusEnum::ACTIVE])
                    ->update(['status' => ReferralAttributionStatusEnum::FRAUD_BLOCKED]);
            }
        });

        return GeneralResource::make(['resolved' => true, 'resolution' => $resolution])->response();
    }

    // -------------------------------------------------------------------------
    // Balance adjust (manual)
    // -------------------------------------------------------------------------

    public function balanceAdjust(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'type'       => ['required', 'in:add,deduct'],
            'amount_azn' => ['required', 'numeric', 'min:0.01'],
            'reason'     => ['required', 'string', 'max:500'],
        ]);

        $amountCents = (int) round($request->input('amount_azn') * 100);
        $balance     = $this->repo->getOrCreateBalance($request->integer('user_id'));

        DB::transaction(function () use ($balance, $request, $amountCents) {
            if ($request->input('type') === 'add') {
                $balance->increment('available_balance', $amountCents);
                $balance->increment('lifetime_earned', $amountCents);
            } else {
                if ($balance->available_balance < $amountCents) {
                    throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Insufficient balance');
                }
                $balance->decrement('available_balance', $amountCents);
            }
            $balance->update(['updated_at' => now()]);
        });

        return GeneralResource::make([
            'available_balance' => round($balance->fresh()->available_balance / 100, 2),
        ])->response();
    }

    // -------------------------------------------------------------------------
    // User detail
    // -------------------------------------------------------------------------

    public function userDetail(User $user): JsonResponse
    {
        $stats       = $this->repo->getReferralStats($user);
        $code        = $this->repo->getUserActiveCode($user);
        $attribution = $this->repo->getAttributionForReferred($user);
        $perk        = $this->repo->getOrCreatePerks($user);

        return GeneralResource::make([
            'stats'       => $stats,
            'code'        => $code?->code,
            'attribution' => $attribution ? [
                'referrer_id' => $attribution->referrer_id,
                'status'      => $attribution->status,
                'activated_at' => $attribution->activated_at?->toISOString(),
            ] : null,
            'perks' => [
                'free_delivery_remaining'   => $perk->remainingFreeDeliveries(),
                'discount_orders_remaining' => $perk->remainingDiscountOrders(),
            ],
        ])->response();
    }

    // -------------------------------------------------------------------------
    // Code management
    // -------------------------------------------------------------------------

    public function codeIndex(Request $request): JsonResponse
    {
        $codes = ReferralCode::with('user:id,name,surname,email')
            ->when($request->input('search'), fn($q, $s) => $q->where('code', 'ILIKE', '%' . $s . '%'))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('id')
            ->paginate($request->integer('limit', 20));

        return response()->json($codes);
    }

    public function codeUpdate(Request $request, ReferralCode $referralCode): JsonResponse
    {
        $settings  = SettingService::instance()->get();
        $minLength = (int) ($settings->referral_code_min_length ?? 4);
        $maxLength = (int) ($settings->referral_code_max_length ?? 20);

        $request->validate([
            'code' => [
                'required', 'string',
                'min:' . $minLength, 'max:' . $maxLength,
                'regex:/^[A-Za-z0-9]+$/',
            ],
        ]);

        $newCode = strtoupper(trim($request->input('code')));

        if (ReferralCode::where('code', $newCode)->where('id', '!=', $referralCode->id)->exists()) {
            return response()->json(['message' => 'Code already exists'], 422);
        }

        $referralCode->update(['code' => $newCode, 'is_custom' => true]);

        return GeneralResource::make(['code' => $referralCode->code])->response();
    }

    public function codeDeactivate(ReferralCode $referralCode): JsonResponse
    {
        $referralCode->update(['is_active' => false]);
        return GeneralResource::make(['is_active' => false])->response();
    }

    public function codeDestroy(ReferralCode $referralCode): JsonResponse
    {
        $referralCode->delete();
        return GeneralResource::make(['deleted' => true])->response();
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public function settingsIndex(): JsonResponse
    {
        $keys = [
            'referral_pending_days',
            'referral_withdrawal_fee_percent',
            'referral_min_platform_spend',
            'referral_min_withdrawal',
            'referral_activation_threshold',
            'referral_code_min_length',
            'referral_code_max_length',
            'referral_activity_window_days',
            'referral_activity_min_orders',
            'referral_activity_min_referrals',
        ];

        $settings = \App\Models\Setting::whereIn('key', $keys)->get(['key', 'value', 'id']);

        return GeneralResource::make($settings)->response();
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'settings'         => ['required', 'array'],
            'settings.*.key'   => ['required', 'string'],
            'settings.*.value' => ['required', 'string'],
        ]);

        $allowedKeys = [
            'referral_pending_days',
            'referral_withdrawal_fee_percent',
            'referral_min_platform_spend',
            'referral_min_withdrawal',
            'referral_activation_threshold',
            'referral_code_min_length',
            'referral_code_max_length',
            'referral_activity_window_days',
            'referral_activity_min_orders',
            'referral_activity_min_referrals',
        ];

        foreach ($request->input('settings') as $item) {
            if (!in_array($item['key'], $allowedKeys)) {
                continue;
            }
            \App\Models\Setting::where('key', $item['key'])->update(['value' => $item['value']]);
        }

        \Illuminate\Support\Facades\Cache::forget('settings');

        return GeneralResource::make(['updated' => true])->response();
    }
}
