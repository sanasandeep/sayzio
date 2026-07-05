<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Task #3564 — a lightweight shared project spun up from a finalized sale.
 * Distinct from the link-organisation {@see Project} model (name collision only).
 */
class DeliveryProject extends Model
{
    use BelongsToWorkspace;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED  = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE    => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_ARCHIVED  => 'Archived',
    ];

    /** Task #3584 — the project's own calendar privacy tiers. */
    public const CALENDAR_PRIVACY_PROJECT   = 'project';
    public const CALENDAR_PRIVACY_WORKSPACE = 'workspace';
    public const CALENDAR_PRIVACY_PUBLIC    = 'public';

    public const CALENDAR_PRIVACIES = [
        self::CALENDAR_PRIVACY_PROJECT   => 'Project only',
        self::CALENDAR_PRIVACY_WORKSPACE => 'Workspace',
        self::CALENDAR_PRIVACY_PUBLIC    => 'Public',
    ];

    /** Plain-language copy for the privacy selector UI. */
    public const CALENDAR_PRIVACY_COPY = [
        self::CALENDAR_PRIVACY_PROJECT   => 'Only people working on this project can see its schedule, plus your client via the share link.',
        self::CALENDAR_PRIVACY_WORKSPACE => 'Everyone on your workspace can see this schedule — it also shows up in their My Calendar.',
        self::CALENDAR_PRIVACY_PUBLIC    => 'Anyone with the link can view and subscribe to this schedule (Google, Apple, Outlook).',
    ];

    protected $fillable = [
        'workspace_id', 'created_by_user_id',
        'sourceable_type', 'sourceable_id',
        'title', 'description', 'status',
        'client_user_id', 'client_name', 'client_email', 'share_token',
        'completed_at',
        'warranty_expires_at', 'warranty_reminder_days',
        'warranty_reminder_sent_at', 'warranty_expired_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at'                 => 'datetime',
            'warranty_expires_at'          => 'date',
            'warranty_reminder_days'       => 'integer',
            'warranty_reminder_sent_at'    => 'datetime',
            'warranty_expired_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $project) {
            if (empty($project->share_token)) {
                $project->share_token = static::newShareToken();
            }
        });
    }

    public static function newShareToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (static::query()->withoutGlobalScope('workspace')->where('share_token', $token)->exists());

        return $token;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DeliveryProjectTask::class, 'project_id')->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DeliveryProjectComment::class, 'project_id')->orderBy('id');
    }

    /**
     * Task #3574 — eager-load `unanswered_client_count`: the number of client
     * comments posted after the most recent team reply (0 = the team is caught
     * up, or the client never asked anything). Comments are inserted in
     * chronological order so id ordering is chronological.
     */
    public function scopeWithUnansweredClientCount($query)
    {
        $table = (new DeliveryProjectComment)->getTable();

        return $query->withCount(['comments as unanswered_client_count' => function ($q) use ($table) {
            $q->where('author_role', DeliveryProjectComment::ROLE_CLIENT)
              ->whereRaw(
                  "{$table}.id > (select coalesce(max(t.id), 0) from {$table} as t"
                  . " where t.project_id = {$table}.project_id and t.author_role = ?)",
                  [DeliveryProjectComment::ROLE_TEAM]
              );
        }]);
    }

    /**
     * Task #3574 — client comments still awaiting a team reply. Prefers the
     * eager-loaded {@see scopeWithUnansweredClientCount} column, then a loaded
     * comments relation, and finally a direct query as a last resort.
     */
    public function unansweredClientCount(): int
    {
        if (array_key_exists('unanswered_client_count', $this->attributes)) {
            return (int) $this->attributes['unanswered_client_count'];
        }

        $comments = $this->relationLoaded('comments') ? $this->comments : $this->comments()->get();
        $lastTeamId = (int) $comments
            ->where('author_role', DeliveryProjectComment::ROLE_TEAM)
            ->max('id');

        return $comments
            ->where('author_role', DeliveryProjectComment::ROLE_CLIENT)
            ->filter(fn ($c) => (int) $c->id > $lastTeamId)
            ->count();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('sourceable');
    }

    public function calendar(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Calendar::class, 'delivery_project_id');
    }

    /**
     * Task #3584 — the project's calendar, created on first use (lazily, so
     * projects with no dated tasks never grow an empty calendar row).
     */
    public function ensureCalendar(): Calendar
    {
        $calendar = $this->calendar()->first();
        if ($calendar) {
            return $calendar;
        }

        return Calendar::create([
            'delivery_project_id' => $this->id,
            'workspace_id'        => $this->workspace_id,
            'user_id'             => $this->created_by_user_id,
            'title'               => $this->title . ' — Schedule',
            'slug'                => 'dp-' . $this->id,
            'description'         => 'Auto-generated schedule for delivery project "' . $this->title . '".',
            'is_public'           => false,
            'privacy'             => self::CALENDAR_PRIVACY_PROJECT,
        ]);
    }

    public function calendarPrivacy(): string
    {
        return $this->calendar?->privacy ?? self::CALENDAR_PRIVACY_PROJECT;
    }

    public function calendarPrivacyLabel(): string
    {
        return self::CALENDAR_PRIVACIES[$this->calendarPrivacy()] ?? 'Project only';
    }

    /** Overall project progress = average of task progress (0 when no tasks). */
    public function progressPercent(): int
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        if ($tasks->isEmpty()) {
            return 0;
        }
        return (int) round($tasks->avg('progress'));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Human label for the sale this project came from, e.g. "Invoice #INV-1". */
    public function sourceLabel(): ?string
    {
        return match ($this->sourceable_type) {
            Invoice::class         => 'Invoice',
            ProductOrder::class    => 'Product order',
            RestaurantOrder::class => 'Restaurant order',
            StoreOrder::class      => 'Store order',
            FormSubmission::class  => 'Form submission',
            default                => $this->sourceable_type ? class_basename($this->sourceable_type) : null,
        };
    }

    /** Whether a warranty window is configured and still in the future. */
    public function warrantyActive(): bool
    {
        return $this->warranty_expires_at !== null
            && $this->warranty_expires_at->endOfDay()->isFuture();
    }

    public function warrantyExpired(): bool
    {
        return $this->warranty_expires_at !== null
            && $this->warranty_expires_at->endOfDay()->isPast();
    }
}
