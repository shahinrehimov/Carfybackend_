<?php

use App\Services\LangService;
use Illuminate\Support\Facades\Route;

Route::get('versions', static function () {
    return LangService::instance()->getVersionsJson();
});

$coreAppRoutes = require __DIR__ . '/app_core.php';

require __DIR__ . '/control.php';

require __DIR__ . '/app.php';
require __DIR__ . '/site.php';
