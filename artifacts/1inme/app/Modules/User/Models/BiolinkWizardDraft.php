<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BiolinkWizardDraft extends Model
{
    protected $fillable = [
        'user_id', 'actor_user_id', 'workspace_id',
        'category', 'page_type', 'industry', 'step', 'answers',
        'ai_mind_ids', 'include_platform_mind', 'file_ids',
    ];

    protected function casts(): array
    {
        return [
            'answers'               => 'array',
            'step'                  => 'integer',
            'ai_mind_ids'           => 'array',
            'file_ids'              => 'array',
            'include_platform_mind' => 'boolean',
        ];
    }
}
