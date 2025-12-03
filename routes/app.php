<?php

use Illuminate\Support\Facades\Route;

/** @var Closure $coreAppRoutes */

Route::prefix('app')
    ->middleware(['general_access:app'])
    ->group(function () use ($coreAppRoutes) {
        $coreAppRoutes(); // shared core
        //require __DIR__ . '/app_extra.php';
    });
