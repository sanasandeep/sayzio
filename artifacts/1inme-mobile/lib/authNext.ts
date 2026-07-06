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
 */

const KEY = "pending_post_auth_next";
// Only honour a stashed redirect briefly so a stale value from an abandoned
// attempt never hijacks a later, unrelated sign-in.
const MAX_AGE_MS = 10 * 60 * 1000;

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

/** Read and clear the stashed path, honouring the freshness window. */
export async function consumePendingPostAuthNext(): Promise<string | null> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return null;
    await AsyncStorage.removeItem(KEY);
    const parsed = JSON.parse(raw) as { next?: string; ts?: number };
    const safe = sanitizeNext(parsed?.next);
    if (!safe) return null;
    if (!parsed.ts || Date.now() - parsed.ts > MAX_AGE_MS) return null;
    return safe;
  } catch {
    return null;
  }
}

/**
 * Replace to the stashed post-auth path if one is pending (and fresh),
 * otherwise to the tabs home. Shared by every login-completion path.
 */
export async function redirectAfterAuth(router: {
  replace: (href: never) => void;
}): Promise<void> {
  const next = await consumePendingPostAuthNext();
  router.replace((next ?? "/(tabs)") as never);
}
