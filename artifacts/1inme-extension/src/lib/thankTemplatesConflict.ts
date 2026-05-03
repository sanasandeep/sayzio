// Pure conflict-resolution helpers for the thank-templates sync.
// Kept dependency-free (no `browser`, no `api`) so they can be unit
// tested in plain Node without a webextension polyfill.

export type ThankChannel = "email" | "x" | "linkedin";

export interface ThankTemplate {
  id: string;
  name: string;
  channel: ThankChannel;
  subject: string;
  body: string;
}

export interface ThankTemplatesConflict {
  local: ThankTemplate[];
  server: ThankTemplate[];
  serverUpdatedAtMs: number | null;
}

export type ThankTemplatesConflictStrategy = "mine" | "server" | "merge";

// Hard cap that mirrors `ThankTemplateController::MAX_TEMPLATES`. A
// merge can never push the workspace over this limit.
export const MAX_THANK_TEMPLATES = 3;

/**
 * Per-template merge: prefer the local entry when both sides share an
 * id, then append server-only ids. Order: local entries first (in their
 * original order), then server-only entries. Capped at the API limit.
 */
export function mergeThankTemplatesPerId(
  local: ThankTemplate[],
  server: ThankTemplate[],
): ThankTemplate[] {
  const out: ThankTemplate[] = [];
  const seen = new Set<string>();
  for (const t of local) {
    if (seen.has(t.id)) continue;
    seen.add(t.id);
    out.push(t);
  }
  for (const t of server) {
    if (seen.has(t.id)) continue;
    seen.add(t.id);
    out.push(t);
  }
  return out.slice(0, MAX_THANK_TEMPLATES);
}

/**
 * Given a user's resolution choice, return the template list that
 * should be persisted. Used by both the storage push path and the test
 * suite (so the "what gets saved" decision is one shared function).
 */
export function pickConflictResolution(
  conflict: ThankTemplatesConflict,
  strategy: ThankTemplatesConflictStrategy,
  mergedOverride?: ThankTemplate[],
): ThankTemplate[] {
  if (strategy === "server") return conflict.server;
  if (strategy === "merge") {
    return mergedOverride ?? mergeThankTemplatesPerId(conflict.local, conflict.server);
  }
  return conflict.local;
}

/**
 * Detect a sync conflict by comparing the server timestamp the client
 * last observed with the timestamp the server reports right now. This
 * is the same check the server performs against `expected_updated_at_ms`
 * on PUT — kept here so the client can also detect conflicts ahead of
 * a push (e.g. on sync) without re-implementing the rule.
 */
export function isThankTemplatesConflict(
  lastSeenServerTs: number | null,
  currentServerTs: number | null,
): boolean {
  const expected = lastSeenServerTs ?? 0;
  const current = currentServerTs ?? 0;
  return current !== expected;
}
