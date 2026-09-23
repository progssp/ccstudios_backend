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
use Illuminate\Support\Facades\Auth;
use App\Models\CcstudiosContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Exception;

class UpdateStatusService
{
    public function update(int $user_id,Model $new_record=null,string $new_status){
        NewUserNotification::dispatch(
            $user_id,
            $new_record,
            $new_status
        );
    }
}
