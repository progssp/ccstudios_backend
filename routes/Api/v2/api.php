<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function(){
    Route::get('/index', [App\Http\Controllers\Api\v2\UserController::class,'index']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:api');
});
