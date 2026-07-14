<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per account that can never be deleted or suspended. Keyed by
 * lowercased email so a single entry protects both the web {@see User}
 * and the matching back-office {@see Admin} (the two auth pools are
 * bridged by a shared email), OR by `user_id` for accounts that signed
 * up without an email (users.email null — WhatsApp/mobile-only signups).
 * Every entry carries at least one key (email or user_id).
 *
 * `locked` rows are the hard-locked seeds (superadmin + demo) that can
 * never be removed from protection — see the create-table migration.
 * Non-locked rows are managed by a superadmin from the admin panel.
 */
class ProtectedAccount extends Model
{
    protected $fillable = ['email', 'user_id', 'locked', 'label', 'created_by'];

    protected $casts = [
        'locked' => 'boolean',
    ];

    /** Normalise an email for storage/lookup (trim + lowercase). */
    public static function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Whether the given email is on the protected list.
     */
    public static function isProtectedEmail(?string $email): bool
    {
        $email = static::normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        return static::query()->whereRaw('lower(email) = ?', [$email])->exists();
    }

    /**
     * Whether the given user id has an id-keyed protected entry.
     */
    public static function isProtectedUserId(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return static::query()->where('user_id', $userId)->exists();
    }

    /**
     * Whether the given subject (a User, an Admin, or an email string)
     * is protected. The single guard helper used by every delete/suspend
     * path for defense in depth. Users are matched by email OR by an
     * id-keyed entry (so email-less accounts can be protected too).
     */
    public static function isProtected(User|Admin|string|null $subject): bool
    {
        if ($subject === null) {
            return false;
        }

        $email = is_string($subject) ? $subject : ($subject->email ?? null);

        if (static::isProtectedEmail($email)) {
            return true;
        }

        if ($subject instanceof User) {
            return static::isProtectedUserId($subject->id);
        }

        return false;
    }

    /** Whether this entry is hard-locked and cannot be removed. */
    public function isLocked(): bool
    {
        return (bool) $this->locked;
    }
}
