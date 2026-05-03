import { browser } from "./browser";

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

export async function clearAuth(): Promise<void> {
  await browser.storage.local.remove(["token", "user", "workspaceId", "workspaces"]);
}

// Append an item to the pending-thanks queue while enforcing the cap.
// Dedupe (by channel + matchedUrl + pageUrl) is handled by callers; this
// helper only owns size enforcement so the queue can never exceed
// PENDING_THANKS_MAX. Oldest items are dropped first.
export function capPendingThanks(items: PendingThank[]): PendingThank[] {
  if (items.length <= PENDING_THANKS_MAX) return items;
  return items.slice(items.length - PENDING_THANKS_MAX);
}

// Drop pending thanks older than the TTL. Returns the pruned list and a
// flag indicating whether anything was removed (so callers can avoid an
// unnecessary write to browser.storage.local).
export function prunePendingThanks(
  items: PendingThank[],
  now: number = Date.now(),
): { items: PendingThank[]; pruned: boolean } {
  const cutoff = now - PENDING_THANKS_TTL_MS;
  const kept = items.filter((q) => q.createdAt >= cutoff);
  return { items: kept, pruned: kept.length !== items.length };
}
