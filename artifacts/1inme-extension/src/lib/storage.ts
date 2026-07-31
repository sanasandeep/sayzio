import { browser } from "./browser";
import { api, ApiError, extractThankTemplatesConflict } from "./api";
import {
  mergeThankTemplatesPerId,
  pickConflictResolution,
  type ThankChannel as SharedThankChannel,
  type ThankTemplate as SharedThankTemplate,
  type ThankTemplatesConflict as SharedThankTemplatesConflict,
  type ThankTemplatesConflictStrategy as SharedThankTemplatesConflictStrategy,
} from "./thankTemplatesConflict";

export { mergeThankTemplatesPerId };

export interface ExtSettings {
  apiBaseUrl: string;
  webBaseUrl: string;
  token: string | null;
  user: {
    id: number;
    name: string;
    email: string;
    handle?: string | null;
    capabilities?: { link_smart_rules?: boolean; max_smart_rules?: number };
  } | null;
  workspaceId: number | null;
  workspaces: Array<{ id: number; name: string }>;
  // Last branded domain picked in the "Shorten this page" domain picker.
  // null = platform default domain. Restored on popup open; validated
  // against the freshly-fetched domain list (a stale/removed id falls
  // back to the default) and cleared on sign-out.
  shortenDomainId: number | null;
  // Contacts: extension preferences. Persisted in browser.storage.local.
  // contactDefaultTags — applied client-side before sending to /contacts.
  // contactAllowOneClick — gates the "One-click save" button.
  // contactWorkspaceId  — overrides the active workspace for contact saves.
  contactDefaultTags: string[];
  contactAllowOneClick: boolean;
  contactWorkspaceId: number | null;

  // Backlink radar — off until the user opts in on first install.
  radarEnabled: boolean;
  radarOnboarded: boolean;
  // Per-domain opt-out: hosts the user has explicitly disabled.
  radarDisabledHosts: string[];

  // Notifications — cached unread count from the last background poll.
  // The popup uses this to render a badge on the Notifications tab
  // without a network round-trip on every popup open.
  notifUnreadCount: number;
  // Epoch ms of the last successful notification poll. Used to limit
  // the frequency of browser.notifications.create() calls so we only
  // fire a native notification for items newer than the last check.
  notifLastPolledAt: number | null;

  // Click-to-dial — opt-in phone-number detection content script.
  dialEnabled: boolean;

  // Saved thank-you templates (max 3) used by the radar's "Thank" action.
  // Placeholders supported in subject/body: {pageUrl}, {matchedUrl}, {anchor}.
  thankTemplates: ThankTemplate[];
  // Local-edit timestamp (ms epoch) for last-write-wins reconciliation
  // against the per-workspace server copy. Bumped whenever the editor
  // saves; cleared on sign-out so a new account hydrates cleanly.
  thankTemplatesUpdatedAtMs: number | null;
  // The server `updated_at_ms` we last successfully observed for this
  // workspace (after a sync or push). Used as the optimistic-concurrency
  // token on the next push: if the server's stored ts has moved past
  // this value, another browser saved in between and we surface a
  // conflict instead of silently overwriting.
  thankTemplatesLastServerTs: number | null;
  // Workspace whose templates are currently mirrored locally. When the
  // creator switches workspace we re-hydrate from the server.
  thankTemplatesWorkspaceId: number | null;
  // Queued thank-yous awaiting batch approval/open from the Backlinks tab.
  pendingThanks: PendingThank[];
  // Last-write-wins reconciliation timestamp for the per-workspace
  // server copy of `pendingThanks` (mirrors `thankTemplatesUpdatedAtMs`).
  pendingThanksUpdatedAtMs: number | null;
  // Workspace whose queue is currently mirrored locally. When the
  // creator switches workspace we re-hydrate from the server.
  pendingThanksWorkspaceId: number | null;
  pendingThanksSeenIds: string[];
  // Click-to-dial history: the last DIAL_HISTORY_MAX dial-overlay
  // interactions (newest first). Written by the background's
  // DIAL_LOOKUP handler; the popup shows the newest 5 in a collapsed
  // "Recent calls" section.
  dialHistory: DialHistoryEntry[];
  // Per-domain "author book": cached author contacts (email / X handle /
  // LinkedIn URL) keyed by host, or by "host|path" for page-scoped
  // overrides. The Thank composer prefers the cached entry over a fresh
  // page scrape, so repeat backlinks from the same publisher pre-fill
  // instantly. Capped at AUTHOR_BOOK_MAX entries (oldest pruned).
  authorBook: Record<string, AuthorBookEntry>;
}

