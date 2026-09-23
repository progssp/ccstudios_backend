<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\ContentController;

Route::prefix('v1')->group(function(){
    
    Route::get('/index', [App\Http\Controllers\Api\v1\UserController::class,'index']);
   
    Route::post('/get_vid', [ContentController::class,'get_all_content']);
    Route::post('/load-video-details', [ContentController::class,'load_content_details']);

    Route::prefix('user')->group(function(){

        

        Route::get('login', [UserController::class, 'login_view'])->name('login');
        Route::post('login', [UserController::class, 'login']);

        Route::post('register', [UserController::class, 'register']);
    
        Route::post('/check-username-availability', [UserController::class,'check_username_availability']);


        Route::middleware(['check.token_in_cookie','auth:api'])->group(function(){
            
            Route::post('/check-auth',[UserController::class,'check_auth']);
            Route::post('/upload',[ContentController::class,'upload']);
            Route::post('/get-user-content', [ContentController::class,'get_user_content']);

            Route::post('logout', [UserController::class, 'logout']);


            Route::post('push-test-notification', [UserController::class, 'push_test_notification']);
        });

    });

});