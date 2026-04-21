<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Pixel extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = ['user_id', 'name', 'type', 'pixel_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function links()
    {
        return $this->belongsToMany(Link::class, 'link_pixels');
    }
}
