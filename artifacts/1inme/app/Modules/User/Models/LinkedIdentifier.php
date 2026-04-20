<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One verified email, phone number, or social-provider identity attached
 * to a user. Login by any verified identifier resolves to its owning
 * account; the row marked is_primary is the canonical email/phone shown
 * on the user record itself.
 */
class LinkedIdentifier extends Model
{
    protected $fillable = [
        'user_id', 'kind', 'value', 'provider', 'external_id',
        'verified_at', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_primary'  => 'boolean',
        ];
    }

    public const KINDS = ['email', 'phone', 'social'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Normalise a raw identifier value to the canonical stored form. */
    public static function normalize(string $kind, string $value, ?string $provider = null, ?string $externalId = null): string
    {
        if ($kind === 'email') return strtolower(trim($value));
        if ($kind === 'phone') {
            // Strip whitespace AND common formatting chars so that
            // "+1 (555) 123-4567" and "+15551234567" hash to the same row.
            $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', trim($value));
            return $cleaned ?? trim($value);
        }
        if ($kind === 'social') {
            $ext = $externalId ?: $value;
            return ($provider ?: '') . ':' . $ext;
        }
        return $value;
    }

    /** Resolve the User who owns the verified identifier, or null. */
    public static function resolveUser(string $kind, string $value, ?string $provider = null, ?string $externalId = null): ?User
    {
        $needle = self::normalize($kind, $value, $provider, $externalId);
        $row = self::where('kind', $kind)
            ->where('value', $needle)
            ->whereNotNull('verified_at')
            ->first();
        return $row?->user;
    }

    /** Pretty label for display ("you@example.com" / "+15551234" / "Instagram (@handle)"). */
    public function displayLabel(): string
    {
        if ($this->kind === 'social') {
            $label = SocialAccountConnection::platformLabel((string) $this->provider);
            // Try to fish a friendly handle out of the user's connection row.
            $conn = SocialAccountConnection::where('user_id', $this->user_id)
                ->where('platform', $this->provider)
                ->where(function ($q) {
                    $q->where('external_id', $this->external_id)
                      ->orWhere('handle', $this->external_id);
                })
                ->first();
            $h = $conn?->handle ?: $this->external_id;
            return $h ? ($label . ' (@' . ltrim((string) $h, '@') . ')') : $label;
        }
        return (string) $this->value;
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'email'  => 'Email',
            'phone'  => 'Phone',
            'social' => 'Social',
            default  => ucfirst($this->kind),
        };
    }
}
