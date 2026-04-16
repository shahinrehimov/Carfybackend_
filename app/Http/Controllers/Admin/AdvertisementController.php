<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertisements\AdvertisementClickListRequest;
use App\Http\Requests\Admin\Advertisements\AdvertisementListRequest;
use App\Http\Requests\Admin\Advertisements\AdvertisementStoreRequest;
use App\Http\Requests\Admin\Advertisements\AdvertisementUpdateRequest;
use App\Http\Resources\Admin\Advertisements\AdvertisementClicksResource;
use App\Http\Resources\Admin\Advertisements\AdvertisementResource;
use App\Http\Resources\Admin\Advertisements\AdvertisementsResource;
use App\Http\Resources\GeneralResource;
use App\Models\Advertisement;
use App\Repositories\AdvertisementRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdvertisementController extends Controller
{
    private AdvertisementRepository $repo;

    public function __construct(AdvertisementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(AdvertisementListRequest $request): AnonymousResourceCollection
    {
        return AdvertisementsResource::collection($this->repo->load($request->validated()));
    }

    public function show(Advertisement $advertisement): AdvertisementResource
    {
        $advertisement = $this->repo->show($advertisement);
        return AdvertisementResource::make($advertisement);
    }

    public function store(AdvertisementStoreRequest $request): JsonResponse
    {
        $advertisement = $this->repo->store($request);
        $advertisement = $this->repo->loadRelations($advertisement);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Advertisement saved successfully!')
                ->getLang('advertisement_form_saved_successfully'),
            'data' => AdvertisementResource::make($advertisement),
        ]));
    }

    public function update(AdvertisementUpdateRequest $request, Advertisement $advertisement): JsonResponse
    {
        $advertisement = $this->repo->update($advertisement, $request);
        $advertisement = $this->repo->loadRelations($advertisement);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Advertisement updated successfully!')
                ->getLang('advertisement_form_updated_successfully'),
            'data' => AdvertisementResource::make($advertisement),
        ]));
    }

    public function destroy(Advertisement $advertisement): JsonResponse
    {
        $this->repo->destroy($advertisement);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Advertisement deleted successfully!')
                ->getLang('advertisement_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Advertisement $advertisement): JsonResponse
    {
        $this->repo->changeActiveStatus($advertisement);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function clicks(AdvertisementClickListRequest $request, Advertisement $advertisement): AnonymousResourceCollection
    {
        return AdvertisementClicksResource::collection(
            $this->repo->loadClicks($advertisement, $request->validated())
        );
    }
}
