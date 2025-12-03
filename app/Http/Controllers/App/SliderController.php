<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\App\Sliders\SliderResource;
use App\Repositories\SliderRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SliderController extends Controller
{
    private SliderRepository $repo;

    public function __construct(SliderRepository $repo)
    {
        $this->repo = $repo;
    }

    public function loadByType(string $type): AnonymousResourceCollection
    {
        return SliderResource::collection($this->repo->getByType($type));
    }

    public function getBySlug(string $slug): SliderResource
    {
        return SliderResource::make($this->repo->getBySlug($slug));
    }
}
