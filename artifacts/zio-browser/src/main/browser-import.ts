/**
 * Browser import — bring bookmarks and history over from other browsers
 * installed on this computer (Chrome, Edge, Brave, Chromium, Firefox), or
 * from a bookmarks HTML file exported by any browser.
 *
 * Reads are strictly read-only: locked SQLite files (Chrome "History",
 * Firefox "places.sqlite") are copied to a temp file first and opened
 * readonly, so the source browser is never touched.
 */
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import Database from 'better-sqlite3';

export interface DetectedBrowser {
  id: string;            // e.g. 'chrome', 'edge:Profile 1'
  name: string;          // e.g. 'Google Chrome', 'Microsoft Edge — Profile 1'
  kind: 'chromium' | 'firefox';
  profileDir: string;
  hasBookmarks: boolean;
  hasHistory: boolean;
}

export interface ImportedBookmark {
  url: string;
  title: string;
  folder: string | null;
}

export interface ImportedHistoryEntry {
  url: string;
  title: string | null;
  visitCount: number;
  lastVisitedIso: string;
}

const HISTORY_IMPORT_LIMIT = 5000;

// Chromium timestamps are microseconds since 1601-01-01.
const CHROMIUM_EPOCH_OFFSET_MS = 11644473600000;

function chromiumTimeToIso(us: number | null | undefined): string {
  if (!us || us <= 0) return new Date().toISOString();
  const ms = us / 1000 - CHROMIUM_EPOCH_OFFSET_MS;
  if (!Number.isFinite(ms) || ms <= 0) return new Date().toISOString();
  return new Date(ms).toISOString();
}

function firefoxTimeToIso(us: number | null | undefined): string {
  if (!us || us <= 0) return new Date().toISOString();
  return new Date(us / 1000).toISOString();
}

function isHttpUrl(url: unknown): url is string {
  return typeof url === 'string' && /^https?:\/\//i.test(url);
}

// ── Detection ────────────────────────────────────────────────────────────────

interface ChromiumSpec { id: string; name: string; roots: string[] }

function chromiumSpecs(): ChromiumSpec[] {
  const home = os.homedir();
  if (process.platform === 'darwin') {
    const base = path.join(home, 'Library', 'Application Support');
    return [
      { id: 'chrome', name: 'Google Chrome', roots: [path.join(base, 'Google', 'Chrome')] },
      { id: 'edge', name: 'Microsoft Edge', roots: [path.join(base, 'Microsoft Edge')] },
      { id: 'brave', name: 'Brave', roots: [path.join(base, 'BraveSoftware', 'Brave-Browser')] },
      { id: 'chromium', name: 'Chromium', roots: [path.join(base, 'Chromium')] },
    ];
  }
  if (process.platform === 'win32') {
    const localAppData = process.env.LOCALAPPDATA ?? path.join(home, 'AppData', 'Local');
    return [
      { id: 'chrome', name: 'Google Chrome', roots: [path.join(localAppData, 'Google', 'Chrome', 'User Data')] },
      { id: 'edge', name: 'Microsoft Edge', roots: [path.join(localAppData, 'Microsoft', 'Edge', 'User Data')] },
      { id: 'brave', name: 'Brave', roots: [path.join(localAppData, 'BraveSoftware', 'Brave-Browser', 'User Data')] },
      { id: 'chromium', name: 'Chromium', roots: [path.join(localAppData, 'Chromium', 'User Data')] },
    ];
  }
  const cfg = path.join(home, '.config');
  return [
    { id: 'chrome', name: 'Google Chrome', roots: [path.join(cfg, 'google-chrome')] },
    { id: 'edge', name: 'Microsoft Edge', roots: [path.join(cfg, 'microsoft-edge')] },
    { id: 'brave', name: 'Brave', roots: [path.join(cfg, 'BraveSoftware', 'Brave-Browser')] },
    { id: 'chromium', name: 'Chromium', roots: [path.join(cfg, 'chromium')] },
  ];
}

function firefoxProfileRoots(): string[] {
  const home = os.homedir();
  if (process.platform === 'darwin') {
    return [path.join(home, 'Library', 'Application Support', 'Firefox', 'Profiles')];
  }
  if (process.platform === 'win32') {
    const appData = process.env.APPDATA ?? path.join(home, 'AppData', 'Roaming');
    return [path.join(appData, 'Mozilla', 'Firefox', 'Profiles')];
  }
  return [path.join(home, '.mozilla', 'firefox'), path.join(home, 'snap', 'firefox', 'common', '.mozilla', 'firefox')];
}

