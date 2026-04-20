<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reassigns every owned record from a "secondary" duplicate account to a
 * "primary" survivor account, moves the secondary's verified identifiers
 * onto the primary, applies the chosen plan, cancels the loser plan, and
 * deletes the secondary user — all in a single transaction.
 *
 * The set of owned tables is discovered dynamically by inspecting which
 * tables in the schema carry a `user_id` column, with a few extra special
 * cases (foreign keys named differently, e.g. follows.creator_id).
 */
class AccountMergeService
{
    /**
     * Tables/columns owned by the user that aren't `user_id`.
     * `dedupe` lists columns to dedupe against to avoid violating
     * (user_id, X) unique constraints when both users own a row.
     */
    public const EXTRA_FK_COLUMNS = [
        ['table' => 'follows',      'column' => 'creator_id'],
        ['table' => 'follows',      'column' => 'follower_id'],
        ['table' => 'link_clicks',  'column' => 'viewer_user_id'],
        ['table' => 'referrals',    'column' => 'referrer_id'],
        ['table' => 'referrals',    'column' => 'referred_user_id'],
        ['table' => 'users',        'column' => 'referrer_id'],
    ];

    /**
     * Build a preview of what would move from $secondary to $primary.
     * Pure read — does not modify anything.
     *
     * @return array{
     *   counts: array<string,int>,
     *   identifiers: array<int,LinkedIdentifier>,
     *   primary_has_paid_plan: bool,
     *   secondary_has_paid_plan: bool,
     *   primary: User,
     *   secondary: User,
     * }
     */
    public function preview(User $primary, User $secondary): array
    {
        $counts = [];
        foreach ($this->ownedTables() as [$table, $column]) {
            try {
                $n = (int) DB::table($table)->where($column, $secondary->id)->count();
            } catch (\Throwable $e) {
                $n = 0;
            }
            if ($n > 0) {
                $counts[$table . '.' . $column] = ($counts[$table . '.' . $column] ?? 0) + $n;
            }
        }

        return [
            'counts'                  => $counts,
            'identifiers'             => $secondary->verifiedIdentifiers()->get()->all(),
            'primary_has_paid_plan'   => $primary->hasActivePlan() && !$primary->isOnFreePlan(),
            'secondary_has_paid_plan' => $secondary->hasActivePlan() && !$secondary->isOnFreePlan(),
            'primary'                 => $primary,
            'secondary'               => $secondary,
        ];
    }

