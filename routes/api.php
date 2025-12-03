<?php

use App\Services\LangService;
use Illuminate\Support\Facades\Route;

Route::get('versions', static function () {
    return LangService::instance()->getVersionsJson();
});

require_once 'control.php';
require_once 'app.php';
