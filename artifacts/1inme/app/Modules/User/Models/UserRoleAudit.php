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

    /**
     * Sentinel used by the source filter to mean "rows whose `source`
     * column is NULL" — i.e. ledger entries written by CLI seeders or
     * other code paths that don't tag themselves. NOT a value that
     * gets persisted to the database.
     */
    public const FILTER_SYSTEM       = 'system';

    /**
     * Sentinel that hides backfilled rows while still showing every
     * other source. Selectable from the same chip group as the
     * specific-source filters.
     */
    public const FILTER_NOT_BACKFILL = 'not_backfill';

    /**
     * Preset chip values for the date-range filter on the audit
     * snapshot panels. `RANGE_ALL` is a sentinel that means
     * "no date constraint" and is normalised to null by
     * `normaliseRangePreset()` so it never reaches the query.
     */
    public const RANGE_24H = '24h';
    public const RANGE_7D  = '7d';
    public const RANGE_30D = '30d';
    public const RANGE_ALL = 'all';

    /**
     * Selectable date-range presets surfaced as chips on the audit
     * snapshot panels, in the order chips should be rendered. Keys
     * are the URL-safe values used in `?audit_range=`; values are
     * short human labels.
     *
     * @return array<string, string>
     */
    public static function rangeFilters(): array
    {
        return [
            self::RANGE_24H => 'Last 24h',
            self::RANGE_7D  => 'Last 7 days',
            self::RANGE_30D => 'Last 30 days',
            self::RANGE_ALL => 'All time',
        ];
    }

    /**
     * Normalise an incoming `?audit_range=` query string parameter
     * to one of the bounded preset keys, or `null` when there is no
     * effective constraint (`'all'`, empty, or unknown values). The
     * `RANGE_ALL` sentinel is intentionally collapsed to null so the
     * query layer never has to special-case it.
     */
    public static function normaliseRangePreset(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === self::RANGE_ALL) {
            return null;
        }
        if (!array_key_exists($value, self::rangeFilters())) {
            return null;
        }
        return $value;
    }

    /**
     * Selectable filter values surfaced on the audit timeline UIs,
     * in the order chips should be rendered. Keys are URL-safe
     * filter values; values are short human labels.
     *
     * @return array<string, string>
     */
    public static function sourceFilters(): array
    {
        return [
            self::SOURCE_USER_ACCESS  => 'User access',
            self::SOURCE_ADMIN        => 'Back-office',
            self::FILTER_SYSTEM       => 'System / CLI',
            self::SOURCE_BACKFILL     => 'Backfilled',
            self::FILTER_NOT_BACKFILL => 'Hide backfilled',
        ];
    }

    /**
     * Normalise an incoming `?audit_source=` query string parameter
     * to one of the known filter keys, or `null` when no recognised
     * filter is in effect ("All sources"). Anything we don't
     * understand becomes null so a stale/typo'd link still renders
     * the timeline rather than a blank page.
     */
    public static function normaliseSourceFilter(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }
        return array_key_exists($value, self::sourceFilters()) ? $value : null;
    }

    /**
     * Apply the chip-bar source filter to an audit query.
     *
     * - `null`             → no filter (show everything).
     * - `user_access` /
     *   `admin` /
     *   `backfill`         → exact match on the `source` column.
     * - `system`           → rows where `source IS NULL` (CLI seeders,
     *                        legacy writes that pre-date the column).
     * - `not_backfill`     → everything except `source = 'backfill'`,
     *                        including NULL rows.
     */
    public function scopeBySourceFilter($query, ?string $filter)
    {
        $filter = self::normaliseSourceFilter($filter);
        if ($filter === null) {
            return $query;
        }
        if ($filter === self::FILTER_SYSTEM) {
            return $query->whereNull('source');
        }
        if ($filter === self::FILTER_NOT_BACKFILL) {
            return $query->where(function ($q) {
                $q->whereNull('source')
                  ->orWhere('source', '!=', self::SOURCE_BACKFILL);
            });
        }
        return $query->where('source', $filter);
    }

    /**
     * Apply the date-range filter to an audit query. Companion to
     * `scopeBySourceFilter` so the snapshot panels can scope a
     * timeline by both who acted and when, e.g. "admin changes in
     * the last 7 days".
     *
     * - `$preset` is one of the `RANGE_*` keys (or `null`/`'all'`/
     *   anything unknown, all of which mean "no preset"). When set,
     *   constrains `created_at >= now() - <preset window>`.
     * - `$from` and `$to` are free-form date strings parsed by
     *   Carbon (`YYYY-MM-DD` from the from/to picker, but anything
     *   Carbon understands is accepted). Each is independently
     *   skipped when blank or unparsable, so a typo'd value never
     *   500s the page — it just doesn't constrain that side.
     * - `$from` snaps to start-of-day and `$to` to end-of-day so an
     *   inclusive `2026-05-01 → 2026-05-01` range still returns rows
     *   created later that day.
     * - Preset and from/to compose: explicit endpoints further
     *   narrow a preset rather than overriding it, matching the
     *   composable behaviour of `scopeFiltered` on the dedicated
     *   audit page.
     */
    public function scopeBetweenDates($query, ?string $preset, ?string $from = null, ?string $to = null)
    {
        $preset = self::normaliseRangePreset($preset);

        $from = trim((string) $from);
        if ($from !== '') {
            try {
                $query->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
            } catch (\Throwable $e) {
                // ignore unparsable input rather than 500
            }
        }

        $to = trim((string) $to);
        if ($to !== '') {
            try {
                $query->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
            } catch (\Throwable $e) {
                // ignore unparsable input rather than 500
            }
        }

        if ($preset !== null) {
            $cutoff = match ($preset) {
                self::RANGE_24H => now()->subDay(),
                self::RANGE_7D  => now()->subDays(7),
                self::RANGE_30D => now()->subDays(30),
                default         => null,
            };
            if ($cutoff !== null) {
                $query->where('created_at', '>=', $cutoff);
            }
        }

        return $query;
    }

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