export type ThankChannel = SharedThankChannel;
export type ThankTemplate = SharedThankTemplate;

export interface AuthorBookEntry {
  // Host the entry was learned from (lowercased, no leading "www.").
  host: string;
  // Optional URL pathname for page-scoped overrides ("/blog/post-1").
  // When omitted the entry applies to the whole host.
  path?: string;
  email: string | null;
  xHandle: string | null;     // Without leading "@".
  linkedinUrl: string | null; // Canonical https LinkedIn profile URL.
  updatedAt: number;          // ms epoch — used for pruning + display.
}

// Cap on author-book size to keep storage bounded for power users who
// thank backlinks on hundreds of unique domains. When exceeded the
// oldest (lowest updatedAt) entries are dropped on insert.
export const AUTHOR_BOOK_MAX = 500;

// Pending-thanks queue bounds. The queue lives in browser.storage.local
// and is otherwise unbounded — a creator who queues lots of thanks and
// never opens the Backlinks tab would slowly bloat extension storage and
// slow popup loads. We cap the queue on insert (drop oldest first) and
// prune anything older than the TTL on popup open.
export const PENDING_THANKS_MAX = 50;
export const PENDING_THANKS_TTL_MS = 30 * 24 * 60 * 60 * 1000; // 30 days

export interface PendingThank {
  id: string;
  templateId: string;
  channel: ThankChannel;
  subject: string;
  body: string;
  recipient: string | null;
  pageUrl: string;
  matchedUrl: string;
  anchor: string;
  createdAt: number;
  // Optional social targets sniffed from the page when the thank-you was
  // composed. Used by buildComposerUrl to deep-link a reply / DM instead
  // of just opening a generic share-intent. Older queued items predate
  // this field and remain valid (treated as null).
  xHandle?: string | null;
  linkedinUrl?: string | null;
}

export interface PropertiesPayload {
  short_link_hosts: string[];
  biolink_username_path: string;
  biolink_hosts: string[];
  custom_domain_hosts: string[];
  slug_hash_prefix_len: number;
  slug_hash_algo: string;
  slug_hashes: string[];
  cached_at: string;
  cache_ttl_seconds: number;
  fetched_at_ms: number;
}

export interface CachedProperties {
  payload: PropertiesPayload;
}

export interface RadarMatch {
  href: string;
  anchor: string;
  matchedPropertyType: "short_link" | "biolink_username" | "custom_domain";
  matchedPropertyValue: string;
}

export interface AuthorContacts {
  email: string | null;
  xHandle: string | null;     // Without leading "@"
  linkedinUrl: string | null; // Canonical https URL to a /in/ or /company/ profile
}

export interface TabMatchState {
  pageUrl: string;
  pageTitle: string;
  matches: RadarMatch[];
  scannedAt: number;
  // Author contacts harvested by the radar content script in the same
  // hop as link extraction. Older cached scans (pre-feature) won't have
  // this — the popup falls back to its own on-demand detector then.
  author?: AuthorContacts;
}

const DEFAULT_API = "https://sayzio.app/api/v1";
const DEFAULT_WEB = "https://sayzio.app";

