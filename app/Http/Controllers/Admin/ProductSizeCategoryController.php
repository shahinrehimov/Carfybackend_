<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductSizeCategoryService;
use Illuminate\Http\JsonResponse;

class ProductSizeCategoryController extends Controller
{
    public function list(): JsonResponse
    {
        return response()->json([
            'data' => ProductSizeCategoryService::instance()->getItems(),
        ]);
    }
}
