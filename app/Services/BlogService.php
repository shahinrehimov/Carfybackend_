<?php

namespace App\Services;

use App\Http\Resources\Admin\Blogs\BlogsListResource;
use App\Repositories\BlogRepository;
use App\Traits\AppServicesTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlogService
{
    use AppServicesTrait;

    private static ?BlogService $instance = null;
    private BlogRepository $repo;

    private function __construct()
    {
        $this->repo = new BlogRepository();
    }

    public static function instance(): BlogService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): AnonymousResourceCollection
    {
        return BlogsListResource::collection($this->repo->listForSite($this->getLimit()));
    }
}