export const defaultSettings: ExtSettings = {
  apiBaseUrl: DEFAULT_API,
  webBaseUrl: DEFAULT_WEB,
  token: null,
  user: null,
  workspaceId: null,
  workspaces: [],
  shortenDomainId: null,
  contactDefaultTags: ["from-extension"],
  contactAllowOneClick: true,
  contactWorkspaceId: null,
  radarEnabled: false,
  radarOnboarded: false,
  radarDisabledHosts: [],
  notifUnreadCount: 0,
  notifLastPolledAt: null,
  dialEnabled: false,
  thankTemplates: defaultThankTemplates(),
  thankTemplatesUpdatedAtMs: null,
  thankTemplatesLastServerTs: null,
  thankTemplatesWorkspaceId: null,
  pendingThanks: [],
  pendingThanksUpdatedAtMs: null,
  pendingThanksWorkspaceId: null,
  pendingThanksSeenIds: [],
  dialHistory: [],
  authorBook: {},
};

// ── Click-to-dial history ─────────────────────────────────────────────

// Cap so extension storage stays bounded; the popup only shows the
// newest 5, but keeping 20 lets a future "see all" view exist without
// a storage migration.
export const DIAL_HISTORY_MAX = 20;

export interface DialHistoryEntry {
  // E.164-ish number the overlay looked up.
  number: string;
  // Matched Sayzio contact (null when the number is unknown).
  contactId: number | null;
  contactName: string | null;
  // Host of the page where the dial affordance was clicked.
  pageHost: string | null;
  // ms epoch of the interaction.
  at: number;
}

// Prepend an entry, de-duping consecutive lookups of the same number
// (a re-click within the same minute just refreshes the timestamp
// instead of flooding the list) and enforcing the cap. Pure — caller
// persists via setSettings.
export function appendDialHistory(
  history: DialHistoryEntry[],
  entry: DialHistoryEntry,
): DialHistoryEntry[] {
  const prev = Array.isArray(history) ? history : [];
  const head = prev[0];
  const rest =
    head && head.number === entry.number && entry.at - head.at < 60_000
      ? prev.slice(1)
      : prev;
  return [entry, ...rest].slice(0, DIAL_HISTORY_MAX);
}

// Normalise a URL into the (host, path) we use as author-book keys.
// Returns null for non-http(s) URLs so we never try to remember the
// "author" of a chrome:// or file:// page.
export function authorBookKeyFromUrl(url: string): { host: string; path: string } | null {
  try {
    const u = new URL(url);
    if (u.protocol !== "http:" && u.protocol !== "https:") return null;
    const host = u.hostname.replace(/^www\./i, "").toLowerCase();
    if (!host) return null;
    // Strip trailing slash so "/foo" and "/foo/" match the same entry.
    const path = u.pathname.replace(/\/+$/, "") || "/";
    return { host, path };
  } catch {
    return null;
  }
}

// Look up the best matching author-book entry for a URL. Page-scoped
// ("host|path") entries win over host-wide entries so a creator's
// per-page override always takes precedence.
export function lookupAuthorBookEntry(
  book: Record<string, AuthorBookEntry>,
  url: string,
): AuthorBookEntry | null {
  const k = authorBookKeyFromUrl(url);
  if (!k) return null;
  const pathHit = book[`${k.host}|${k.path}`];
  if (pathHit) return pathHit;
  return book[k.host] ?? null;
}

