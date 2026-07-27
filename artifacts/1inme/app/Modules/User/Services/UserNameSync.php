<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps every denormalized copy of a user's display name in sync when the
 * user renames themselves. Several features copy `users.name` into their own
 * tables at creation time (block comments, community rosters, fan-points
 * leaderboards, subscriber opt-ins, internally-linked contacts, the default
 * personal workspace) — without this, the old name keeps showing there.
 *
 * Intentionally NOT synced (frozen historical snapshots):
 *   - invoices / billing-address snapshots (legal accuracy),
 *   - past feed events (they describe a past moment),
 *   - Google-synced external contacts (owned by the provider),
 *   - manually renamed workspaces (user's explicit choice).
 *
 * Entry point: {@see handleRename()} from the web + API profile-update
 * paths. Fast, single-row updates (personal workspace, linked admin) run
 * inline; the fan-out over the denormalized tables and cache busting runs
 * on the queue via {@see \App\Jobs\SyncUserDisplayNameJob}.
 */
class UserNameSync
{
    /**
     * Called right after a profile save when the name actually changed.
     * Runs the cheap inline syncs and queues the heavy fan-out.
     */
    public static function handleRename(User $user, ?string $previousName): void
    {
        if ((string) $user->name === (string) $previousName) {
            return;
        }

        self::syncPersonalWorkspace($user, $previousName);
        self::syncLinkedAdmin($user);

        \App\Jobs\SyncUserDisplayNameJob::dispatch($user->id);
    }

    /**
     * Keep the personal workspace name in sync with the profile name, but
     * only while it still carries the auto-generated default (so a
     * workspace the user deliberately renamed is never clobbered). The
     * default is derived exactly as User::ensureDefaultWorkspace() does.
     */
    public static function syncPersonalWorkspace(User $user, ?string $previousName): void
    {
        $personal = $user->ownedWorkspaces()->where('is_personal', true)->first();
        if (!$personal) {
            return;
        }
        $autoDefaults = [
            (($previousName ?: ('User ' . $user->id))) . "'s workspace",
            'User ' . $user->id . "'s workspace",
        ];
        if (in_array($personal->name, $autoDefaults, true)) {
            $personal->update([
                'name' => ($user->name ?: ('User ' . $user->id)) . "'s workspace",
            ]);
        }
    }

    /**
     * Sync the linked admin account's name so the admin sidebar always
     * shows the user's current display name. Only the name is synced —
     * email and all other fields are explicitly out of scope. If the
     * user has no linked admin account this is a no-op.
     */
    public static function syncLinkedAdmin(User $user): void
    {
        $linkedAdmin = \App\Modules\Common\Services\AdminUserBridge::resolveAdminForUser($user);
        if ($linkedAdmin !== null && $linkedAdmin->name !== $user->name) {
            $linkedAdmin->update(['name' => $user->name]);
        }
    }

    /**
     * Fan-out: rewrite every denormalized name snapshot tied to this user.
     * Idempotent set-based updates — safe to run repeatedly (queued job,
     * backfill command, data migration all share this).
     *
     * Rows whose snapshot is NULL/empty are left alone: an absent name means
     * the person chose to stay anonymous ("Anonymous fan" / "Guest") and we
     * must not de-anonymize them.
     */
    public static function applyDenormalized(User $user): void
    {
        $name = trim((string) $user->name);
        if ($name === '') {
            return; // never blast an empty name over real snapshots
        }
        $id = $user->id;

        // Block comments the user authored — either as a signed-in viewer
        // (viewer_user_id) or as the creator replying as themselves (user_id).
        DB::table('block_comments')
            ->where(function ($q) use ($id) {
                $q->where('viewer_user_id', $id)->orWhere('user_id', $id);
            })
            ->whereNotNull('author_name')->where('author_name', '<>', '')
            ->where('author_name', '<>', $name)
            ->update(['author_name' => $name]);

        // Community member rosters (Insider feed) where the user joined.
        DB::table('community_members')
            ->where('viewer_user_id', $id)
            ->whereNotNull('display_name')->where('display_name', '<>', '')
            ->where('display_name', '<>', $name)
            ->update(['display_name' => $name]);

        // Fan-points leaderboard entries earned by the user.
        DB::table('fan_points')
            ->where('viewer_user_id', $id)
            ->whereNotNull('display_name')->where('display_name', '<>', '')
            ->where('display_name', '<>', $name)
            ->update(['display_name' => $name]);

        // Subscriber entries where the user subscribed to creators. The
        // subscribers table has no user link — the identity tie is the
        // user's own email address.
        $email = strtolower(trim((string) $user->email));
        if ($email !== '') {
            DB::table('subscribers')
                ->whereRaw('lower(email) = ?', [$email])
                ->whereNotNull('name')->where('name', '<>', '')
                ->where('name', '<>', $name)
                ->update(['name' => $name]);
        }

        // Roadmap comments the user posted while signed in (viewer_user_id).
        DB::table('roadmap_comments')
            ->where('viewer_user_id', $id)
            ->whereNotNull('author_name')->where('author_name', '<>', '')
            ->where('author_name', '<>', $name)
            ->update(['author_name' => $name]);

        // Native reviews written by this user. The reviews table has no
        // reviewer user link (reviews.user_id is the reviewed creator), so —
        // like subscribers — the identity tie is the reviewer's email.
        $reviewEmail = strtolower(trim((string) $user->email));
        if ($reviewEmail !== '') {
            DB::table('reviews')
                ->whereRaw('lower(author_email) = ?', [$reviewEmail])
                ->whereNotNull('author_name')->where('author_name', '<>', '')
                ->where('author_name', '<>', $name)
                ->update(['author_name' => $name]);
        }

        // Contacts internally linked to this Sayzio user (manual profiles
        // bound via biolink_user_id). Google-synced contacts are owned by
        // the external provider and are never touched.
        DB::table('contacts')
            ->where('biolink_user_id', $id)
            ->whereNull('google_contacts_account_id')
            ->whereNotNull('display_name')->where('display_name', '<>', '')
            ->where('display_name', '<>', $name)
            ->update(['display_name' => $name]);

        self::bustCreatorCaches($user);
    }

    /**
     * Bust the short-lived caches that render creator names so the new
     * name shows promptly on public surfaces (they'd otherwise refresh
     * within their 5–10 minute TTLs anyway).
     */
    public static function bustCreatorCaches(User $user): void
    {
        try {
            Cache::forget(\App\Modules\Common\Controllers\CreatorsController::DEFAULT_CACHE_KEY);
            foreach (\App\Modules\Common\Controllers\CreatorsController::trendingCarouselVariants() as [$showAdult, $onlyAdult]) {
                Cache::forget(\App\Modules\Common\Controllers\CreatorsController::trendingCarouselCacheKey($showAdult, $onlyAdult));
            }
            Cache::forget('sitemap.creators.xml');

            // Site-resolve payloads are keyed per custom domain host.
            $domains = DB::table('domains')
                ->where('user_id', $user->id)
                ->pluck('domain');
            foreach ($domains as $host) {
                $host = strtolower(trim((string) $host));
                if ($host !== '') {
                    Cache::forget("site-resolve:{$host}");
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Name-sync cache bust failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }
}
