import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

/**
 * Shared self-bootstrapping fixture helpers for the Sayzio event-feature
 * browser specs (guest broadcast, waitlist promotion, public event page,
 * calendar settings).
 *
 * Everything is seeded via `php artisan tinker --execute=` against the (distant,
 * SHARED) Laravel RDS — the same channel the rest of this suite uses, and the
 * reliable way to read/write Laravel tables (executeSql hits the Node DB, not
 * Laravel's RDS — see repo memory browser-e2e-validation-gate.md).
 *
 * Shared-RDS discipline (repo memory e2e-shared-rds-fixture-aliases.md): every
 * fixture link uses a PER-RUN unique alias under a fixed prefix, and each seed
 * first prunes stale (>2h old) links under that prefix so concurrent task
 * environments pointing at the same RDS never collide on a fixed alias.
 */

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/** Fixed prefix for every alias this suite seeds (stale-prune key). */
export const ALIAS_PREFIX = "e2e-evtfeat";

/**
 * A per-run-unique suffix. Distinct task environments (and repeated local runs)
 * each get their own aliases so they can never delete/recreate each other's
 * fixture rows mid-render.
 */
export const RUN_ID = `${Date.now().toString(36)}${process.pid.toString(36)}`;

/**
 * Run a `php artisan tinker` seed, retrying on a transient RDS connect blip
 * (mirrors the pattern the other self-bootstrapping specs use). A genuine PHP
 * error fails every attempt and is surfaced via the rethrow.
 */
export function runTinker(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
        // Give the slow distant RDS room; a cold connect + writes can be slow.
        maxBuffer: 20 * 1024 * 1024,
      });
    } catch (err) {
      lastErr = err;
      // Small jittered back-off so concurrent/serial retries don't hammer the
      // same RDS rows in lockstep.
      const e = err as { stdout?: Buffer | string; stderr?: Buffer | string };
      const detail =
        (e.stdout ? e.stdout.toString() : "") +
        (e.stderr ? e.stderr.toString() : "");
      // Surface the real PHP error on the final attempt (execFileSync's own
      // message is just "Command failed").
      if (attempt === 3) {
        throw new Error(
          `tinker seed failed after ${attempt} attempts:\n` + detail.slice(-4000),
        );
      }
    }
  }
  throw lastErr;
}

/** The demo account this suite authenticates + owns every fixture as. */
export const OWNER_EMAIL = DEMO_LOGIN_EMAIL;

/**
 * Ensure the demo owner exists, is onboarded, and holds the `user-admin` role
 * (bypasses plan-gated surfaces like calendar_sync — see repo memory
 * infinite-animation-actionability.md). Returns the PHP snippet so callers can
 * compose it into a larger seed and only pay one tinker round-trip.
 */
function ownerSnippet(): string {
  return `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', '${OWNER_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${OWNER_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
`;
}

/**
 * Prune stale (>2h) fixture links this suite has previously seeded under
 * ALIAS_PREFIX, together with their child rows (ics_data, rsvps,
 * event_broadcasts). Kept in PHP so it runs inside the same tinker call as the
 * seed.
 */
function pruneSnippet(): string {
  return `
use App\\Modules\\User\\Models\\Link;
$stale = Link::query()->withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  DB::table('event_broadcasts')->whereIn('link_id', $stale)->delete();
  DB::table('rsvps')->whereIn('link_id', $stale)->delete();
  DB::table('ics_data')->whereIn('link_id', $stale)->delete();
  Link::query()->withoutGlobalScope('workspace')->whereKey($stale)->delete();
}
`;
}

/**
 * Build the shared header (owner + prune) every seed composes in front of its
 * own body.
 */
export function seedHeader(): string {
  return ownerSnippet() + pruneSnippet();
}
