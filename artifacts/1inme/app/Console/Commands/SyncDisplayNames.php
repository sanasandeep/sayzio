<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time / repeatable backfill: rewrites every stale denormalized copy of
 * users' display names (block comments, community rosters, fan points,
 * subscriber entries, internally-linked contacts) so past renames that
 * happened before the automatic sync existed are corrected.
 *
 * Set-based JOIN updates — one statement per table regardless of user
 * count, safe over a distant RDS. Idempotent: re-running is a no-op once
 * everything matches. NULL/empty snapshots (anonymous choices) are never
 * touched, and Google-synced contacts are never touched.
 *
 * Use --user=ID to fan out for a single user via the same code path the
 * queued rename job uses (includes cache busting).
 */
class SyncDisplayNames extends Command
{
    protected $signature = 'users:sync-display-names
        {--user= : Only sync the given user id (also busts their caches)}
        {--dry-run : Report how many rows would change without writing}';

    protected $description = 'Backfill denormalized display-name copies (comments, community members, fan points, subscribers, linked contacts) from users.name';

    public function handle(): int
    {
        if ($userId = $this->option('user')) {
            $user = \App\Modules\User\Models\User::find((int) $userId);
            if (!$user) {
                $this->error("User {$userId} not found.");
                return self::FAILURE;
            }
            \App\Modules\User\Services\UserNameSync::applyDenormalized($user);
            $this->info("Synced denormalized names for user {$user->id} ({$user->name}).");
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        foreach (self::statements() as [$table, $label, $countSql, $updateSql]) {
            if (!Schema::hasTable($table)) {
                $this->warn("Skipping {$label}: table {$table} missing.");
                continue;
            }
            if ($dry) {
                $n = (int) (DB::selectOne($countSql)->n ?? 0);
                $this->line("{$label}: {$n} stale row(s) would be updated.");
            } else {
                $n = DB::update($updateSql);
                $this->info("{$label}: updated {$n} row(s).");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Shared with the data migration. Each entry: [table, label, countSql,
     * updateSql]. Only rows with a non-empty stale snapshot are touched,
     * and only when the user has a non-empty current name.
     *
     * @return array<int, array{0:string,1:string,2:string,3:string}>
     */
    public static function statements(): array
    {
        $userOk = "u.name IS NOT NULL AND btrim(u.name) <> ''";

        return [
            [
                'block_comments', 'Block comments',
                "SELECT COUNT(*) AS n FROM block_comments bc JOIN users u ON u.id = COALESCE(bc.viewer_user_id, bc.user_id)
                 WHERE {$userOk} AND bc.author_name IS NOT NULL AND bc.author_name <> '' AND bc.author_name <> u.name",
                "UPDATE block_comments bc SET author_name = u.name FROM users u
                 WHERE u.id = COALESCE(bc.viewer_user_id, bc.user_id)
                   AND {$userOk} AND bc.author_name IS NOT NULL AND bc.author_name <> '' AND bc.author_name <> u.name",
            ],
            [
                'community_members', 'Community members',
                "SELECT COUNT(*) AS n FROM community_members cm JOIN users u ON u.id = cm.viewer_user_id
                 WHERE {$userOk} AND cm.display_name IS NOT NULL AND cm.display_name <> '' AND cm.display_name <> u.name",
                "UPDATE community_members cm SET display_name = u.name FROM users u
                 WHERE u.id = cm.viewer_user_id
                   AND {$userOk} AND cm.display_name IS NOT NULL AND cm.display_name <> '' AND cm.display_name <> u.name",
            ],
            [
                'fan_points', 'Fan points',
                "SELECT COUNT(*) AS n FROM fan_points fp JOIN users u ON u.id = fp.viewer_user_id
                 WHERE {$userOk} AND fp.display_name IS NOT NULL AND fp.display_name <> '' AND fp.display_name <> u.name",
                "UPDATE fan_points fp SET display_name = u.name FROM users u
                 WHERE u.id = fp.viewer_user_id
                   AND {$userOk} AND fp.display_name IS NOT NULL AND fp.display_name <> '' AND fp.display_name <> u.name",
            ],
            [
                'subscribers', 'Subscribers (matched by email)',
                "SELECT COUNT(*) AS n FROM subscribers s JOIN users u ON lower(u.email) = lower(s.email)
                 WHERE {$userOk} AND s.name IS NOT NULL AND s.name <> '' AND s.name <> u.name",
                "UPDATE subscribers s SET name = u.name FROM users u
                 WHERE lower(u.email) = lower(s.email)
                   AND {$userOk} AND s.name IS NOT NULL AND s.name <> '' AND s.name <> u.name",
            ],
            [
                'roadmap_comments', 'Roadmap comments',
                "SELECT COUNT(*) AS n FROM roadmap_comments rc JOIN users u ON u.id = rc.viewer_user_id
                 WHERE {$userOk} AND rc.author_name IS NOT NULL AND rc.author_name <> '' AND rc.author_name <> u.name",
                "UPDATE roadmap_comments rc SET author_name = u.name FROM users u
                 WHERE u.id = rc.viewer_user_id
                   AND {$userOk} AND rc.author_name IS NOT NULL AND rc.author_name <> '' AND rc.author_name <> u.name",
            ],
            [
                'reviews', 'Native reviews (matched by email)',
                "SELECT COUNT(*) AS n FROM reviews r JOIN users u ON lower(u.email) = lower(r.author_email)
                 WHERE {$userOk} AND r.author_name IS NOT NULL AND r.author_name <> '' AND r.author_name <> u.name",
                "UPDATE reviews r SET author_name = u.name FROM users u
                 WHERE lower(u.email) = lower(r.author_email)
                   AND {$userOk} AND r.author_name IS NOT NULL AND r.author_name <> '' AND r.author_name <> u.name",
            ],
            [
                'contacts', 'Internally-linked contacts',
                "SELECT COUNT(*) AS n FROM contacts c JOIN users u ON u.id = c.biolink_user_id
                 WHERE c.google_contacts_account_id IS NULL
                   AND {$userOk} AND c.display_name IS NOT NULL AND c.display_name <> '' AND c.display_name <> u.name",
                "UPDATE contacts c SET display_name = u.name FROM users u
                 WHERE u.id = c.biolink_user_id AND c.google_contacts_account_id IS NULL
                   AND {$userOk} AND c.display_name IS NOT NULL AND c.display_name <> '' AND c.display_name <> u.name",
            ],
        ];
    }
}
