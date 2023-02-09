<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Chatify\Traits\UUID;

class ChMessage extends Model
{
    use UUID;

    public function sender()
    {
        return $this->morphTo('user');
    }

    public function receiver()
    {
        return $this->morphTo('user');
    }

    public function room()
    {
        return $this->belongsTo(ChRoom::class);
    }

    public function getFromUIdAttribute()
    {
        return $this->from_type . '#' . $this->from_id;
    }

    public function getToUIdAttribute()
    {
        return $this->to_type . '#' . $this->to_id;
    }
}
