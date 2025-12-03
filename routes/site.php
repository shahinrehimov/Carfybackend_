<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['general_access:site'])->prefix('site')->group(static function () {
    require 'app_core.php';
});
