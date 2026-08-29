<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Jobs\ProcessContentJob;
use App\Services\ContentPipelineService;
use App\Exceptions\GetResolutionException;
use App\Exceptions\CreatePlaylistException;
use App\Exceptions\UploadToBucketException;
use Illuminate\Support\Facades\Log;
use App\Models\CcstudiosContent;
use Exception;

class ContentController extends Controller
{
    
    public function upload(Request $request){
                
        $validator = Validator::make($request->all(),[
            'actual_content' => 'required|file',
            'thumbnail' => 'required|file',
            'video_name' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(['status'=>false,'errors'=>$validator->errors()]);
        }

       
        
            $data = $request->all();
            unset($data['actual_content']);
            unset($data['thumbnail']);
            $data['user'] = $request->user();

            $input_file_name = Str::random();
            
            $path = $request->file('actual_content')
            ->storeAs('uploads',$input_file_name.'.'.$request->file('actual_content')
            ->getClientOriginalExtension(),'local');
            
            $absInputDir = storage_path('app/private/'.$path);

            $outputDir = 'hls_outputs/'.$input_file_name;
            $absOutputDir = storage_path('app/private/'.$outputDir);
        
        

            ProcessContentJob::dispatch(
                $data,
                $input_file_name,
                $absInputDir,
                $absOutputDir
            );

            return response()->json([
                'status' => true,
                'msg' => 'uploaded',
                'input_file_name' => $input_file_name,
                'orig_file_path' => $absInputDir,
                'output_file_path' => $absOutputDir,
            ]);
       
    }

    public function get_all_content(Request $request){
        if($request->input('vid_to_leave') !== null){
            $result = CcstudiosContent::where('video_name_identifier','not like',$request->input('vid_to_leave'))->orderBy('id','DESC')->get();
            return response()->json(['status' => true,'content'=>$result]);
        }
        else{
            $result = CcstudiosContent::orderBy('id')->get();
            return response()->json(['status' => true,'content'=>$result]);
        }
    }

    public function load_content_details(Request $request){
        $validator = Validator::make($request->all(),[
            'video_name_identifier' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(['status'=>false,'errors'=>$validator->errors()]);
        }
        
        $result = CcstudiosContent::where('video_name_identifier','like',$request->input('video_name_identifier'))->first();
        return response()->json(['status' => true,'data'=>$result]);
       
    }

    
}
