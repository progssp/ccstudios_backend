<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{    
    public function index(){
        return response()->json(['msg'=>'hello from v2']);
    }
}