// Insert / replace an author-book entry. Returns the new map (does not
// mutate). Caller is responsible for persisting via setSettings.
//  - scope "host"  → key = host
//  - scope "path"  → key = host|path (URL-specific override)
// When the cap is exceeded the oldest entries (lowest updatedAt) are
// pruned first so storage stays bounded.
export function upsertAuthorBookEntry(
  book: Record<string, AuthorBookEntry>,
  url: string,
  contacts: { email: string | null; xHandle: string | null; linkedinUrl: string | null },
  scope: "host" | "path",
  now: number = Date.now(),
): Record<string, AuthorBookEntry> {
  const k = authorBookKeyFromUrl(url);
  if (!k) return book;
  // Don't store empty entries — saves storage and avoids pre-filling
  // the composer with three nulls on the next visit.
  if (!contacts.email && !contacts.xHandle && !contacts.linkedinUrl) return book;
  const key = scope === "path" ? `${k.host}|${k.path}` : k.host;
  const next: Record<string, AuthorBookEntry> = { ...book };
  next[key] = {
    host: k.host,
    path: scope === "path" ? k.path : undefined,
    email: contacts.email,
    xHandle: contacts.xHandle,
    linkedinUrl: contacts.linkedinUrl,
    updatedAt: now,
  };
  const keys = Object.keys(next);
  if (keys.length > AUTHOR_BOOK_MAX) {
    // Sort ascending by updatedAt and drop the oldest until we fit.
    const sorted = keys
      .map((kk) => ({ kk, ts: next[kk].updatedAt }))
      .sort((a, b) => a.ts - b.ts);
    const drop = sorted.slice(0, keys.length - AUTHOR_BOOK_MAX);
    for (const d of drop) delete next[d.kk];
  }
  return next;
}

// Remove a single author-book entry by its storage key.
export function removeAuthorBookEntry(
  book: Record<string, AuthorBookEntry>,
  key: string,
): Record<string, AuthorBookEntry> {
  if (!(key in book)) return book;
  const next = { ...book };
  delete next[key];
  return next;
}

export function defaultThankTemplates(): ThankTemplate[] {
  return [
    {
      id: "tmpl-email",
      name: "Friendly email",
      channel: "email",
      subject: "Thanks for the link!",
      body:
        "Hi there,\n\n" +
        "Just spotted that you linked to {matchedUrl} from {pageUrl}" +
        " — really appreciate the mention{anchorClause}.\n\n" +
        "If there's ever anything I can do to help out in return, just say the word.\n\n" +
        "Thanks again!",
    },
    {
      id: "tmpl-x",
      name: "Quick X reply",
      channel: "x",
      subject: "",
      body: "Thanks so much for the shout-out at {pageUrl} 🙏 — really appreciate it!",
    },
    {
      id: "tmpl-linkedin",
      name: "LinkedIn note",
      channel: "linkedin",
      subject: "",
      body:
        "Thanks for linking to my work at {matchedUrl} from {pageUrl}{anchorClause}." +
        " Genuinely appreciate the mention!",
    },
  ];
}

export function renderThankTemplate(
  tpl: { subject: string; body: string },
  vars: { pageUrl: string; matchedUrl: string; anchor: string },
): { subject: string; body: string } {
  const anchor = (vars.anchor || "").trim();
  const anchorClause = anchor ? ` (loved the "${anchor}" anchor)` : "";
  const replace = (s: string) => s
    .replace(/\{pageUrl\}/g, vars.pageUrl)
    .replace(/\{matchedUrl\}/g, vars.matchedUrl)
    .replace(/\{anchor\}/g, anchor)
    .replace(/\{anchorClause\}/g, anchorClause);
  return { subject: replace(tpl.subject || ""), body: replace(tpl.body || "") };
}

const PROPERTIES_KEY = "radarProperties";

export async function getCachedProperties(): Promise<PropertiesPayload | null> {
  const stored = (await browser.storage.local.get([PROPERTIES_KEY])) as Record<string, unknown>;
  const cached = stored[PROPERTIES_KEY] as PropertiesPayload | undefined;
  return cached ?? null;
}

export async function setCachedProperties(payload: PropertiesPayload): Promise<void> {
  await browser.storage.local.set({ [PROPERTIES_KEY]: payload });
}

export async function clearCachedProperties(): Promise<void> {
  await browser.storage.local.remove([PROPERTIES_KEY]);
}

export async function getSettings(): Promise<ExtSettings> {
  const stored = (await browser.storage.local.get(Object.keys(defaultSettings))) as Partial<ExtSettings>;
  return { ...defaultSettings, ...stored };
}

export async function setSettings(patch: Partial<ExtSettings>): Promise<ExtSettings> {
  await browser.storage.local.set(patch as Record<string, unknown>);
  return getSettings();
}

