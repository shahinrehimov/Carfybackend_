<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['general_access:app'])->prefix('app')->group(static function () {
    require 'app_core.php';
});
