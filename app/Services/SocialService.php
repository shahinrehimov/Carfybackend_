<?php

namespace App\Services;

use App\Http\Resources\Admin\Socials\SocialsListResource;
use App\Models\Social;
use App\Traits\AppServicesTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SocialService
{
    use AppServicesTrait;

    private static ?SocialService $instance = null;

    private function __construct() {

    }

    public static function instance(): SocialService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): AnonymousResourceCollection
    {
        $items = Social::query()
            ->with([
                'media',
            ])
            ->orderBy('id')
            ->limit($this->getLimit())
            ->get();

        return SocialsListResource::collection($items);
    }
}
