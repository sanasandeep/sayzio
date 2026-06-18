<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNotification extends Model
{
    use SoftDeletes;

    // Dismissing a notification soft-deletes the row (stamps `dismissed_at`)
    // instead of permanently removing it, so an accidental dismissal can be
    // undone. The SoftDeletes trait wires the global scope, restore(),
    // onlyTrashed(), etc. to this column automatically.
    const DELETED_AT = 'dismissed_at';

    public $timestamps = false;
    protected $fillable = ['user_id', 'type', 'data', 'read_at', 'emailed_at', 'created_at', 'dismissed_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'emailed_at' => 'datetime', 'created_at' => 'datetime', 'dismissed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Resolve the single canonical "thing this notification is about" URL.
     *
     * Most notifications stash their action target in the data payload under
     * a handful of historically-used keys (`url` is canonical, `target_url`
     * is a legacy alias, `fix_url` is used by the social-connection alerts).
     * A few types derive their destination from their kind rather than a
     * stored URL (e.g. a workspace access request always points at the team
     * page). This is the one place web, the open-redirect route, and the
     * REST API all consult so the row, its primary link, and mobile stay in
     * lockstep. Returns null when there is nothing meaningful to open.
     */
    public function targetUrl(): ?string
    {
        return self::resolveTargetUrl($this->data ?? [], $this->type);
    }

    /**
     * Resolve the canonical target URL from a raw data payload + type,
     * without needing a persisted model. This keeps the web row, the
     * open-redirect route, the REST feed, and the push payload assembly
     * (so a tapped push deep-links to the exact same place) all in
     * lockstep off one implementation. Returns null when there is nothing
     * meaningful to open.
     *
     * @param array<string, mixed> $data
     */
    public static function resolveTargetUrl(array $data, ?string $type = null): ?string
    {
        foreach (['url', 'target_url', 'fix_url'] as $key) {
            $candidate = $data[$key] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        // Type-derived destinations for notifications that don't store a URL.
        if ($type === 'workspace_access_request') {
            return route('user.team.index');
        }

        return null;
    }
}