function safeExists(p: string): boolean {
  try { return fs.existsSync(p); } catch { return false; }
}

export function detectBrowsers(): DetectedBrowser[] {
  const out: DetectedBrowser[] = [];

  for (const spec of chromiumSpecs()) {
    for (const root of spec.roots) {
      if (!safeExists(root)) continue;
      let profiles: string[] = [];
      try {
        profiles = fs.readdirSync(root).filter(d =>
          (d === 'Default' || /^Profile \d+$/.test(d)) && safeExists(path.join(root, d)));
      } catch { continue; }
      for (const prof of profiles) {
        const dir = path.join(root, prof);
        const hasBookmarks = safeExists(path.join(dir, 'Bookmarks'));
        const hasHistory = safeExists(path.join(dir, 'History'));
        if (!hasBookmarks && !hasHistory) continue;
        const suffix = prof === 'Default' ? '' : ` — ${prof}`;
        out.push({
          id: prof === 'Default' ? spec.id : `${spec.id}:${prof}`,
          name: `${spec.name}${suffix}`,
          kind: 'chromium',
          profileDir: dir,
          hasBookmarks,
          hasHistory,
        });
      }
      break; // first existing root wins per browser
    }
  }

  for (const root of firefoxProfileRoots()) {
    if (!safeExists(root)) continue;
    let dirs: string[] = [];
    try { dirs = fs.readdirSync(root); } catch { continue; }
    for (const d of dirs) {
      const dir = path.join(root, d);
      const places = path.join(dir, 'places.sqlite');
      if (!safeExists(places)) continue;
      out.push({
        id: `firefox:${d}`,
        name: dirs.filter(x => safeExists(path.join(root, x, 'places.sqlite'))).length > 1
          ? `Mozilla Firefox — ${d}`
          : 'Mozilla Firefox',
        kind: 'firefox',
        profileDir: dir,
        hasBookmarks: true,
        hasHistory: true,
      });
    }
  }

  return out;
}

// ── Read-only SQLite helper ──────────────────────────────────────────────────

function withSqliteCopy<T>(sourcePath: string, fn: (db: Database.Database) => T): T {
  const tmp = path.join(os.tmpdir(), `zio-import-${process.pid}-${Date.now()}-${Math.random().toString(36).slice(2)}.sqlite`);
  fs.copyFileSync(sourcePath, tmp);
  // Best-effort WAL sidecar copy so recent rows are visible.
  try {
    if (fs.existsSync(`${sourcePath}-wal`)) fs.copyFileSync(`${sourcePath}-wal`, `${tmp}-wal`);
  } catch { /* ignore */ }
  const db = new Database(tmp, { readonly: true });
  try {
    return fn(db);
  } finally {
    try { db.close(); } catch { /* ignore */ }
    try { fs.unlinkSync(tmp); } catch { /* ignore */ }
    try { fs.unlinkSync(`${tmp}-wal`); } catch { /* ignore */ }
  }
}

// ── Chromium readers ─────────────────────────────────────────────────────────

interface ChromiumBookmarkNode {
  type?: string;
  name?: string;
  url?: string;
  children?: ChromiumBookmarkNode[];
}

export function readChromiumBookmarks(profileDir: string): ImportedBookmark[] {
  const file = path.join(profileDir, 'Bookmarks');
  if (!safeExists(file)) return [];
  let parsed: { roots?: Record<string, ChromiumBookmarkNode> };
  try {
    parsed = JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return [];
  }
  const out: ImportedBookmark[] = [];
  const walk = (node: ChromiumBookmarkNode, folder: string | null) => {
    if (node.type === 'url' && isHttpUrl(node.url)) {
      out.push({ url: node.url, title: node.name || node.url, folder });
      return;
    }
    const nextFolder = node.type === 'folder' && node.name ? node.name : folder;
    for (const child of node.children ?? []) walk(child, nextFolder);
  };
  for (const root of Object.values(parsed.roots ?? {})) {
    if (root && typeof root === 'object') walk(root, null);
  }
  return out;
}

