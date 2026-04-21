<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BiolinkWizardDraft extends Model
{
    protected $fillable = [
        'user_id', 'actor_user_id', 'workspace_id',
        'category', 'page_type', 'industry', 'step', 'answers',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'step'    => 'integer',
        ];
    }
}
