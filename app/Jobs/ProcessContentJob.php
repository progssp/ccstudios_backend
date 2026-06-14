<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\ContentPipelineService;
use App\Exceptions\GetResolutionException;
use App\Exceptions\CreatePlaylistException;
use App\Exceptions\UploadToBucketException;

class ProcessContentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private $request_data,
        private $content_id,
        private $original_file_path,
        private $output_path){}

    /**
     * Execute the job.
     */
    public function handle(ContentPipelineService $pipeline): void
    {
        try{
            $pipeline->process($this->request_data,$this->content_id,$this->original_file_path,$this->output_path);
        }
        catch(GetResolutionException $err){
            Log::error($err->getMessage());
        }
        catch(CreatePlaylistException $err){
            Log::error($err->getMessage());
        }
        catch(UploadToBucketException $err){
            Log::error($err->getMessage());
        }
    }
}
