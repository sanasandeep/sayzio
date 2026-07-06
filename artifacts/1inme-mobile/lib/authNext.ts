import AsyncStorage from "@react-native-async-storage/async-storage";

/**
 * Post-auth redirect plumbing for guest → signup → deep-link flows.
 *
 * The "Perfect pairings" cross-promo module (and any future surface that
 * wants a guest to land somewhere specific after signing in) stashes an
 * intended in-app path here before sending the visitor into the auth flow.
 * Every login-completion path (OTP verify, demo login, Google native, the
 * OAuth browser round-trip) then calls `redirectAfterAuth` which consumes
 * the stashed path and replaces to it — or falls back to the tabs home when
 * there's nothing pending.
 *
 * Persisting via AsyncStorage (rather than threading a `next` route param)
 * keeps a single consumption point that survives the external browser
 * round-trip used by social OAuth, where params don't come back to us.
 *
 * Freshness has two tiers so a slow-but-genuine sign-up isn't silently
 * dropped:
 *   - `MAX_AGE_MS` (10 min): the window a stash stays fresh between active
 *     steps. Every time the guest actively lands on an auth surface we call
 *     `touchPendingPostAuthNext` to slide this window forward, so a guest
 *     who is still working through signup never expires mid-flow.
 *   - `RESUMABLE_MAX_AGE_MS` (60 min): the outer bound. `redirectAfterAuth`
 *     only ever runs *after* a successful authentication — the strongest
 *     possible signal that this is the guest finishing the flow they
 *     started, not an unrelated stale value. So on completion we honour a
 *     stash within this wider window (covering an interruption like email
 *     verification that ran past the 10-minute mark) while still refusing
 *     to resurrect a genuinely abandoned stash from hours ago.
 */

const KEY = "pending_post_auth_next";
// The window a stash stays fresh between active auth steps. Slid forward by
// `touchPendingPostAuthNext` each time the guest actively re-enters the flow,
// so an active-but-slow guest never expires. A short window still stops a
// stale value from an abandoned attempt hijacking a later, unrelated sign-in.
const MAX_AGE_MS = 10 * 60 * 1000;
// The outer bound honoured at successful-auth completion. Wide enough to
// survive a real-world interruption (email verification, a distraction) that
// pushed the sign-up past the 10-minute mark, but bounded so a genuinely
// abandoned stash from hours ago is never resurrected onto an unrelated login.
const RESUMABLE_MAX_AGE_MS = 60 * 60 * 1000;

/**
 * Allow only internal absolute paths (e.g. "/links/create/biolink"). Anything
 * external, protocol-relative, or non-string resolves to null so we can never
 * be redirected off-app.
 */
export function sanitizeNext(next: string | null | undefined): string | null {
  if (!next) return null;
  const s = String(next);
  if (!s.startsWith("/") || s.startsWith("//")) return null;
  return s;
}

export async function setPendingPostAuthNext(next: string): Promise<void> {
  const safe = sanitizeNext(next);
  if (!safe) return;
  try {
    await AsyncStorage.setItem(
      KEY,
      JSON.stringify({ next: safe, ts: Date.now() }),
    );
  } catch {
    // Non-fatal: worst case the visitor lands on the tabs home after auth.
  }
}

/**
 * Slide the freshness window forward for an in-flight stash without consuming
 * it. Called when the guest actively lands on an auth surface (login screen,
 * OTP verify screen) so that as long as they keep progressing the stash never
 * expires mid-flow. Only re-arms a stash still inside the outer resumable
 * window, so a long-abandoned value is never brought back to life.
 */
export async function touchPendingPostAuthNext(): Promise<void> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return;
    const parsed = JSON.parse(raw) as { next?: string; ts?: number };
    const safe = sanitizeNext(parsed?.next);
    if (!safe) {
      await AsyncStorage.removeItem(KEY);
      return;
    }
    // Never resurrect a stash that's already past the outer resumable bound —
    // that's a genuinely abandoned attempt, not an active flow.
    if (!parsed.ts || Date.now() - parsed.ts > RESUMABLE_MAX_AGE_MS) {
      await AsyncStorage.removeItem(KEY);
      return;
    }
    await AsyncStorage.setItem(KEY, JSON.stringify({ next: safe, ts: Date.now() }));
  } catch {
    // Non-fatal: worst case the window isn't refreshed and the guest falls
    // back to the tabs home after auth.
  }
}

/**
 * Read and clear the stashed path, honouring a freshness window. `maxAgeMs`
 * defaults to the short between-steps window; completion paths pass the wider
 * resumable window (see `redirectAfterAuth`). The value is always cleared,
 * even when stale, so it can never leak into a later attempt.
 */
export async function consumePendingPostAuthNext(
  maxAgeMs: number = MAX_AGE_MS,
): Promise<string | null> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return null;
    await AsyncStorage.removeItem(KEY);
    const parsed = JSON.parse(raw) as { next?: string; ts?: number };
    const safe = sanitizeNext(parsed?.next);
    if (!safe) return null;
    if (!parsed.ts || Date.now() - parsed.ts > maxAgeMs) return null;
    return safe;
  } catch {
    return null;
  }
}

/**
 * Replace to the stashed post-auth path if one is pending, otherwise to the
 * tabs home. Shared by every login-completion path. Honours the wider
 * `RESUMABLE_MAX_AGE_MS` window: because this only runs after a *successful*
 * authentication, a stash a bit past the 10-minute mark is almost certainly
 * the guest finishing the flow they started (e.g. after a slow email
 * verification), so we take them where they meant to go instead of silently
 * dropping them into the generic tabs.
 */
export async function redirectAfterAuth(router: {
  replace: (href: never) => void;
}): Promise<void> {
  const next = await consumePendingPostAuthNext(RESUMABLE_MAX_AGE_MS);
  router.replace((next ?? "/(tabs)") as never);
}
