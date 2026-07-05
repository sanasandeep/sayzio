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
