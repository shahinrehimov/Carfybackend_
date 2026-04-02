<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Pages\PageListRequest;
use App\Http\Resources\App\Pages\PageResource;
use App\Http\Resources\App\Pages\PagesListResource;
use App\Repositories\PageRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageController extends Controller
{
    private PageRepository $repo;

    public function __construct(PageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(PageListRequest $request): AnonymousResourceCollection
    {
        return PagesListResource::collection($this->repo->listForSite($request->validated()));
    }

    public function show(string $slug): PageResource
    {
        return PageResource::make($this->repo->showBySlugForSite($slug));
    }
}
