<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Statistics\CancellationStatsRequest;
use App\Http\Requests\Admin\Statistics\CommissionStatsRequest;
use App\Http\Requests\Admin\Statistics\OrderStatsRequest;
use App\Http\Requests\Admin\Statistics\TopProductsRequest;
use App\Http\Requests\Admin\Statistics\TopStoresRequest;
use App\Http\Requests\Admin\Statistics\UserStatsRequest;
use App\Http\Resources\GeneralResource;
use App\Repositories\ControlStatisticsRepository;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    private ControlStatisticsRepository $repo;

    public function __construct(ControlStatisticsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function orderStats(OrderStatsRequest $request): JsonResponse
    {
        return response()->json(GeneralResource::make(
            $this->repo->getOrderStats($request->validated())
        ));
    }

    public function topStores(TopStoresRequest $request): JsonResponse
    {
        return response()->json(GeneralResource::make([
            'data' => $this->repo->getTopStores($request->validated())->values(),
        ]));
    }

    public function topProducts(TopProductsRequest $request): JsonResponse
    {
        return response()->json(GeneralResource::make(
            $this->repo->getTopProducts($request->validated())
        ));
    }

    public function userStats(UserStatsRequest $request): JsonResponse
    {
        return response()->json(GeneralResource::make(
            $this->repo->getUserStats($request->validated())
        ));
    }

    public function cancellations(CancellationStatsRequest $request): JsonResponse
    {
        return response()->json(GeneralResource::make(
            $this->repo->getCancellations($request->validated())
        ));
    }

    public function commissions(CommissionStatsRequest $request): JsonResponse
    {
        return response()->json(GeneralResource::make(
            $this->repo->getCommissions($request->validated())
        ));
    }
}
