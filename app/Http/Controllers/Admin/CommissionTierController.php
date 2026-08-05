<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionTiers\CommissionTiersUpdateRequest;
use App\Http\Resources\Admin\CommissionTiers\CommissionTierResource;
use App\Http\Resources\GeneralResource;
use App\Repositories\CommissionTierRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

/**
 * Platform-wide (global) commission tiers. Company and category tier sets are
 * managed through their own forms; these are the last resort of the chain,
 * checked right before the default_commission_percent setting.
 */
class CommissionTierController extends Controller
{
    private CommissionTierRepository $repo;

    public function __construct(CommissionTierRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(): JsonResponse
    {
        return response()->json(GeneralResource::make([
            'data' => CommissionTierResource::collection($this->repo->globalTiers()),
        ]));
    }

    public function update(CommissionTiersUpdateRequest $request): JsonResponse
    {
        $this->repo->replaceFor(null, $request->validated()['commission_tiers'] ?? []);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('commission_tiers_saved_successfully'),
            'data' => CommissionTierResource::collection($this->repo->globalTiers()),
        ]));
    }
}
