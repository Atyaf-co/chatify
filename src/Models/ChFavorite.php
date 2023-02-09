<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Chatify\Traits\UUID;

class ChFavorite extends Model
{
    use UUID;

    public function user()
    {
        return $this->morphTo('user');
    }

    public function favorite()
    {
        return $this->morphTo('favorite');
    }

    public function getFavoriteUIdAttribute()
    {
        return $this->favorite_type . '#' . $this->favorite_id;
    }

    public function getUserUIdAttribute()
    {
        return $this->user_type . '#' . $this->user_id;
    }
}
