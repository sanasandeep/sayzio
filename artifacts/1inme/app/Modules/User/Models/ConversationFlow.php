<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationFlow extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'link_id', 'workspace_id', 'name', 'version', 'is_published', 'is_active',
        'intro_message', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_active'    => 'boolean',
            'settings'     => 'array',
            'version'      => 'integer',
        ];
    }

    public function link(): BelongsTo { return $this->belongsTo(Link::class); }
    public function steps(): HasMany   { return $this->hasMany(ConversationStep::class, 'flow_id')->orderBy('sort_order'); }
    public function actions(): HasMany { return $this->hasMany(ConversationAction::class, 'flow_id'); }
    public function sessions(): HasMany{ return $this->hasMany(ConversationSession::class, 'flow_id'); }
    public function events(): HasMany  { return $this->hasMany(ConversationStepEvent::class, 'flow_id'); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function entryStep(): ?ConversationStep
    {
        return $this->steps()->where('is_entry', true)->first()
            ?: $this->steps()->orderBy('sort_order')->first();
    }
}
