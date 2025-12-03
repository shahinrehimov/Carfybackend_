<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentMethodHelper;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function getPaymentMethods(): JsonResponse
    {
        return response()->json([
            'data' => PaymentMethodHelper::getMethodsForApi(),
        ]);
    }
}
