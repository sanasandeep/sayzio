<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = ['user_id', 'name', 'color', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function getLinksCountAttribute(): int
    {
        return $this->links()->count();
    }

    public function getTotalClicksAttribute(): int
    {
        return $this->links()->sum('total_clicks');
    }
}
