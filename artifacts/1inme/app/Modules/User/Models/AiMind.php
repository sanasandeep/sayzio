<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiMind extends Model
{
    protected $table = 'ai_minds';

    protected $fillable = [
        'user_id', 'name', 'description', 'is_default', 'is_disabled',
        'disabled_reason', 'chunks_count', 'sources_count', 'last_ingested_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default'       => 'boolean',
            'is_disabled'      => 'boolean',
            'chunks_count'     => 'integer',
            'sources_count'    => 'integer',
            'last_ingested_at' => 'datetime',
        ];
    }

    public function user()    { return $this->belongsTo(User::class); }
    public function sources() { return $this->hasMany(AiMindSource::class, 'mind_id')->orderByDesc('id'); }
    public function chunks()  { return $this->hasMany(AiMindChunk::class, 'mind_id'); }

    public function isPlatform(): bool
    {
        return $this->user_id === null && $this->is_default;
    }

    public function recountStats(): void
    {
        $this->forceFill([
            'sources_count' => $this->sources()->count(),
            'chunks_count'  => $this->chunks()->count(),
        ])->save();
    }
}
