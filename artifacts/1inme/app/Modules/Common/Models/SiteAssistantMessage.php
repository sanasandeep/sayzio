<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAssistantMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'role', 'content', 'blocks',
        'citations', 'meta', 'credits_spent',
    ];

    protected function casts(): array
    {
        return [
            'blocks'        => 'array',
            'citations'     => 'array',
            'meta'          => 'array',
            'credits_spent' => 'int',
        ];
    }
}
