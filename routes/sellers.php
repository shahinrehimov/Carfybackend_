<?php

use App\Http\Middleware\RouteLogMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['general_access:sellers', RouteLogMiddleware::class])->prefix('sellers')->group(callback: static function () {

});
