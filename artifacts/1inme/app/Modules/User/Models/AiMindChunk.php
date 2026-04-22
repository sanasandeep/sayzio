<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiMindChunk extends Model
{
    protected $table = 'ai_mind_chunks';

    protected $fillable = [
        'mind_id', 'source_id', 'ord', 'content', 'tokens', 'embedding', 'model',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'ord'       => 'integer',
            'tokens'    => 'integer',
        ];
    }

    public function mind()   { return $this->belongsTo(AiMind::class, 'mind_id'); }
    public function source() { return $this->belongsTo(AiMindSource::class, 'source_id'); }
}
