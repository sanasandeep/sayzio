<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class LinkPerformanceSnapshot extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'link_id', 'date', 'score', 'components_json',
    ];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'score'           => 'integer',
            'components_json' => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
