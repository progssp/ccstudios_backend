<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcstudiosContent extends Model
{
    public function getStreamUrlAttribute(){
        $supabase_url = env('SUPABASE_URL');
        return "${supabase_url}/storage/v1/object/public/ccstudios_stream_bucket/{$this->stream_path}";
    }
}