    /**
     * Execute the merge.
     *
     * @param string $keepPlanFrom 'primary' | 'secondary' — which side's
     *                              plan survives (the other is cancelled).
     * @return array Summary including per-table reassigned counts.
     */
    public function merge(User $primary, User $secondary, string $keepPlanFrom = 'primary'): array
    {
        if ($primary->id === $secondary->id) {
            throw new \InvalidArgumentException('Cannot merge an account into itself.');
        }
        // Defence in depth: refuse for the super-admin role and for any
        // future role that contains "admin" (e.g. an "admin" or
        // "billing_admin" role added later). Merging an admin account
        // can elevate a regular user to admin via the data move and is
        // explicitly out of scope for this user-initiated flow.
        foreach ([$primary, $secondary] as $u) {
            if ($u->isSuperAdmin() || (is_string($u->role) && str_contains(strtolower($u->role), 'admin'))) {
                throw new \RuntimeException('Admin accounts cannot be merged.');
            }
        }

        // Server-side normalisation of the plan choice so a crafted POST
        // can't override the obvious behaviour: if only one side has a
        // paid plan, that plan must be the one kept (and we never
        // forfeit a paid plan in favour of a free one).
        $primaryPaid   = $primary->hasActivePlan() && !$primary->isOnFreePlan();
        $secondaryPaid = $secondary->hasActivePlan() && !$secondary->isOnFreePlan();
        if ($secondaryPaid && !$primaryPaid) {
            $keepPlanFrom = 'secondary';
        } elseif ($primaryPaid && !$secondaryPaid) {
            $keepPlanFrom = 'primary';
        }
        if (!in_array($keepPlanFrom, ['primary', 'secondary'], true)) {
            $keepPlanFrom = 'primary';
        }

        return DB::transaction(function () use ($primary, $secondary, $keepPlanFrom) {
            $reassigned = [];
            foreach ($this->ownedTables() as [$table, $column]) {
                // For tables that have unique (user_id, X)-style
                // constraints, naively updating user_id can collide.
                // We delete the secondary's would-be-duplicate rows when
                // the primary already has the equivalent row, then
                // reassign the remainder. Any failure here is a hard
                // error — the surrounding transaction rolls back so
                // both accounts are left untouched on partial failure.
                $count = $this->reassignTable($table, $column, $primary->id, $secondary->id);
                if ($count > 0) $reassigned[$table . '.' . $column] = $count;
            }

            // Move verified identifiers onto the primary. The secondary's
            // primary marker is cleared — the primary keeps its own.
            DB::table('linked_identifiers')
                ->where('user_id', $secondary->id)
                ->update(['user_id' => $primary->id, 'is_primary' => false, 'updated_at' => now()]);

            // Apply chosen plan, cancel the other.
            if ($keepPlanFrom === 'secondary') {
                $primary->plan_id        = $secondary->plan_id;
                $primary->billing_cycle  = $secondary->billing_cycle;
                $primary->plan_expires_at = $secondary->plan_expires_at;
                $primary->trial_ends_at  = $secondary->trial_ends_at;
                $primary->save();
            }
            // Either way, the loser plan is forfeited — clear plan fields
            // on the secondary before delete so any cascading-on-plan
            // logic doesn't see a phantom active subscription.
            $secondary->plan_id         = null;
            $secondary->plan_expires_at = null;
            $secondary->trial_ends_at   = null;
            $secondary->billing_cycle   = null;
            $secondary->save();

            // Sync the primary user row's email/mobile to whichever
            // identifier is currently flagged primary.
            $this->syncUserPrimaryFields($primary);

            // Finally remove the now-empty secondary user.
            $secondaryEmail = $secondary->email;
            $secondaryId    = $secondary->id;
            $secondary->delete();

            return [
                'reassigned'      => $reassigned,
                'kept_plan_from'  => $keepPlanFrom,
                'secondary_id'    => $secondaryId,
                'secondary_email' => $secondaryEmail,
            ];
        });
    }

    /**
     * Build the list of (table, column) pairs whose rows belong to a user.
     * Discovered by introspection plus a curated list of FK columns whose
     * name isn't `user_id`.
     *
     * @return array<int, array{0:string, 1:string}>
     */
    public function ownedTables(): array
    {
        $out = [];
        // Conservative denylist: framework infrastructure, audit/log
        // tables, and anything we deliberately handle elsewhere. New
        // schema additions don't need to opt in — they just need to
        // expose `user_id` — but anything that should NOT transfer
        // ownership across the merge belongs here. This guards against
        // schema drift silently moving security-sensitive rows
        // (sessions, audit logs, OTP records) onto the wrong account.
        $skip = [
            'users', 'sessions', 'password_reset_tokens', 'jobs', 'job_batches',
            'failed_jobs', 'cache', 'cache_locks', 'migrations',
            'personal_access_tokens', 'password_resets',
            'otps', 'otp_codes', 'one_time_passwords',
            'audit_logs', 'activity_log', 'admin_audit_logs',
            'linked_identifiers', // handled separately
        ];

        foreach ($this->allTables() as $table) {
            if (in_array($table, $skip, true)) continue;
            if (Schema::hasColumn($table, 'user_id')) {
                $out[] = [$table, 'user_id'];
            }
        }

        foreach (self::EXTRA_FK_COLUMNS as $row) {
            if (Schema::hasTable($row['table']) && Schema::hasColumn($row['table'], $row['column'])) {
                $out[] = [$row['table'], $row['column']];
            }
        }
        return $out;
    }

    private function allTables(): array
    {
        try {
            // Laravel 11+ schema builder
            $tables = Schema::getTables();
            $names = [];
            foreach ($tables as $t) {
                $names[] = is_array($t) ? ($t['name'] ?? '') : (is_object($t) ? ($t->name ?? '') : (string) $t);
            }
            return array_filter($names);
        } catch (\Throwable $e) {
            // Fallback for SQLite
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            return array_map(fn ($r) => $r->name, $rows);
        }
    }

