<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sliders\SliderChangeSortMultipleRequest;
use App\Http\Requests\Admin\Sliders\SliderChangeSortRequest;
use App\Http\Requests\Admin\Sliders\SliderStoreRequest;
use App\Http\Requests\Admin\Sliders\SliderUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Sliders\SlidersListResource;
use App\Http\Resources\Admin\Sliders\SlidersResource;
use App\Http\Resources\Admin\Sliders\SliderResource;
use App\Http\Resources\GeneralResource;
use App\Models\Slider;
use App\Repositories\SliderRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SliderController extends Controller
{
    private SliderRepository $repo;

    public function __construct(SliderRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return SlidersResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return SlidersListResource::collection($this->repo->list());
    }

    public function show(Slider $slider): SliderResource
    {
        $slider = $this->repo->show($slider);
        return SliderResource::make($slider);
    }

    public function store(SliderStoreRequest $request): JsonResponse
    {
        $slider = $this->repo->store($request);
        $slider = $this->repo->loadRelations($slider);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Slider saved successfully!')
                ->getLang('slider_form_saved_successfully'),
            'data' => SlidersResource::make($slider),
        ]));
    }

    public function update(SliderUpdateRequest $request, Slider $slider): JsonResponse
    {
        $slider = $this->repo->update($slider, $request);
        $slider = $this->repo->loadRelations($slider);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Slider updated successfully!')
                ->getLang('slider_form_updated_successfully'),
            'data' => SlidersResource::make($slider),
        ]));
    }

    public function destroy(Slider $slider): JsonResponse
    {
        $this->repo->destroy($slider);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Slider deleted successfully!')
                ->getLang('slider_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Slider $slider): JsonResponse
    {
        $this->repo->changeActiveStatus($slider);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(SliderChangeSortRequest $request, Slider $slider): JsonResponse
    {
        $validated = $request->validated();
        $this->repo->changeSorting($slider, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(SliderChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