export function readChromiumHistory(profileDir: string): ImportedHistoryEntry[] {
  const file = path.join(profileDir, 'History');
  if (!safeExists(file)) return [];
  try {
    return withSqliteCopy(file, (db) => {
      const rows = db.prepare(`
        SELECT url, title, visit_count, last_visit_time
        FROM urls WHERE hidden = 0
        ORDER BY last_visit_time DESC LIMIT ?
      `).all(HISTORY_IMPORT_LIMIT) as Array<{ url: string; title: string | null; visit_count: number; last_visit_time: number }>;
      return rows.filter(r => isHttpUrl(r.url)).map(r => ({
        url: r.url,
        title: r.title || null,
        visitCount: Math.max(1, r.visit_count | 0),
        lastVisitedIso: chromiumTimeToIso(r.last_visit_time),
      }));
    });
  } catch {
    return [];
  }
}

// ── Firefox readers ──────────────────────────────────────────────────────────

export function readFirefoxBookmarks(profileDir: string): ImportedBookmark[] {
  const file = path.join(profileDir, 'places.sqlite');
  if (!safeExists(file)) return [];
  try {
    return withSqliteCopy(file, (db) => {
      const rows = db.prepare(`
        SELECT p.url AS url, COALESCE(b.title, p.title, p.url) AS title, f.title AS folder
        FROM moz_bookmarks b
        JOIN moz_places p ON p.id = b.fk
        LEFT JOIN moz_bookmarks f ON f.id = b.parent
        WHERE b.type = 1
      `).all() as Array<{ url: string; title: string; folder: string | null }>;
      return rows.filter(r => isHttpUrl(r.url)).map(r => ({
        url: r.url,
        title: r.title || r.url,
        folder: r.folder && !/^(menu|toolbar|unfiled|mobile|root)$/i.test(r.folder) ? r.folder : null,
      }));
    });
  } catch {
    return [];
  }
}

export function readFirefoxHistory(profileDir: string): ImportedHistoryEntry[] {
  const file = path.join(profileDir, 'places.sqlite');
  if (!safeExists(file)) return [];
  try {
    return withSqliteCopy(file, (db) => {
      const rows = db.prepare(`
        SELECT url, title, visit_count, last_visit_date
        FROM moz_places
        WHERE hidden = 0 AND visit_count > 0
        ORDER BY last_visit_date DESC LIMIT ?
      `).all(HISTORY_IMPORT_LIMIT) as Array<{ url: string; title: string | null; visit_count: number; last_visit_date: number | null }>;
      return rows.filter(r => isHttpUrl(r.url)).map(r => ({
        url: r.url,
        title: r.title || null,
        visitCount: Math.max(1, r.visit_count | 0),
        lastVisitedIso: firefoxTimeToIso(r.last_visit_date),
      }));
    });
  } catch {
    return [];
  }
}

// ── Bookmarks HTML (Netscape format, exported by every browser) ─────────────

export function parseBookmarksHtml(html: string): ImportedBookmark[] {
  const out: ImportedBookmark[] = [];
  const folderStack: string[] = [];
  // Tokenize the interesting tags in document order.
  const tokenRe = /<H3[^>]*>([\s\S]*?)<\/H3>|<\/DL>|<A\s+[^>]*HREF="([^"]*)"[^>]*>([\s\S]*?)<\/A>/gi;
  let m: RegExpExecArray | null;
  const decode = (s: string) => s
    .replace(/<[^>]+>/g, '')
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"').replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
    .trim();
  while ((m = tokenRe.exec(html)) !== null) {
    if (m[0].toUpperCase().startsWith('<H3')) {
      folderStack.push(decode(m[1] ?? ''));
    } else if (m[0].toUpperCase().startsWith('</DL')) {
      folderStack.pop();
    } else {
      const url = decode(m[2] ?? '');
      if (!isHttpUrl(url)) continue;
      const title = decode(m[3] ?? '') || url;
      const folder = folderStack.length > 0 ? folderStack[folderStack.length - 1] : null;
      out.push({ url, title, folder: folder || null });
    }
  }
  return out;
}

// ── Top-level import readers ─────────────────────────────────────────────────

export function readBrowserData(browser: DetectedBrowser, want: { bookmarks: boolean; history: boolean }): {
  bookmarks: ImportedBookmark[];
  history: ImportedHistoryEntry[];
} {
  if (browser.kind === 'firefox') {
    return {
      bookmarks: want.bookmarks ? readFirefoxBookmarks(browser.profileDir) : [],
      history: want.history ? readFirefoxHistory(browser.profileDir) : [],
    };
  }
  return {
    bookmarks: want.bookmarks ? readChromiumBookmarks(browser.profileDir) : [],
    history: want.history ? readChromiumHistory(browser.profileDir) : [],
  };
}
