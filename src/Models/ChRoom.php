<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Chatify\Traits\UUID;

class ChRoom extends Model
{
    use UUID;

    public function cause()
    {
        return $this->morphTo('chat_cause');
    }
}
