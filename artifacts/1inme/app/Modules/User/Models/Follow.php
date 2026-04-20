<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    public $timestamps = false;
    protected $fillable = ['follower_id', 'creator_id', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function follower() { return $this->belongsTo(User::class, 'follower_id'); }
    public function creator()  { return $this->belongsTo(User::class, 'creator_id'); }
}
