<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Socials\SocialStoreRequest;
use App\Http\Requests\Admin\Socials\SocialUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Socials\SocialsListResource;
use App\Http\Resources\Admin\Socials\SocialsResource;
use App\Http\Resources\Admin\Socials\SocialResource;
use App\Http\Resources\GeneralResource;
use App\Models\Social;
use App\Repositories\SocialRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SocialController extends Controller
{
    private SocialRepository $repo;

    public function __construct(SocialRepository $repo)
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
        return SocialsResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return SocialsListResource::collection($this->repo->list());
    }

    public function show(Social $social): SocialResource
    {
        $social = $this->repo->show($social);

        return SocialResource::make($social);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param SocialStoreRequest $request
     * @return JsonResponse
     */
    public function store(SocialStoreRequest $request): JsonResponse
    {
        $social = $this->repo->store($request);

        $social = $this->repo->loadRelations($social);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('social_form_saved_successfully'),
            'data' => SocialsResource::make($social),
        ]));
    }

    public function update(SocialUpdateRequest $request, Social $social): JsonResponse
    {
        $social = $this->repo->update($social, $request);

        $social = $this->repo->loadRelations($social);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('social_form_saved_successfully'),
            'data' => SocialsResource::make($social),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Social $social
     * @return JsonResponse
     */
    public function destroy(Social $social): JsonResponse
    {
        $this->repo->destroy($social);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('social_deleted_successfully'),
        ]));
    }
}
