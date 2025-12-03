<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Metas\MetaStoreRequest;
use App\Http\Requests\Admin\Metas\MetaUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Metas\MetasListResource;
use App\Http\Resources\Admin\Metas\MetasResource;
use App\Http\Resources\Admin\Metas\MetaResource;
use App\Http\Resources\GeneralResource;
use App\Models\Meta;
use App\Repositories\MetaRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MetaController extends Controller
{
    private MetaRepository $repo;

    public function __construct(MetaRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @param GeneralListRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return MetasResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return MetasListResource::collection($this->repo->list());
    }

    public function show(Meta $meta): MetaResource
    {
        return MetaResource::make($meta);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param MetaStoreRequest $request
     * @return JsonResponse
     */
    public function store(MetaStoreRequest $request): JsonResponse
    {
        $meta = $this->repo->store($request->validated());

        $meta = $this->repo->loadRelations($meta);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('meta_form_saved_successfully'),
            'data' => MetasResource::make($meta),
        ]));
    }

    public function update(MetaUpdateRequest $request, Meta $meta): JsonResponse
    {
        $meta = $this->repo->update($meta, $request->validated());

        $meta = $this->repo->loadRelations($meta);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('meta_form_saved_successfully'),
            'data' => MetasResource::make($meta),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Meta $meta
     * @return JsonResponse
     */
    public function destroy(Meta $meta): JsonResponse
    {
        $this->repo->destroy($meta);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('meta_deleted_successfully'),
        ]));
    }
}
