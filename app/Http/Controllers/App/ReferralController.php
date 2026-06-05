<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Referral\CreateWithdrawalRequest;
use App\Http\Requests\App\Referral\UpdateReferralCodeRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\Referral\ReferralEarningResource;
use App\Http\Resources\App\Referral\ReferralMyResource;
use App\Http\Resources\App\Referral\ReferralWithdrawalResource;
use App\Http\Resources\GeneralResource;
use App\Services\LangService;
use App\Services\ReferralService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferralController extends Controller
{
    public function my(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $repo  = ReferralService::instance()->repository();
        $stats = $repo->getReferralStats($user);
        $code  = $repo->getUserActiveCode($user);

        $stats['code'] = $code?->code;
        $stats['link'] = $code ? config('app.frontend_url', '') . '/register?ref=' . $code->code : null;

        return ReferralMyResource::make($stats)->response();
    }

    public function balance(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $balance = ReferralService::instance()->repository()->getOrCreateBalance($user->id);

        return GeneralResource::make([
            'pending_balance'             => round($balance->pending_balance / 100, 2),
            'available_balance'           => round($balance->available_balance / 100, 2),
            'lifetime_earned'             => round($balance->lifetime_earned / 100, 2),
            'lifetime_withdrawn'          => round($balance->lifetime_withdrawn / 100, 2),
            'lifetime_spent_on_platform'  => round($balance->lifetime_spent_on_platform / 100, 2),
        ])->response();
    }

    public function earnings(GeneralListRequest $request): AnonymousResourceCollection
    {
        $user     = auth()->user();
        $earnings = ReferralService::instance()->repository()
            ->getEarningsForUser($user, $request->validated());

        return ReferralEarningResource::collection($earnings);
    }

    public function referrals(Request $request): JsonResponse
    {
        $user = auth()->user();
        $repo = ReferralService::instance()->repository();

        $attributions = \App\Models\Referral\ReferralAttribution::where('referrer_id', $user->id)
            ->with('referred:id,name,surname,created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('limit', 15));

        $attributions->getCollection()->transform(fn($a) => [
            'referred_name'  => $a->referred ? trim($a->referred->name . ' ' . $a->referred->surname) : null,
            'status'         => $a->status,
            'activated_at'   => $a->activated_at?->toISOString(),
            'joined_at'      => $a->created_at->toISOString(),
        ]);

        return GeneralResource::make($attributions)->response();
    }

    public function perks(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $perks = ReferralService::instance()->getPerks($user);

        return GeneralResource::make($perks)->response();
    }

    public function createWithdrawal(CreateWithdrawalRequest $request): JsonResponse
    {
        $user     = auth()->user();
        $repo     = ReferralService::instance()->repository();
        $settings = SettingService::instance()->get();

        $amountCents  = (int) round($request->input('amount') * 100);
        $minWithdraw  = (int) ($settings->referral_min_withdrawal ?? 5000);
        $balance      = $repo->getOrCreateBalance($user->id);

        if ($balance->available_balance < $minWithdraw) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('Minimum withdrawal amount is @min AZN')
                    ->getLang('referral_min_withdrawal_not_met', ['@min' => $minWithdraw / 100]),
            ], 422);
        }

        if ($amountCents > $balance->available_balance) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('Requested amount exceeds your available balance')
                    ->getLang('referral_insufficient_balance'),
            ], 422);
        }

        if ($amountCents < $minWithdraw) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('Minimum withdrawal amount is @min AZN')
                    ->getLang('referral_min_withdrawal_not_met', ['@min' => $minWithdraw / 100]),
            ], 422);
        }

        $withdrawal = $repo->createWithdrawal(
            $user,
            $amountCents,
            $request->input('card_last4'),
            $request->input('card_holder_name')
        );

        return ReferralWithdrawalResource::make($withdrawal)->response();
    }

    public function withdrawals(\Illuminate\Http\Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $user        = auth()->user();
        $withdrawals = ReferralService::instance()->repository()
            ->getWithdrawalsForUser($user, $request->integer('limit', 15));

        return ReferralWithdrawalResource::collection($withdrawals);
    }

    public function updateCode(UpdateReferralCodeRequest $request): JsonResponse
    {
        $user    = auth()->user();
        $newCode = strtoupper(trim($request->input('code')));
        $repo    = ReferralService::instance()->repository();

        if ($repo->codeExistsForOtherUser($newCode, $user->id)) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('This referral code is already taken.')
                    ->getLang('referral_code_already_taken'),
            ], 422);
        }

        $code = $repo->updateUserCode($user, $newCode);

        return GeneralResource::make(['code' => $code->code])->response();
    }
}
