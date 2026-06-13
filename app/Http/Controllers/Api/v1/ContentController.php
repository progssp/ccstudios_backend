<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ContentController extends Controller
{
    public function upload(Request $request){
        
        $validator = Validator::make($request->all(),[
            'actual_content' => 'required|file',
            'title' => 'required',
            'thumbnail' => 'required',
            'description' => 'required',
            'hash_tags' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(['status'=>false,'errors'=>$validator->errors()]);
        }

        $input_file_name = Str::random();
        
        $path = $request->file('actual_content')->storeAs('uploads',$input_file_name.'.'.$request->file('actual_content')->getClientOriginalExtension(),'local');
        
        $absInputDir = storage_path('app/private/'.$path);

        $outputDir = 'hls_outputs/'.$input_file_name;
        $absOutputDir = storage_path('app/private/'.$outputDir);

        if(!file_exists($absOutputDir)){
            mkdir($absOutputDir,0777,true);
        }

        // getting original resolution of video
        $process = new Process([
            'ffprobe',
            '-v','error',
            '-select_streams','v:0',
            '-show_entries','stream=width,height',
            '-of','csv=s=x:p=0',
            $absInputDir
        ]);

        $process->run();

        if(!$process->isSuccessful()){
            throw new \Exception($process->getErrorOutput());
        }
        [$width,$height] = explode('x',trim($process->getOutput()));
        // getting original resolution of video (end)

        // creating playlist for downscaled res
        $master_file_content = "#EXTM3U" . "\n\n";
        
        $standard_res = [
            [
                'res_dir' => '1080p',
                'res_height' => 1080,
                'master_file_res' => '1920x1080',
                'bandwidth' => 5000000
            ],
            [
                'res_dir' => '720p',
                'res_height' => 720,
                'master_file_res' => '1280x720',
                'bandwidth' => 2800000
            ],
            [
                'res_dir' => '480p',
                'res_height' => 480,
                'master_file_res' => '854x480',
                'bandwidth' => 1400000
            ],
        ];
       
        foreach($standard_res as $res){

            if((int)$res['res_height'] > (int)$height){continue;}

            mkdir($absOutputDir.'/'.$res['res_dir'],0777,true);

            $process = new Process([
                'ffmpeg',
                '-i',$absInputDir,
                '-vf',"scale=-2:".$res['res_height'],
                '-c:v','libx264',
                '-preset','medium',
                '-c:a','aac','-b:a','128k',
                '-hls_time','6','-hls_playlist_type','vod',
                '-hls_segment_filename',
                "$absOutputDir/".$res['res_dir']."/seg_%03d.ts",
                "$absOutputDir/".$res['res_dir']."/index.m3u8",
            ]);
            $process->setTimeout(0);
            $process->mustRun();

            $master_file_content .= "#EXT-X-STREAM-INF:BANDWIDTH=".$res['bandwidth'].",RESOLUTION=".$res['master_file_res'] . "\n" . $res['res_dir'] . "/index.m3u8" . "\n\n";
        
        }

        $master_file_content .= "M3U8;";
        file_put_contents($absOutputDir.'/master.m3u8',$master_file_content);
        // creating playlist for downscaled res (end)


        return response()->json([
            'width' => (int)$width,
            'height' => (int)$height,
        ]);

        return response()->json([
                'status' => true,
                'msg' => 'uploaded',
                'input_file_name' => $input_file_name,
                'err' => $output
        ]);
    }
}
