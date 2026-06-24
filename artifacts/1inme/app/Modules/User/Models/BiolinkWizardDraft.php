<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BiolinkWizardDraft extends Model
{
    protected $fillable = [
        'user_id', 'actor_user_id', 'workspace_id',
        'persona', 'persona_group',
        'category', 'page_type', 'industry', 'template_id', 'step', 'answers',
        'ai_mind_ids', 'include_platform_mind', 'file_ids',
    ];

    protected function casts(): array
    {
        return [
            'answers'               => 'array',
            'step'                  => 'integer',
            'template_id'           => 'integer',
            'ai_mind_ids'           => 'array',
            'file_ids'              => 'array',
            'include_platform_mind' => 'boolean',
        ];
    }
}
