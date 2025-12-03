<?php

use Illuminate\Support\Facades\Route;

/** @var Closure $coreAppRoutes */

Route::prefix('site')
    ->middleware(['general_access:site'])
    ->group(function () use ($coreAppRoutes) {
        $coreAppRoutes(); // shared core
        //require __DIR__ . '/site_extra.php';
    });
