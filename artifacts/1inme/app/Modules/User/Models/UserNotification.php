<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        if ($type === 'workspace_access_request' || $type === 'workspace_member_left') {
            return route('user.team.index');
        }

        return null;
    }

    /**
     * Short, plain-text preview of what this notification is about — no
     * markup or action links, just the same per-type copy shown on the
     * full notifications page condensed to a single line. Powers the
     * header bell dropdown; the full index view keeps its own richer
     * per-type markup (bold names, quoted snippets, action buttons).
     */
    public function previewText(): string
    {
        return self::resolvePreviewText($this->data ?? [], $this->type);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function resolvePreviewText(array $data, ?string $type = null): string
    {
        switch ($type) {
            case 'new_follower':
                return ($data['follower_name'] ?? 'Someone') . ' started following you.';
            case 'follower_update':
                return ($data['creator_name'] ?? 'A creator') . ' ' . ($data['message'] ?? 'has new activity');
            case 'social_connection_broken':
                return $data['message'] ?? 'A social connection needs your attention.';
            case 'workspace_access_request':
                return ($data['requester_name'] ?? 'A teammate') . ' is asking for access to ' . ($data['workspace_name'] ?? 'a workspace') . '.';
            case 'task_assigned':
                return ($data['assigner'] ?? 'Someone') . ' assigned you to a task in ' . ($data['board_name'] ?? 'a board') . ': ' . Str::limit($data['message'] ?? '', 80);
            case 'task_mention':
                return ($data['mentioner'] ?? 'Someone') . ' mentioned you in ' . ($data['board_name'] ?? 'a board') . ': ' . Str::limit($data['snippet'] ?? $data['message'] ?? '', 100);
            case 'task_due':
                return 'A card you\'re assigned to is due today in ' . ($data['board_name'] ?? 'a board') . ': ' . Str::limit($data['message'] ?? '', 80);
            case 'task_overdue':
                return 'Overdue: a card you\'re assigned to in ' . ($data['board_name'] ?? 'a board') . ' — ' . Str::limit($data['message'] ?? '', 80);
            case 'billing.subscription_update':
                return $data['message'] ?? 'Your subscription has changed.';
            case 'delivery_project.comment':
                return $data['message'] ?? 'A client commented on a delivery project';
            case 'event_exchange_request':
                return ($data['requester_name'] ?? 'Someone') . ' wants to exchange contacts with you at ' . ($data['event_title'] ?? 'an event') . '.';
            case 'event_exchange_accepted':
                return ($data['acceptor_name'] ?? 'Someone') . ' accepted your contact exchange at ' . ($data['event_title'] ?? 'an event') . '.';
            case 'account.verification_approved':
                return $data['message'] ?? 'Your verification request was approved.';
            case 'account.verification_rejected':
                return $data['message'] ?? 'Your verification request was not approved.';
            default:
                return $data['message'] ?? ($type ?? 'Notification');
        }
    }
}
