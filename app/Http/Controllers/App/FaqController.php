<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\App\Faqs\FaqsListResource;
use App\Repositories\FaqRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FaqController extends Controller
{
    private FaqRepository $repo;

    public function __construct(FaqRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(): AnonymousResourceCollection
    {
        return FaqsListResource::collection($this->repo->list());
    }
}
