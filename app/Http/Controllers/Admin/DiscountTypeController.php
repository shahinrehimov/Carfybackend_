<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DiscountTypeService;
use Illuminate\Http\JsonResponse;

class DiscountTypeController extends Controller
{
    public function list(): JsonResponse
    {
        return response()->json([
            'data' => DiscountTypeService::instance()->getItems(),
        ]);
    }
}
