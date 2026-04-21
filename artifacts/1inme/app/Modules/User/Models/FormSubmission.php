<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'form_id', 'data', 'files', 'ip', 'user_agent', 'referrer',
        'country', 'is_spam', 'spam_reason', 'is_read', 'is_starred',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'files' => 'array',
            'is_spam' => 'boolean',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Parent record used by the BelongsToWorkspace trait to derive
     * workspace_id when a submission is created from a public visitor
     * (no current_workspace bound). Without this, public submissions
     * land with NULL workspace_id and are then hidden by the global
     * scope when the owner views their inbox.
     */
    public function parentForWorkspace()
    {
        if ($this->form_id) {
            return Form::withoutGlobalScope('workspace')->find($this->form_id);
        }
        return null;
    }
}
