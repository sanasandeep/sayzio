<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #3060 — a one-click applyable play attached to a Marketing
 * Strategy. `payload` carries the type-specific parameters used to build
 * the real object; the result is recorded via applied_ref_* so the UI can
 * deep link and never double-apply.
 */
class MarketingStrategySuggestion extends Model
{
    protected $table = 'marketing_strategy_suggestions';

    public const TYPE_CREATE_LINK  = 'create_link';
    public const TYPE_ADD_BLOCK    = 'add_block';
    public const TYPE_ATTACH_PIXEL = 'attach_pixel';
    public const TYPE_DRAFT_POST   = 'draft_post';
    /** Task #3281 — an ordered multi-step funnel applied in one click. */
    public const TYPE_FUNNEL       = 'funnel';

    public const TYPES = [
        self::TYPE_CREATE_LINK,
        self::TYPE_ADD_BLOCK,
        self::TYPE_ATTACH_PIXEL,
        self::TYPE_DRAFT_POST,
        self::TYPE_FUNNEL,
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPLIED   = 'applied';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ERROR     = 'error';
    /** A funnel where some but not all steps landed. */
    public const STATUS_PARTIAL   = 'partial';

    protected $fillable = [
        'strategy_id', 'type', 'title', 'description', 'payload', 'steps', 'step_results',
        'status', 'applied_ref_type', 'applied_ref_id', 'error', 'applied_at',
    ];

    protected $casts = [
        'payload'        => 'array',
        'steps'          => 'array',
        'step_results'   => 'array',
        'applied_ref_id' => 'integer',
        'applied_at'     => 'datetime',
    ];

    public function strategy()
    {
        return $this->belongsTo(MarketingStrategy::class, 'strategy_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFunnel(): bool
    {
        return $this->type === self::TYPE_FUNNEL;
    }

    /** Friendly label for the action type. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CREATE_LINK  => 'Create link',
            self::TYPE_ADD_BLOCK    => 'Add biolink block',
            self::TYPE_ATTACH_PIXEL => 'Attach pixel',
            self::TYPE_DRAFT_POST   => 'Draft scheduled post',
            self::TYPE_FUNNEL       => 'Build funnel',
            default                 => ucwords(str_replace('_', ' ', (string) $this->type)),
        };
    }
}
