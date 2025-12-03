<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\CategoryListRequest;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    private CategoryRepository $repo;

    public function __construct(CategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(CategoryListRequest $request): AnonymousResourceCollection
    {
        return CategoriesListResource::collection($this->repo->list($request->validated()));
    }

    public function listSubs(CategoryListRequest $request, Category $category): AnonymousResourceCollection
    {
        return CategoriesListResource::collection($this->repo->listSubs($category, $request->validated()));
    }
}
