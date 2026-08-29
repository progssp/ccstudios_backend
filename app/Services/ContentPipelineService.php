<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Exceptions\GetResolutionException;
use App\Exceptions\CreatePlaylistException;
use App\Exceptions\UploadToBucketException;
use App\Events\NewUserNotification;
use App\Services\UpdateStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\CcstudiosContent;
use Exception;

class ContentPipelineService
{
    private int $original_height;
    private string $content_id;
    private string $original_file_path;
    private string $output_path;
    private array $request_data;
    private Model $new_record;
    private UpdateStatusService $update_status;

    public function __construct(UpdateStatusService $update_status_service)
    {
        $this->update_status = $update_status_service;
    }
        
    public function process(
        array $request_data,
        string $content_id,
        string $original_file_path,
        string $output_path
    ){
        $this->request_data = $request_data;
        $this->content_id = $content_id;
        $this->original_file_path = $original_file_path;
        $this->output_path = $output_path;

        $this->saveToDatabase();        
        $this->original_height = $this->getOriginalResolution();
        $this->createPlaylist();
        $this->uploadContentFolder();
        
    }

    public function saveToDatabase(){
        $new_content = new CcstudiosContent();
        $new_content->user_id = $this->request_data['user']->id;
        $new_content->video_name = $this->request_data['video_name'];
        $new_content->video_name_identifier = $this->content_id;
        $new_content->thumbnail = 'thumbnail';
        $new_content->video_stream_path = "content/".$this->content_id."/master.m3u8";
        $new_content->video_meta_details = json_encode('meta_data');
        $new_content->save();
        $this->new_record = $new_content;
        
        $this->update_status->update(
            $this->request_data['user']->id,
            $this->new_record,
            "processing"
        );
    }

    public function getOriginalResolution() : int {
        $this->update_status->update(
            $this->request_data['user']->id,
            $this->new_record,
            "extracting highest resolution"
        );
        try{
            $ffprobe = "/usr/bin/ffprobe";
            $process = new Process([
                $ffprobe,
                '-v','error',
                '-select_streams','v:0',
                '-show_entries','stream=width,height',
                '-of','csv=s=x:p=0',
                $this->original_file_path
            ]);

            $process->run();

            if(!$process->isSuccessful()){
                throw new \Exception($process->getErrorOutput());
            }
            [$width,$height] = explode('x',trim($process->getOutput()));
            return (int)$height;
        }
        catch(Exception $err){
            throw new GetResolutionException("get resolution err for " . $this->content_id . ": " . $err->getMessage());
        }
    }

    public function createPlaylist(){
        try{
            if(!file_exists($this->output_path)){
                mkdir($this->output_path,0775,true);
            }
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

                if((int)$res['res_height'] > (int)$this->original_height){continue;}

                $this->update_status->update(
                    $this->request_data['user']->id,
                    $this->new_record,
                    "processing " . $res['res_dir']
                );
                mkdir($this->output_path.'/'.$res['res_dir'],0777,true);

                $process = new Process([
                    'ffmpeg',
                    '-i',$this->original_file_path,
                    '-vf',"scale=-2:".$res['res_height'],
                    '-c:v','libx264',
                    '-preset','medium',
                    '-c:a','aac','-b:a','128k',
                    '-hls_time','6','-hls_playlist_type','vod',
                    '-hls_segment_filename',
                    $this->output_path."/".$res['res_dir']."/seg_%03d.ts",
                    $this->output_path."/".$res['res_dir']."/index.m3u8",
                ]);
                $process->setTimeout(0);
                $process->mustRun();

                if(!$process->isSuccessful()){
                    throw new \Exception($process->getErrorOutput());
                }

                $master_file_content .= "#EXT-X-STREAM-INF:BANDWIDTH=".$res['bandwidth'].",RESOLUTION=".$res['master_file_res'] . "\n" . $res['res_dir'] . "/index.m3u8" . "\n\n";
            
            }

            $master_file_content .= "M3U8;";
            file_put_contents($this->output_path.'/master.m3u8',$master_file_content);
        }        
        catch(Exception $err){
            throw new CreatePlaylistException("create paylist err for " . $this->content_id . ": " . $err->getMessage());
        }
    }

    public function uploadContentFolder(){
        $this->update_status->update(
            $this->request_data['user']->id,
            $this->new_record,
            "moving content to safe vault"
        );
        try{
            $localVideoPath = $this->output_path;

            if(!File::exists($localVideoPath)){
                throw new Exception($this->content_id.": ".$localVideoPath." not found!");
            }

            $files = File::allFiles($localVideoPath);
            $supabaseUrl = env('SUPABASE_ENDPOINT');
            $serviceKey = env('SUPABASE_SERVICE_KEY');
            $bucket = env('SUPABASE_BUCKET');

            foreach($files as $file){
                $filePath = $file->getRealPath();
               
                $relativePath = $this->content_id . DIRECTORY_SEPARATOR . str_replace($localVideoPath . DIRECTORY_SEPARATOR,'',$filePath);              

                $supabaseDestinationPath = 'content/' . str_replace('\\','/',$relativePath);

                $mimeType = File::mimeType($filePath);
                $extension = strtolower(pathinfo($filePath,PATHINFO_EXTENSION));

                if($extension === "m3u8"){
                    $mimeType = 'application/x-mpegURL';
                }
                else if($extension === 'ts'){
                    $mimeType = 'video/MP2T';
                }

                $apiUrl = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $supabaseDestinationPath;

                $response = Http::withHeaders([
                    'apiKey' => $serviceKey,
                    'Authorization' => 'Bearer ' . $serviceKey,
                    'Content-Type' => $mimeType,
                ])
                ->withBody(file_get_contents($filePath),$mimeType)
                ->post($apiUrl);

                if(!$response->successful()){
                    throw new Exception("supabase error: " . $response->body());
                }
            }

            $this->update_status->update(
                $this->request_data['user']->id,
                $this->new_record,
                "done"
            );
        }        
        catch(Exception $err){
            throw new UploadToBucketException("upload to bucket err for ".$this->content_id.": " . $err->getMessage());
        }
    }

    
}
