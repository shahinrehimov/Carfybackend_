<?php

namespace App\Services;

use App\Http\Resources\App\Faqs\FaqsListResource;
use App\Repositories\FaqRepository;
use App\Traits\AppServicesTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FaqService
{
    use AppServicesTrait;

    private static ?FaqService $instance = null;
    private FaqRepository $repo;

    private function __construct()
    {
        $this->repo = new FaqRepository();
    }

    public static function instance(): FaqService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): AnonymousResourceCollection
    {
        return FaqsListResource::collection($this->repo->list());
    }
}