/**
 * Persist the unread-notification count AND repaint the global toolbar
 * badge in one go. Used by the popup's markRead / markAll / load paths
 * and the background's poll so a read action on any surface clears the
 * badge instantly instead of waiting for the next 30 s background poll.
 *
 * Also stamps `notifLastPolledAt` so the background stale-guard treats
 * the fresh count as authoritative (the popup only calls this with a
 * count derived from a successful API response or a just-confirmed
 * mark-read mutation).
 */
export async function applyNotifUnreadCount(count: number): Promise<void> {
  const n = Math.max(0, Math.floor(count));
  await setSettings({ notifUnreadCount: n, notifLastPolledAt: Date.now() });
  try {
    const text = n > 0 ? (n > 99 ? "99+" : String(n)) : "";
    await (browser.action as any)?.setBadgeText?.({ text });
    if (n > 0) {
      await (browser.action as any)?.setBadgeBackgroundColor?.({ color: "#ef4444" });
    }
  } catch { /* badge updates are best-effort */ }
}

/**
 * Reconcile the locally-stored thank-you templates with the server copy
 * for the active workspace. Called on sign-in and whenever the workspace
 * selection changes.
 *
 * Resolution rules (last-write-wins by ms timestamp):
 *  - If we've never synced this workspace locally, the server copy wins
 *    (or, if the server has none, we push the local defaults).
 *  - Otherwise, whichever side has the newer updated_at_ms wins. Ties
 *    or missing server timestamps fall through to "push local" so an
 *    offline edit survives the next sync.
 *
 * Network failures are swallowed so the UI keeps working offline; the
 * next call (e.g. on the next popup open) retries naturally.
 */
