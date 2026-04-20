<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorPost extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'image'];

    public function user() { return $this->belongsTo(User::class); }
}
