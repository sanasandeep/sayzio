<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiPersonaAgentVersion extends Model
{
    public $timestamps = false;

    protected $table = 'ai_persona_agent_versions';

    protected $fillable = [
        'persona_id', 'revision', 'config', 'summary',
        'created_by_user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'config'     => 'array',
            'revision'   => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function persona() { return $this->belongsTo(AiPersonaAgent::class, 'persona_id'); }
}
