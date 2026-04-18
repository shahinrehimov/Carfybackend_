<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\Statistics\CustomerBehaviorRequest;
use App\Http\Requests\Sellers\Statistics\OrderStatsRequest;
use App\Http\Requests\Sellers\Statistics\TopSellingRequest;
use App\Http\Requests\Sellers\Statistics\TopViewedRequest;
use App\Http\Resources\GeneralResource;
use App\Repositories\SellerStatisticsRepository;
use App\Services\CompanyAdminService;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    private SellerStatisticsRepository $repo;

    public function __construct(SellerStatisticsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function orderStats(OrderStatsRequest $request): JsonResponse
    {
        $companyId = CompanyAdminService::instance()->getCompany()->id;

        $data = $this->repo->getOrderStats($companyId, $request->validated());

        return response()->json(GeneralResource::make($data));
    }

    public function topSelling(TopSellingRequest $request): JsonResponse
    {
        $companyId = CompanyAdminService::instance()->getCompany()->id;

        $data = $this->repo->getTopSelling($companyId, $request->validated());

        return response()->json(GeneralResource::make(['data' => $data->values()]));
    }

    public function topViewed(TopViewedRequest $request): JsonResponse
    {
        $companyId = CompanyAdminService::instance()->getCompany()->id;

        $data = $this->repo->getTopViewed($companyId, $request->validated());

        return response()->json(GeneralResource::make(['data' => $data->values()]));
    }

    public function customerBehavior(CustomerBehaviorRequest $request): JsonResponse
    {
        $companyId = CompanyAdminService::instance()->getCompany()->id;

        $data = $this->repo->getCustomerBehavior($companyId, $request->validated());

        return response()->json(GeneralResource::make($data));
    }
}