export async function syncThankTemplates(): Promise<void> {
  const s = await getSettings();
  if (!s.token || !s.workspaceId) return;
  let server: { templates: ThankTemplate[]; updated_at_ms: number | null } | null = null;
  try {
    const resp = await api.getThankTemplates(s.workspaceId);
    server = { templates: resp.templates as ThankTemplate[], updated_at_ms: resp.updated_at_ms };
  } catch (e) {
    if (e instanceof ApiError && e.status >= 400 && e.status < 500 && e.status !== 404) return;
    // Offline / 5xx — leave local copy alone, try again later.
    return;
  }

  const local = s.thankTemplates && s.thankTemplates.length > 0 ? s.thankTemplates : defaultThankTemplates();
  const localTs = s.thankTemplatesUpdatedAtMs;
  const sameWorkspace = s.thankTemplatesWorkspaceId === s.workspaceId;
  const serverTs = server?.updated_at_ms ?? null;
  const serverHasAny = !!server && server.templates.length > 0;

  // Server has nothing → seed it from local (defaults or prior edits).
  if (!serverHasAny) {
    try {
      const resp = await api.saveThankTemplates(local, s.workspaceId, Date.now());
      await setSettings({
        thankTemplates: resp.templates as ThankTemplate[],
        thankTemplatesUpdatedAtMs: resp.updated_at_ms,
        thankTemplatesLastServerTs: resp.updated_at_ms,
        thankTemplatesWorkspaceId: s.workspaceId,
      });
    } catch { /* offline — defer to next sync */ }
    return;
  }

  // First sync for this workspace, or local has no edit timestamp →
  // server wins.
  if (!sameWorkspace || localTs == null) {
    await setSettings({
      thankTemplates: server!.templates,
      thankTemplatesUpdatedAtMs: serverTs,
      thankTemplatesLastServerTs: serverTs,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
    return;
  }

  // Both sides have a timestamp — last-write-wins.
  if (serverTs != null && serverTs > localTs) {
    await setSettings({
      thankTemplates: server!.templates,
      thankTemplatesUpdatedAtMs: serverTs,
      thankTemplatesLastServerTs: serverTs,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
  } else if (localTs > (serverTs ?? 0)) {
    try {
      const resp = await api.saveThankTemplates(local, s.workspaceId, localTs);
      await setSettings({
        thankTemplates: resp.templates as ThankTemplate[],
        thankTemplatesUpdatedAtMs: resp.updated_at_ms,
        thankTemplatesLastServerTs: resp.updated_at_ms,
        thankTemplatesWorkspaceId: s.workspaceId,
      });
    } catch { /* offline — try next time */ }
  } else {
    // Equal timestamps — adopt server payload to converge content.
    await setSettings({
      thankTemplates: server!.templates,
      thankTemplatesUpdatedAtMs: serverTs,
      thankTemplatesLastServerTs: serverTs,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
  }
}

// Re-exported from `./thankTemplatesConflict` so existing imports keep
// working. The conflict types live in a dependency-free module so the
// pure helpers are unit-testable without a webextension polyfill.
export type ThankTemplatesConflict = SharedThankTemplatesConflict;
export type ThankTemplatesConflictStrategy = SharedThankTemplatesConflictStrategy;

/**
 * Persist edited templates locally and push to the server. Bumps the
 * local timestamp so an offline save still wins the next reconcile.
 *
 * Returns a `conflict` payload when the server rejects the push because
 * another browser saved in between (HTTP 409). The local copy is still
 * kept (the user's edits aren't dropped) — the editor surfaces the
 * conflict and lets the creator pick a resolution via
 * `resolveThankTemplatesConflict`.
 */
export async function saveThankTemplatesLocallyAndPush(
  templates: ThankTemplate[],
): Promise<{
  pushed: boolean;
  error?: string;
  conflict?: ThankTemplatesConflict;
}> {
  const ts = Date.now();
  const s = await getSettings();
  await setSettings({
    thankTemplates: templates,
    thankTemplatesUpdatedAtMs: ts,
    thankTemplatesWorkspaceId: s.workspaceId,
  });
  if (!s.token || !s.workspaceId) return { pushed: false };
  // Send the server ts we last saw so the server can reject the push
  // (with 409) if another browser saved in between. `0` is the sentinel
  // for "we expected nothing on the server yet" (first push from this
  // workspace).
  const expected = s.thankTemplatesLastServerTs ?? 0;
  try {
    const resp = await api.saveThankTemplates(templates, s.workspaceId, ts, expected);
    await setSettings({
      thankTemplates: resp.templates as ThankTemplate[],
      thankTemplatesUpdatedAtMs: resp.updated_at_ms ?? ts,
      thankTemplatesLastServerTs: resp.updated_at_ms ?? ts,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
    return { pushed: true };
  } catch (e: any) {
    const conflictPayload = extractThankTemplatesConflict(e);
    if (conflictPayload) {
      return {
        pushed: false,
        error: e?.message || "Server has a newer copy",
        conflict: {
          local: templates,
          server: conflictPayload.templates as ThankTemplate[],
          serverUpdatedAtMs: conflictPayload.updated_at_ms,
        },
      };
    }
    return { pushed: false, error: e?.message || "Sync deferred" };
  }
}

/**
 * Apply the user's choice from the conflict banner. Bypasses the
 * optimistic-concurrency check (the user has now seen the server copy
 * and explicitly picked a winner), so the push always wins barring a
 * fresh race.
 */
export async function resolveThankTemplatesConflict(
  conflict: ThankTemplatesConflict,
  strategy: ThankTemplatesConflictStrategy,
  mergedOverride?: ThankTemplate[],
): Promise<{ pushed: boolean; templates: ThankTemplate[]; error?: string }> {
  const s = await getSettings();
  const chosen = pickConflictResolution(conflict, strategy, mergedOverride);

  // "Use server" doesn't need a network round-trip — we already have
  // the authoritative payload and its timestamp from the 409.
  if (strategy === "server") {
    await setSettings({
      thankTemplates: chosen,
      thankTemplatesUpdatedAtMs: conflict.serverUpdatedAtMs,
      thankTemplatesLastServerTs: conflict.serverUpdatedAtMs,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
    return { pushed: true, templates: chosen };
  }

  const ts = Date.now();
  await setSettings({
    thankTemplates: chosen,
    thankTemplatesUpdatedAtMs: ts,
    thankTemplatesWorkspaceId: s.workspaceId,
  });
  if (!s.token || !s.workspaceId) {
    return { pushed: false, templates: chosen };
  }
  try {
    // Pass the server ts we just observed via the 409 so the push lines
    // up with the current server state. If yet another browser raced in
    // between, we'll get a fresh 409 and the editor will re-prompt.
    const resp = await api.saveThankTemplates(
      chosen, s.workspaceId, ts, conflict.serverUpdatedAtMs ?? 0,
    );
    await setSettings({
      thankTemplates: resp.templates as ThankTemplate[],
      thankTemplatesUpdatedAtMs: resp.updated_at_ms ?? ts,
      thankTemplatesLastServerTs: resp.updated_at_ms ?? ts,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
    return { pushed: true, templates: resp.templates as ThankTemplate[] };
  } catch (e: any) {
    return { pushed: false, templates: chosen, error: e?.message || "Sync deferred" };
  }
}

export async function clearAuth(): Promise<void> {
  await browser.storage.local.remove([
    "token", "user", "workspaceId", "workspaces", "shortenDomainId",
    "thankTemplatesUpdatedAtMs", "thankTemplatesLastServerTs",
    "thankTemplatesWorkspaceId",
    "pendingThanksUpdatedAtMs", "pendingThanksWorkspaceId",
    "pendingThanksSeenIds",
  ]);
}

/**
 * Count pending-thanks items that haven't been "seen" on this device
 * yet. Used to drive the unread-count badge on the Backlinks tab so
 * items that arrived from another browser via sync surface visibly.
 */
export function unreadPendingThanksCount(
  items: PendingThank[],
  seenIds: string[],
): number {
  if (items.length === 0) return 0;
  const seen = new Set(seenIds);
  let n = 0;
  for (const it of items) if (!seen.has(it.id)) n++;
  return n;
}

/**
 * Mark every currently-queued pending thank as "seen" on this device.
 * Also drops any seen-ids that no longer correspond to a queued item
 * (e.g. items that were sent or auto-pruned) so the set can't grow
 * forever. Returns the new seen-ids list and whether anything changed
 * so callers can avoid an unnecessary write.
 */
export function markPendingThanksSeen(
  items: PendingThank[],
  seenIds: string[],
): { seenIds: string[]; changed: boolean } {
  const liveIds = new Set(items.map((i) => i.id));
  const next = items.map((i) => i.id);
  const prev = new Set(seenIds);
  // Changed if any current id is unseen, or any seen id no longer exists.
  let changed = false;
  for (const id of liveIds) if (!prev.has(id)) { changed = true; break; }
  if (!changed) {
    for (const id of seenIds) if (!liveIds.has(id)) { changed = true; break; }
  }
  return { seenIds: next, changed };
}

/**
 * Reconcile the locally-stored pending-thanks queue with the server
 * copy for the active workspace. Called on sign-in and whenever the
 * workspace selection changes. Mirrors `syncThankTemplates` rules:
 *
 *  - First sync for this workspace → server wins (the queue may have
 *    been edited from another browser).
 *  - Otherwise last-write-wins by ms timestamp.
 *  - Network failures are swallowed; the next call retries.
 *
 * On adoption from server we also TTL-prune so a long-stale item
 * stored remotely doesn't reappear forever.
 */
export async function syncPendingThanks(): Promise<void> {
  const s = await getSettings();
  if (!s.token || !s.workspaceId) return;
  let server: { items: PendingThank[]; updated_at_ms: number | null } | null = null;
  try {
    const resp = await api.getPendingThanks(s.workspaceId);
    server = { items: resp.items as PendingThank[], updated_at_ms: resp.updated_at_ms };
  } catch (e) {
    if (e instanceof ApiError && e.status >= 400 && e.status < 500 && e.status !== 404) return;
    return;
  }

  const local = s.pendingThanks || [];
  const localTs = s.pendingThanksUpdatedAtMs;
  const sameWorkspace = s.pendingThanksWorkspaceId === s.workspaceId;
  const serverTs = server?.updated_at_ms ?? null;

  const adoptServer = async () => {
    const { items } = prunePendingThanks(server!.items);
    await setSettings({
      pendingThanks: capPendingThanks(items).items,
      pendingThanksUpdatedAtMs: serverTs,
      pendingThanksWorkspaceId: s.workspaceId,
    });
  };

  // First sync for this workspace, or local has no edit timestamp →
  // server wins (even if empty — another device may have cleared it).
  if (!sameWorkspace || localTs == null) {
    await adoptServer();
    return;
  }

  if (serverTs != null && serverTs > localTs) {
    await adoptServer();
  } else if (localTs > (serverTs ?? 0)) {
    try {
      const resp = await api.savePendingThanks(local, s.workspaceId, localTs);
      await setSettings({
        pendingThanks: capPendingThanks(resp.items as PendingThank[]).items,
        pendingThanksUpdatedAtMs: resp.updated_at_ms ?? localTs,
        pendingThanksWorkspaceId: s.workspaceId,
      });
    } catch { /* offline — try next time */ }
  } else {
    // Equal timestamps — adopt server payload to converge content.
    await adoptServer();
  }
}

/**
 * Persist the pending-thanks queue locally and push to the server.
 * Bumps the local timestamp so an offline change still wins the next
 * reconcile. Returns whether the server push succeeded so callers can
 * surface a "saved locally, will sync later" hint if they want to.
 */
export async function savePendingThanksLocallyAndPush(
  items: PendingThank[],
): Promise<{ pushed: boolean; error?: string }> {
  const ts = Date.now();
  const s = await getSettings();
  const { items: capped } = capPendingThanks(items);
  await setSettings({
    pendingThanks: capped,
    pendingThanksUpdatedAtMs: ts,
    pendingThanksWorkspaceId: s.workspaceId,
  });
  if (!s.token || !s.workspaceId) return { pushed: false };
  try {
    const resp = await api.savePendingThanks(capped, s.workspaceId, ts);
    await setSettings({
      pendingThanks: capPendingThanks(resp.items as PendingThank[]).items,
      pendingThanksUpdatedAtMs: resp.updated_at_ms ?? ts,
      pendingThanksWorkspaceId: s.workspaceId,
    });
    return { pushed: true };
  } catch (e: any) {
    return { pushed: false, error: e?.message || "Sync deferred" };
  }
}

// Append an item to the pending-thanks queue while enforcing the cap.
// Dedupe (by channel + matchedUrl + pageUrl) is handled by callers; this
// helper only owns size enforcement so the queue can never exceed
// PENDING_THANKS_MAX. Oldest items are dropped first. Returns the
// capped list and the number of dropped entries so the popup can warn
// the creator that the oldest thank-yous fell off.
export function capPendingThanks(
  items: PendingThank[],
): { items: PendingThank[]; dropped: number } {
  if (items.length <= PENDING_THANKS_MAX) return { items, dropped: 0 };
  const dropped = items.length - PENDING_THANKS_MAX;
  return { items: items.slice(dropped), dropped };
}

// Drop pending thanks older than the TTL. Returns the pruned list and
// the count of removed entries (so callers can avoid an unnecessary
// write to browser.storage.local and surface a one-time notice with
// the exact number of stale items).
export function prunePendingThanks(
  items: PendingThank[],
  now: number = Date.now(),
): { items: PendingThank[]; pruned: number } {
  const cutoff = now - PENDING_THANKS_TTL_MS;
  const kept = items.filter((q) => q.createdAt >= cutoff);
  return { items: kept, pruned: items.length - kept.length };
}
