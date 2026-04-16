<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\App\Advertisements\AdvertisementResource;
use App\Repositories\AdvertisementRepository;
use App\Services\RouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdvertisementController extends Controller
{
    private AdvertisementRepository $repo;

    public function __construct(AdvertisementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function active(Request $request): AnonymousResourceCollection
    {
        return AdvertisementResource::collection($this->repo->getActive(RouteService::instance()->getName()));
    }

    public function byKey(Request $request, string $key): AdvertisementResource
    {
        return AdvertisementResource::make($this->repo->getByKey($key, RouteService::instance()->getName()));
    }

    public function click(Request $request, string $key): RedirectResponse
    {
        $advertisement = $this->repo->getByKey($key, RouteService::instance()->getName());
        $this->repo->trackClick($advertisement, $request);

        return redirect()->away($advertisement->link);
    }
}