    /**
     * Move rows for ($table, $column) from $fromId to $toId.
     * If $table has a unique constraint involving the user column plus
     * other columns, we delete the secondary's would-be-duplicate rows
     * first so the update doesn't violate the constraint.
     */
    private function reassignTable(string $table, string $column, int $toId, int $fromId): int
    {
        // Drop secondary rows that would clash with a primary row on
        // common natural-key columns — we don't need both.
        $likelyKeys = ['platform', 'handle', 'kind', 'provider', 'email', 'follower_id', 'creator_id'];
        $available = [];
        foreach ($likelyKeys as $k) {
            // Never include the FK column we're about to reassign in the
            // dedupe predicate — that would build a contradictory query
            // ("follower_id = fromId AND follower_id = toId") and miss
            // every actual duplicate.
            if ($k === $column) continue;
            if (Schema::hasColumn($table, $k)) $available[] = $k;
        }
        if ($available) {
            $primaryRows = DB::table($table)->where($column, $toId)->get($available);
            foreach ($primaryRows as $pr) {
                $q = DB::table($table)->where($column, $fromId);
                foreach ($available as $k) {
                    $q->where($k, $pr->$k);
                }
                $q->delete();
            }
        }

        return DB::table($table)->where($column, $fromId)->update([$column => $toId]);
    }

    /** Keep the user.email / user.mobile columns aligned with the current primary identifier. */
    public function syncUserPrimaryFields(User $user): void
    {
        $primary = $user->linkedIdentifiers()->where('is_primary', true)->first();
        if (!$primary) {
            // No primary set — pick one (prefer email, then phone).
            $primary = $user->linkedIdentifiers()->where('kind', 'email')->whereNotNull('verified_at')->first()
                ?? $user->linkedIdentifiers()->where('kind', 'phone')->whereNotNull('verified_at')->first();
            if ($primary) {
                $primary->is_primary = true;
                $primary->save();
            }
        }
        if (!$primary) return;

        if ($primary->kind === 'email' && $user->email !== $primary->value) {
            $user->email = $primary->value;
            $user->save();
        } elseif ($primary->kind === 'phone' && Schema::hasColumn('users', 'mobile') && $user->mobile !== $primary->value) {
            $user->mobile = $primary->value;
            $user->save();
        }
    }

    /**
     * Promote a verified identifier to primary. The previous primary
     * becomes a regular linked identifier.
     */
    public function promoteToPrimary(User $user, LinkedIdentifier $identifier): void
    {
        if ($identifier->user_id !== $user->id) {
            throw new \InvalidArgumentException('Identifier does not belong to user.');
        }
        if (!$identifier->verified_at) {
            throw new \RuntimeException('Cannot promote an unverified identifier.');
        }
        if ($identifier->kind === 'social') {
            throw new \RuntimeException('A social identifier cannot be the primary contact.');
        }

        DB::transaction(function () use ($user, $identifier) {
            $user->linkedIdentifiers()->update(['is_primary' => false]);
            $identifier->is_primary = true;
            $identifier->save();
            $this->syncUserPrimaryFields($user->fresh());
        });
    }

    /** Detach a non-primary identifier, refusing to leave the user without one. */
    public function unlink(User $user, LinkedIdentifier $identifier): void
    {
        if ($identifier->user_id !== $user->id) {
            throw new \InvalidArgumentException('Identifier does not belong to user.');
        }
        if ($identifier->is_primary) {
            throw new \RuntimeException('Cannot remove the primary identifier — promote another one first.');
        }
        $remaining = $user->verifiedIdentifiers()
            ->where('id', '!=', $identifier->id)
            ->count();
        if ($remaining < 1) {
            throw new \RuntimeException('You must keep at least one verified identifier.');
        }
        $hasContact = $user->verifiedIdentifiers()
            ->where('id', '!=', $identifier->id)
            ->whereIn('kind', ['email', 'phone'])
            ->exists();
        if (!$hasContact) {
            throw new \RuntimeException('You must keep at least one verified email or phone.');
        }
        $identifier->delete();
    }
}
