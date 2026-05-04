<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkSlideDeck extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'link_id', 'workspace_id', 'version', 'is_published', 'settings', 'published_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_published'       => 'boolean',
            'version'            => 'integer',
            'settings'           => 'array',
            'published_snapshot' => 'array',
        ];
    }

    public function link(): BelongsTo { return $this->belongsTo(Link::class); }
    public function slides(): HasMany { return $this->hasMany(LinkSlide::class, 'deck_id')->orderBy('sort_order'); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }
}
