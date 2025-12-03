<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GenderService;

class GenderController extends Controller
{
    public function list(): array
    {
        return GenderService::instance()->getItems();
    }
}
