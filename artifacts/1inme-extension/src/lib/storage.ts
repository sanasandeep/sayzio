import { browser } from "./browser";
import { api, ApiError } from "./api";

export interface ExtSettings {
  apiBaseUrl: string;
  webBaseUrl: string;
  token: string | null;
  user: { id: number; name: string; email: string; handle?: string | null } | null;
  workspaceId: number | null;
  workspaces: Array<{ id: number; name: string }>;
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

  // Saved thank-you templates (max 3) used by the radar's "Thank" action.
  // Placeholders supported in subject/body: {pageUrl}, {matchedUrl}, {anchor}.
  thankTemplates: ThankTemplate[];
  // Local-edit timestamp (ms epoch) for last-write-wins reconciliation
  // against the per-workspace server copy. Bumped whenever the editor
  // saves; cleared on sign-out so a new account hydrates cleanly.
  thankTemplatesUpdatedAtMs: number | null;
  // Workspace whose templates are currently mirrored locally. When the
  // creator switches workspace we re-hydrate from the server.
  thankTemplatesWorkspaceId: number | null;
  // Queued thank-yous awaiting batch approval/open from the Backlinks tab.
  pendingThanks: PendingThank[];
}

export type ThankChannel = "email" | "x" | "linkedin";

export interface ThankTemplate {
  id: string;
  name: string;
  channel: ThankChannel;
  subject: string;
  body: string;
}

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

export interface TabMatchState {
  pageUrl: string;
  pageTitle: string;
  matches: RadarMatch[];
  scannedAt: number;
}

const DEFAULT_API = "https://1inme.com/api/v1";
const DEFAULT_WEB = "https://1inme.com";

export const defaultSettings: ExtSettings = {
  apiBaseUrl: DEFAULT_API,
  webBaseUrl: DEFAULT_WEB,
  token: null,
  user: null,
  workspaceId: null,
  workspaces: [],
  contactDefaultTags: ["from-extension"],
  contactAllowOneClick: true,
  contactWorkspaceId: null,
  radarEnabled: false,
  radarOnboarded: false,
  radarDisabledHosts: [],
  thankTemplates: defaultThankTemplates(),
  thankTemplatesUpdatedAtMs: null,
  thankTemplatesWorkspaceId: null,
  pendingThanks: [],
};

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
      thankTemplatesWorkspaceId: s.workspaceId,
    });
    return;
  }

  // Both sides have a timestamp — last-write-wins.
  if (serverTs != null && serverTs > localTs) {
    await setSettings({
      thankTemplates: server!.templates,
      thankTemplatesUpdatedAtMs: serverTs,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
  } else if (localTs > (serverTs ?? 0)) {
    try {
      const resp = await api.saveThankTemplates(local, s.workspaceId, localTs);
      await setSettings({
        thankTemplates: resp.templates as ThankTemplate[],
        thankTemplatesUpdatedAtMs: resp.updated_at_ms,
        thankTemplatesWorkspaceId: s.workspaceId,
      });
    } catch { /* offline — try next time */ }
  } else {
    // Equal timestamps — adopt server payload to converge content.
    await setSettings({
      thankTemplates: server!.templates,
      thankTemplatesUpdatedAtMs: serverTs,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
  }
}

/**
 * Persist edited templates locally and push to the server. Bumps the
 * local timestamp so an offline save still wins the next reconcile.
 */
export async function saveThankTemplatesLocallyAndPush(
  templates: ThankTemplate[],
): Promise<{ pushed: boolean; error?: string }> {
  const ts = Date.now();
  const s = await getSettings();
  await setSettings({
    thankTemplates: templates,
    thankTemplatesUpdatedAtMs: ts,
    thankTemplatesWorkspaceId: s.workspaceId,
  });
  if (!s.token || !s.workspaceId) return { pushed: false };
  try {
    const resp = await api.saveThankTemplates(templates, s.workspaceId, ts);
    await setSettings({
      thankTemplates: resp.templates as ThankTemplate[],
      thankTemplatesUpdatedAtMs: resp.updated_at_ms ?? ts,
      thankTemplatesWorkspaceId: s.workspaceId,
    });
    return { pushed: true };
  } catch (e: any) {
    return { pushed: false, error: e?.message || "Sync deferred" };
  }
}

export async function clearAuth(): Promise<void> {
  await browser.storage.local.remove([
    "token", "user", "workspaceId", "workspaces",
    "thankTemplatesUpdatedAtMs", "thankTemplatesWorkspaceId",
  ]);
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
