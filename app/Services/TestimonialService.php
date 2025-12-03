<?php

namespace App\Services;

use App\Http\Resources\Admin\Testimonials\TestimonialsListResource;
use App\Repositories\TestimonialRepository;
use App\Traits\AppServicesTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestimonialService
{
    use AppServicesTrait;

    private static ?TestimonialService $instance = null;
    private TestimonialRepository $repo;

    private function __construct()
    {
        $this->repo = new TestimonialRepository();
    }

    public static function instance(): TestimonialService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): AnonymousResourceCollection
    {
        return TestimonialsListResource::collection($this->repo->list($this->getLimit()));
    }
}
