<?php

namespace App\Http\Controllers;

use App\Models\Banners;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function get_banner() {
        return ok(Banners::get());
    }
}
