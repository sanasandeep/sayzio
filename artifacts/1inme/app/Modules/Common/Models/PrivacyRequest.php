<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A visitor/user request to delete their account or download all their
 * data, fulfilled after staff approval. See the create-table migration
 * for the column-level documentation and the full status lifecycle.
 */
class PrivacyRequest extends Model
{
    public const TYPE_DELETION = 'deletion';
    public const TYPE_EXPORT   = 'export';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_VERIFIED   = 'verified';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_BLOCKED    = 'blocked';

    /** How long an unverified request's email link stays valid. */
    public const VERIFY_TTL_HOURS = 48;

    /** Cooling-off window between deletion approval and irreversible erasure. */
    public const DELETION_GRACE_DAYS = 7;

    /** How long a generated export archive download link stays live. */
    public const DOWNLOAD_TTL_DAYS = 7;

    protected $fillable = [
        'type', 'user_id', 'email', 'reason', 'status',
        'verification_token', 'token_expires_at', 'verified_at',
        'scheduled_at', 'approved_by', 'approved_at',
        'rejection_reason', 'rejected_at', 'completed_at', 'failure_reason',
        'download_token', 'archive_path', 'download_expires_at',
        'ip', 'audit',
    ];

    protected $casts = [
        'token_expires_at'    => 'datetime',
        'verified_at'         => 'datetime',
        'scheduled_at'        => 'datetime',
        'approved_at'         => 'datetime',
        'rejected_at'         => 'datetime',
        'completed_at'        => 'datetime',
        'download_expires_at' => 'datetime',
        'audit'               => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isDeletion(): bool
    {
        return $this->type === self::TYPE_DELETION;
    }

    public function isExport(): bool
    {
        return $this->type === self::TYPE_EXPORT;
    }

    /** Human label for the request type. */
    public function typeLabel(): string
    {
        return $this->isDeletion() ? 'Account deletion' : 'Data export';
    }

    /**
     * Append an entry to the immutable audit trail. Each entry records the
     * event, an optional actor + note, and a timestamp. Saved immediately
     * unless $persist is false (the caller will save).
     */
    public function recordAudit(string $event, ?string $actor = null, ?string $note = null, bool $persist = true): void
    {
        $trail = is_array($this->audit) ? $this->audit : [];
        $trail[] = array_filter([
            'event' => $event,
            'actor' => $actor,
            'note'  => $note,
            'at'    => now()->toIso8601String(),
        ], fn ($v) => $v !== null);
        $this->audit = $trail;
        if ($persist) {
            $this->save();
        }
    }

    /** Match a submitted email to an existing account (lowercased). */
    public static function matchUser(string $email): ?User
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        return User::whereRaw('lower(email) = ?', [$email])->first();
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [
            self::STATUS_VERIFIED,
            self::STATUS_APPROVED,
            self::STATUS_PROCESSING,
        ], true);
    }

    public function downloadIsLive(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->isExport()
            && $this->download_token
            && $this->archive_path
            && $this->download_expires_at
            && $this->download_expires_at->isFuture();
    }
}
