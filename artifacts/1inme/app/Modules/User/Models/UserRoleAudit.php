<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Append-only ledger row for a single role attach/detach on a
 * user account. Written by `UserRoleAuditLogger` from both the
 * self-service "User access" page and the back-office admin
 * user-detail page, then surfaced read-only from the same pages
 * so reviewers can see who promoted/demoted whom.
 */
class UserRoleAudit extends Model
{
    public const ACTION_ATTACHED = 'attached';
    public const ACTION_DETACHED = 'detached';

    public const SOURCE_USER_ACCESS = 'user_access';
    public const SOURCE_ADMIN       = 'admin';
    public const SOURCE_BACKFILL    = 'backfill';
    // Auto-generated detach rows written by the model `deleting` hooks
    // when a user account or a role is deleted, so the cascade on the
    // `user_roles` pivot doesn't silently erase the audit trail.
    public const SOURCE_USER_DELETED = 'user_deleted';
    public const SOURCE_ROLE_DELETED = 'role_deleted';

    protected $table = 'user_role_audits';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'actor_admin_id', 'actor_guard',
        'actor_name', 'actor_email',
        'target_user_id',
        'role_id', 'role_slug', 'role_name',
        'action', 'source', 'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorAdmin()
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Best-effort label for the actor — prefers the live record's
     * current name/email, falls back to the snapshot, and finally
     * to "System" when neither side is populated (e.g. CLI seeders).
     */
    public function actorLabel(): string
    {
        if ($this->actor_guard === 'web' && $this->actorUser) {
            return $this->actorUser->name ?: ($this->actorUser->email ?: ('User #' . $this->actor_user_id));
        }
        if ($this->actor_guard === 'admin' && $this->actorAdmin) {
            return ($this->actorAdmin->name ?: $this->actorAdmin->email ?: ('Admin #' . $this->actor_admin_id))
                . ' (admin)';
        }
        if ($this->actor_name) {
            return $this->actor_guard === 'admin'
                ? $this->actor_name . ' (admin)'
                : $this->actor_name;
        }
        return 'System';
    }

    /**
     * Apply the dedicated audit-page filters (actor, target, role
     * slug, action, source, date range) to a query. Each filter is
     * skipped when its value is empty/invalid, so the same call site
     * works for both the on-screen list and the CSV export.
     *
     * Free-text actor / target searches match the snapshotted
     * actor name/email columns and the live target user's name/email
     * respectively; passing a numeric value targets the id columns
     * directly so reviewers can paste an id from the URL.
     *
     * @param array{
     *   actor?: ?string, target?: ?string, role?: ?string,
     *   action?: ?string, source?: ?string,
     *   from?: ?string, to?: ?string,
     * } $f
     */
    public function scopeFiltered(Builder $query, array $f): Builder
    {
        $actor = trim((string) ($f['actor'] ?? ''));
        if ($actor !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $actor) . '%';
            $query->where(function ($q) use ($like, $actor) {
                $q->where('actor_name', 'like', $like)
                  ->orWhere('actor_email', 'like', $like);
                if (ctype_digit($actor)) {
                    $id = (int) $actor;
                    $q->orWhere('actor_user_id', $id)
                      ->orWhere('actor_admin_id', $id);
                }
            });
        }

        $target = trim((string) ($f['target'] ?? ''));
        if ($target !== '') {
            if (ctype_digit($target)) {
                $query->where('target_user_id', (int) $target);
            } else {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $target) . '%';
                $query->whereHas('targetUser', function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                      ->orWhere('email', 'like', $like);
                });
            }
        }

        $role = trim((string) ($f['role'] ?? ''));
        if ($role !== '') {
            $query->where('role_slug', $role);
        }

        $action = (string) ($f['action'] ?? '');
        if (in_array($action, [self::ACTION_ATTACHED, self::ACTION_DETACHED], true)) {
            $query->where('action', $action);
        }

        $source = (string) ($f['source'] ?? '');
        if (in_array($source, [self::SOURCE_USER_ACCESS, self::SOURCE_ADMIN], true)) {
            $query->where('source', $source);
        }

        $from = trim((string) ($f['from'] ?? ''));
        if ($from !== '') {
            try {
                $query->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
            } catch (\Throwable $e) {
                // ignore unparsable input rather than 500
            }
        }

        $to = trim((string) ($f['to'] ?? ''));
        if ($to !== '') {
            try {
                $query->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
            } catch (\Throwable $e) {
                // ignore unparsable input rather than 500
            }
        }

        return $query;
    }

    /**
     * Distinct role slugs that appear anywhere in the ledger — used
     * to populate the role filter dropdown on the audit page.
     * Includes slugs of roles that have since been deleted, since
     * the audit row snapshots them.
     *
     * @return array<int, string>
     */
    public static function distinctRoleSlugs(): array
    {
        return static::query()
            ->whereNotNull('role_slug')
            ->where('role_slug', '!=', '')
            ->distinct()
            ->orderBy('role_slug')
            ->pluck('role_slug')
            ->all();
    }

    /**
     * Stream the (already-filtered) query to the browser as a CSV
     * download. Uses chunked iteration so a multi-thousand-row
     * security export doesn't load the whole table into memory.
     */
    public static function streamCsv(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Timestamp (UTC)',
                'Action',
                'Source',
                'Actor guard',
                'Actor id',
                'Actor name',
                'Actor email',
                'Target user id',
                'Target user name',
                'Target user email',
                'Role slug',
                'Role name',
                'IP',
            ]);

            $query->orderByDesc('created_at')->chunk(500, function ($rows) use ($out) {
                $rows->load('targetUser:id,name,email');
                foreach ($rows as $r) {
                    $actorId = $r->actor_guard === 'admin'
                        ? $r->actor_admin_id
                        : $r->actor_user_id;
                    fputcsv($out, [
                        optional($r->created_at)->toIso8601String(),
                        $r->action,
                        $r->source,
                        $r->actor_guard,
                        $actorId,
                        $r->actor_name,
                        $r->actor_email,
                        $r->target_user_id,
                        optional($r->targetUser)->name,
                        optional($r->targetUser)->email,
                        $r->role_slug,
                        $r->role_name,
                        $r->ip,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
