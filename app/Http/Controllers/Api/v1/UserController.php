<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class UserController extends Controller {

    public function login_view(Request $request){
        if(strpos($request->url(),"api") > 0){
            return response()->json(['status'=>false,'msg'=>'you are not an authenticated user']);
        }
        else{
            return response()->json(['status'=>false,'msg'=>'you cannot access this url']);
        }
    }

    public function login(Request $request){
        $validator = Validator::make($request->all(),[
            'username' => 'required',            
            'password' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(['status'=>false,'msg'=>$validator->errors()->all()]);
        }
        $username = $request->username;
        $password = $request->password;
        $user = User::where('username',$username)->first();
        if(empty($user)){
            return response()->json([
                'status' => false,
                'msg' => 'username not found'
            ]);
        }
        if(Hash::check($password, $user->password)){
            // laravel passport
            $token = $user->createToken('open_chat_token')->accessToken;
            $user_det = new \stdClass();
            $user_det->id = $user->id;
            $user_det->user_first_name = $user->user_first_name;
            $user_det->user_last_name = $user->user_last_name;
            $user_det->email = $user->email;
            $user_det->icon = $user->icon;

            return response()->json([
                'status' => true,
                'msg' => 'login successful',
                'user' =>$user_det,
                'token' => $token
            ])->withCookie(cookie('token', $token, 7200));
        }
        else{
            return response()->json([
                'status' => false,
                'msg' => 'incorrect password'
            ]);
        }
    }

    public function register(Request $request){
        $validator = Validator::make($request->all(),[
            'username' => 'required|min:6|max:6|unique:users',
            'firstname' => 'required',
            'lastname' => 'required',
            'email' => 'required|email',
            'password' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(['status'=>false,'errors'=>$validator->errors()->all()]);
        }
        $username = $request->username;
        $firstname = $request->firstname;
        $lastname = $request->lastname;
        $email = $request->email;
        $password = $request->password;
        $user = User::where('username',$username)->first();
        if(!empty($user)){
            return response()->json([
                'status' => false,
                'msg' => 'this username is taken'
            ]);
        }
        $user = new User();
        $user->user_first_name = $firstname;
        $user->user_last_name = $lastname;
        $user->email = $email;
        $user->password = $password;
        $user->username = $username;
        $user->icon = "/defaults/user_icons/default_profile_image.jpg";
        $user->last_online = Carbon::now();
        $user->save();


        // laravel passport
        $token = $user->createToken('open_chat_token')->accessToken;
        $user_det = new \stdClass();
        $user_det->id = $user->id;
        $user_det->user_first_name = $user->user_first_name;
        $user_det->user_last_name = $user->user_last_name;
        $user_det->email = $user->email;
        $user_det->icon = $user->icon;

        return response()->json([
            'status' => true,
            'msg' => 'login successful',
            'user' =>$user_det,
            'token' => $token
        ])->withCookie(cookie('token', $token, 7200));
    }

    public function logout(Request $request){
        unset($_COOKIE['token']);
        $cookie = \Cookie::forget('token');
        // Auth::logout();
        return response()->json([
            'status' => true,
            'msg' => 'logout successful'
        ])->withCookie(cookie('token', null, 1));
    }

    public function search_users(Request $request){
        $validator = Validator::make($request->all(),[
            'search_string' => 'required'
        ]);
        $search_string = $request->search_string;
        if($validator->fails()){
            return response()->json($validator->errors());
        }
        $result = User::where('user_first_name','like','%'.$search_string.'%')->get();
        return response()->json($result);
    }

    public function edit_profile(Request $request){
        $validator = Validator::make($request->all(), [
            // 'edit_profile_icon' => 'required',
            'edit_profile_fname' => 'required',
            'edit_profile_lname' => 'required'
        ]);
        if($validator->fails()){
            return response()->json($validator->errors());
        }

        DB::beginTransaction();
        //get old user icon
        $user_rec = User::where('id',$request->user()->id)->first();
        try{
            if($request->hasFile('edit_profile_icon')){

                // delete old user icon
                Storage::delete($user_rec->icon);
                // upload new user icon
                $icon_path = $request->file('edit_profile_icon')->store('user_icons');
                $icon_path = "/".$icon_path;
                // echo $icon_path;
                //updating user record
                $user_rec->icon = $icon_path;

            }

            $fname = $request->edit_profile_fname;
            $lname = $request->edit_profile_lname;

            $user_rec->user_first_name = $fname;
            $user_rec->user_last_name = $lname;
            $user_rec->save();

            DB::commit();

            return response()->json(['status'=>true,'msg'=>"upated",'user'=>$user_rec]);

        }
        catch(\Exception $e){
            DB::rollback();
            $ret_arr = ['status'=>false,'msg'=>'Error'];
            Log::debug('file: '.__FILE__.'\nline: '.__LINE__.',\nerr: '.$e.'\n\n');
            return response()->json($ret_arr);
        }
    }

    public function check_auth(Request $request){
        return response()->json(['status'=>true,'msg'=>'auth successful','user'=>$request->user()]);
    }

    public function check_username_availability(Request $request){

        $validator = Validator::make($request->all(),[
            'username' => 'required|max:6|min:6'
        ]);
        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()->all()]);
        }

        $username = $request->username;

        $user = User::select('username')->where('username',$username)->first();
        if(empty($user)){
            return response()->json([
                'status' => true,
                'msg' => 'username available'
            ]);
        }
        else{
            return response()->json([
                'status' => false,
                'msg' => 'username not available',
            ]);
        }

    }

}