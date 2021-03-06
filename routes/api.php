<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\Covid19Controller;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});
Route::prefix("v1")->group(function() {
    Route::prefix("user")->group(function() {
        Route::post("login", [UserController::class, "login"]); // API 01 用戶登入
        Route::middleware("checkToken")->group(function() {
            Route::post("logout", [UserController::class, "logout"]); // API 02 用戶登出
            Route::get("profile", [UserController::class, "get_profile"]); // API 04 取得用戶資料(SELF)
            Route::post("profile/edit", [UserController::class, "profile_edit"]); // API 05 更新用戶資料
        });
        Route::post("add", [UserController::class, "add"]); // API 03 用戶註冊
    });
    Route::prefix("index")->group(function() {
        Route::get("banner", [IndexController::class, "get_banner"]); // API XX 取得banner圖
    });
    Route::prefix("covid-19")->group(function() {
//        Route::get("update", [UpdateController::class, "all_country"]);
        Route::prefix("write")->group(function() {
            Route::post("{date}", [UpdateController::class, "write_daily"]);
            Route::post("{begin}/{after}", [UpdateController::class, "write_full"]);
            // ex: covid-19/write/11-09-2020/12-26-2020 批量寫入SQL
        });
        Route::prefix("update")->group(function() {
            Route::post("country", [UpdateController::class, "countries"]);
        });
        Route::get("islands", [Covid19Controller::class, "get_islands"]);
        Route::get("countries/{island_id}", [Covid19Controller::class, "get_countries"]);
    });
});
