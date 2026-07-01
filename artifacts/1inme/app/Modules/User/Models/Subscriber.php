<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'link_id', 'block_id', 'type', 'email', 'phone',
        'name', 'channel_url', 'status', 'source', 'metadata',
        'subscribed_at', 'unsubscribed_at',
        'is_read', 'is_starred', 'is_spam', 'spam_reason', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'is_spam' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * When a new subscriber (biolink email/WhatsApp opt-in) is captured, fan
     * it out to the owner's connected CRMs. Loop-safe: inbound CRM pulls only
     * ever create Contacts, never Subscribers, so this can never echo back.
     */
    protected static function booted(): void
    {
        static::created(function (Subscriber $sub): void {
            if ($sub->is_spam || !$sub->user_id) {
                return;
            }
            \App\Jobs\PushLeadToCrmJob::forUser((int) $sub->user_id, $sub->toCrmLead());
        });
    }

    /** @return array<string,mixed> normalized CRM lead payload. */
    public function toCrmLead(): array
    {
        $name  = trim((string) $this->name);
        $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];
        return [
            'email'        => $this->email,
            'phone'        => $this->phone,
            'first_name'   => $parts[0] ?? null,
            'last_name'    => $parts[1] ?? null,
            'display_name' => $name !== '' ? $name : null,
            'company'      => null,
            'source'       => 'subscriber:' . ($this->type ?: 'email'),
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\User\Models\User::class);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * Parent record used by the BelongsToWorkspace trait to derive
     * workspace_id when a subscriber is created from a public flow
     * (no current_workspace bound). Falls back to the parent link.
     */
    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function block()
    {
        return $this->belongsTo(BiolinkBlock::class, 'block_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
