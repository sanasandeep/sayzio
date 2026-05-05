<?php

use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot backfill so the role-audit timeline isn't blank for
 * accounts that received their roles before `user_role_audits`
 * existed (notably the `user-admin` rows seeded by
 * 2027_05_10_000001_create_user_roles_pivot_seed_user_admin.php).
 *
 * For every row in `user_roles` that doesn't already have an
 * 'attached' audit entry for the same (target_user_id, role_id),
 * we insert a synthetic "system" attached row using the pivot's
 * `created_at` so the timeline ordering is honest. Actor fields
 * are all null and `source = 'backfill'` so reviewers can tell
 * these rows apart from real human-driven attaches.
 *
 * The exists-check makes this safe to re-run: pivots that were
 * already backfilled (or that have any genuine 'attached' audit)
 * are skipped, and pivots created after this migration ran will
 * have their normal real-time audit row from UserRoleAuditLogger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_roles') || !Schema::hasTable('user_role_audits')) {
            return;
        }

        DB::table('user_roles')
            ->select('user_roles.user_id', 'user_roles.role_id', 'user_roles.created_at',
                     'roles.slug as role_slug', 'roles.name as role_name')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->orderBy('user_roles.user_id')
            ->orderBy('user_roles.role_id')
            ->chunk(500, function ($pivots) {
                $now  = now();
                $rows = [];

                foreach ($pivots as $pivot) {
                    $alreadyAudited = DB::table('user_role_audits')
                        ->where('target_user_id', $pivot->user_id)
                        ->where('role_id', $pivot->role_id)
                        ->where('action', UserRoleAudit::ACTION_ATTACHED)
                        ->exists();

                    if ($alreadyAudited) {
                        continue;
                    }

                    $rows[] = [
                        'actor_user_id'  => null,
                        'actor_admin_id' => null,
                        'actor_guard'    => null,
                        'actor_name'     => null,
                        'actor_email'    => null,
                        'target_user_id' => $pivot->user_id,
                        'role_id'        => $pivot->role_id,
                        // Slug is NOT NULL; fall back to a stable synthetic
                        // marker if the role record was deleted before the
                        // backfill ran.
                        'role_slug'      => $pivot->role_slug ?? ('role#' . $pivot->role_id),
                        'role_name'      => $pivot->role_name,
                        'action'         => UserRoleAudit::ACTION_ATTACHED,
                        'source'         => UserRoleAudit::SOURCE_BACKFILL,
                        'ip'             => null,
                        // Honour the pivot's original timestamp so the audit
                        // timeline reflects when the grant actually happened
                        // rather than when this migration ran.
                        'created_at'     => $pivot->created_at ?? $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('user_role_audits')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // One-shot, additive backfill. Removing rows on rollback would
        // also delete any human-meaningful records that happened to
        // share the 'backfill' source label, so we intentionally no-op.
    }
};
