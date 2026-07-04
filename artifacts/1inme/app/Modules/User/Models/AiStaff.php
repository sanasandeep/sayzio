<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task #3523 — a user-configurable AI staff member: a name + personality
 * bound to one of the four domains below. The domain drives which live
 * data (via AiMindFeatureAdapter) and which actions (billing draft/chase,
 * contacts summarize/follow-up, inbox autopilot, general Q&A) the staff
 * member can use — there is no separate per-staff model/runtime, all
 * chat/actions flow through AiStaffRuntime + the existing OpenAiService.
 */
class AiStaff extends Model
{
    protected $table = 'ai_staff';

    public const DOMAIN_BILLING  = 'billing';
    public const DOMAIN_CONTACTS = 'contacts';
    public const DOMAIN_INBOX    = 'inbox';
    public const DOMAIN_GENERAL  = 'general';

    public const DOMAINS = [
        self::DOMAIN_BILLING  => 'Billing & Invoices',
        self::DOMAIN_CONTACTS => 'Contacts & Leads',
        self::DOMAIN_INBOX    => 'Inbox',
        self::DOMAIN_GENERAL  => 'General Assistant',
    ];

    public const DOMAIN_DESCRIPTIONS = [
        self::DOMAIN_BILLING  => 'Drafts invoices from a prompt and surfaces/chases unpaid or overdue ones (you always confirm before anything sends).',
        self::DOMAIN_CONTACTS => 'Summarizes contacts & leads, suggests next steps, and drafts follow-up messages.',
        self::DOMAIN_INBOX    => 'Wires into your Inbox Agent settings to triage and draft/send replies for incoming messages.',
        self::DOMAIN_GENERAL  => 'Answers questions about your Sayzio account, grounded in your live workspace data.',
    ];

    protected $fillable = [
        'user_id', 'name', 'domain', 'instructions', 'is_disabled', 'config', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_disabled'  => 'boolean',
            'config'       => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(AiStaffSuggestion::class);
    }

    public function isEnabled(): bool
    {
        return !$this->is_disabled;
    }

    public function domainLabel(): string
    {
        return self::DOMAINS[$this->domain] ?? ucfirst((string) $this->domain);
    }

    /** The AiPlanAccess / AiFeatureCatalog / AiUsageCharger feature key for this staff's domain. */
    public function featureKey(): string
    {
        return 'ai_staff_' . $this->domain;
    }

    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
