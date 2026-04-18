<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\GeneralResource;
use App\Models\CompanyProductVariant;
use App\Repositories\SellerStatisticsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductViewController extends Controller
{
    private SellerStatisticsRepository $repo;

    public function __construct(SellerStatisticsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function track(Request $request, CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->trackView($companyProductVariant, $request);

        return response()->json(GeneralResource::make([]));
    }
}
