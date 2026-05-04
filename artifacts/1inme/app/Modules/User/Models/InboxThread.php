<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxThread extends Model
{
    use BelongsToWorkspace;

    protected $table = 'inbox_threads';

    protected $fillable = [
        'workspace_id', 'user_id', 'source_type', 'source_id', 'channel',
        'subject', 'preview', 'sender_name', 'sender_email', 'sender_handle', 'sender_avatar',
        'category', 'category_confidence', 'category_source',
        'assignee_user_id', 'status', 'is_private', 'sla_due_at', 'sla_overdue_notified',
        'last_message_at', 'last_sender', 'is_starred', 'is_read', 'unread_count', 'meta',
    ];

    protected $casts = [
        'sla_due_at'           => 'datetime',
        'sla_overdue_notified' => 'boolean',
        'last_message_at'      => 'datetime',
        'is_starred'           => 'boolean',
        'is_read'              => 'boolean',
        'is_private'           => 'boolean',
        'unread_count'         => 'integer',
        'category_confidence'  => 'float',
        'meta'                 => 'array',
    ];

    public const CATEGORIES = ['lead', 'fan', 'sponsorship', 'support', 'spam'];
    public const STATUSES   = ['open', 'archived', 'snoozed'];

    public const CHANNEL_LABELS = [
        'instagram'    => ['Instagram',     'fab fa-instagram',  '#E4405F'],
        'tiktok'       => ['TikTok',        'fab fa-tiktok',     '#000000'],
        'x'            => ['X',             'fab fa-x-twitter',  '#000000'],
        'email'        => ['Email',         'fas fa-envelope',   '#3b82f6'],
        'form'         => ['Form',          'fas fa-clipboard-list', '#8b5cf6'],
        'biolink_dm'   => ['Biolink DM',    'fas fa-comment-dots','#a855f7'],
        'sponsorship'  => ['Sponsorship',   'fas fa-handshake',  '#10b981'],
    ];

    public const CATEGORY_LABELS = [
        'lead'        => ['Lead',        '#3b82f6'],
        'fan'         => ['Fan',         '#a855f7'],
        'sponsorship' => ['Sponsorship', '#10b981'],
        'support'     => ['Support',     '#f59e0b'],
        'spam'        => ['Spam',        '#ef4444'],
    ];

    public const DEFAULT_SLA_HOURS = [
        'lead'        => 24,
        'sponsorship' => 24,
        'support'     => 12,
        'fan'         => 72,
        'spam'        => null,
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'thread_id')->orderBy('sent_at');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(InboxThreadConversion::class, 'thread_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(InboxThreadAssignment::class, 'thread_id')->orderByDesc('created_at');
    }

    public function channelLabel(): string
    {
        return self::CHANNEL_LABELS[$this->channel ?? ''][0] ?? ucfirst((string) $this->channel);
    }

    public function channelIcon(): string
    {
        return self::CHANNEL_LABELS[$this->channel ?? ''][1] ?? 'fas fa-inbox';
    }

    public function channelColor(): string
    {
        return self::CHANNEL_LABELS[$this->channel ?? ''][2] ?? '#7c3aed';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category ?? 'lead'][0] ?? ucfirst((string) $this->category);
    }

    public function categoryColor(): string
    {
        return self::CATEGORY_LABELS[$this->category ?? 'lead'][1] ?? '#7c3aed';
    }

    public function isOverdue(): bool
    {
        return $this->sla_due_at && $this->sla_due_at->isPast() && $this->status === 'open';
    }
}
