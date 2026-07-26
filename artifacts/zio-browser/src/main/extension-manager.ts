/**
 * Extension manager — loads unpacked Chrome extensions into the default
 * browsing session and persists the chosen directories as a preference so
 * they reload on the next launch.
 *
 * Private windows never get extensions (their isolated session is untouched).
 */
import { app, session, dialog, BrowserWindow } from 'electron';
import * as fs from 'fs';
import * as path from 'path';
import { getPreference, setPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

export interface ExtensionInfo {
  id: string;
  name: string;
  version: string;
  path: string;
  /** True for the bundled Sayzio extension — always on, cannot be removed. */
  builtin?: boolean;
  /** True when the stored extension folder no longer exists on disk. */
  missing?: boolean;
}

/** Stored extension dirs that could not be found/loaded at startup. */
let missingExtensionDirs: string[] = [];

// ── Built-in Sayzio extension ─────────────────────────────────────────────────

let builtinExtensionId: string | null = null;

/**
 * Locate the bundled Sayzio extension directory.
 * Packaged builds ship it via electron-builder extraResources; in dev it is
 * read straight from build-resources/ in the repo.
 */
export function resolveBuiltinExtensionDir(): string | null {
  const candidates = app.isPackaged
    ? [path.join(process.resourcesPath, 'zio-extension')]
    : [
        path.join(app.getAppPath(), 'build-resources', 'zio-extension'),
        path.join(__dirname, '..', '..', '..', 'build-resources', 'zio-extension'),
      ];
  for (const dir of candidates) {
    if (isExtensionDir(dir)) return dir;
  }
  return null;
}

/** Load the bundled Sayzio extension into the default session (fail-soft). */
export async function loadBuiltinExtension(): Promise<void> {
  const dir = resolveBuiltinExtensionDir();
  if (!dir) return;
  try {
    const ext = await session.defaultSession.loadExtension(dir);
    builtinExtensionId = ext.id;
  } catch (err) {
    console.error('Failed to load built-in Sayzio extension:', err);
  }
}

/** Read the persisted list of unpacked-extension directories. */
export function getStoredExtensionPaths(): string[] {
  try {
    const raw = getPreference(PREFERENCE_KEYS.EXTENSION_PATHS) ?? '[]';
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((p): p is string => typeof p === 'string' && p.length > 0);
  } catch {
    return [];
  }
}

function storeExtensionPaths(paths: string[]): void {
  try {
    setPreference(PREFERENCE_KEYS.EXTENSION_PATHS, JSON.stringify(paths));
  } catch {
    // DB unavailable — extension still loads for this run.
  }
}

/** True when the extension is the bundled Sayzio one (id or path match). */
function isBuiltin(ext: { id: string; path: string }): boolean {
  if (builtinExtensionId && ext.id === builtinExtensionId) return true;
  const dir = resolveBuiltinExtensionDir();
  return dir !== null && path.resolve(ext.path) === path.resolve(dir);
}

function toInfo(ext: Electron.Extension): ExtensionInfo {
  return {
    id: ext.id,
    name: ext.name,
    version: ext.version,
    path: ext.path,
    builtin: isBuiltin(ext),
  };
}

/** Validate that a directory looks like an unpacked extension. */
export function isExtensionDir(dir: string): boolean {
  try {
    return fs.existsSync(path.join(dir, 'manifest.json'));
  } catch {
    return false;
  }
}

/**
 * Load all persisted extensions at startup. Entries whose directory no longer
 * exists are NOT silently dropped — they stay in the stored list and are
 * surfaced in the extensions panel with a "missing" state so the user can see
 * what happened and remove them explicitly.
 */
export async function loadStoredExtensions(): Promise<void> {
  const stored = getStoredExtensionPaths();
  missingExtensionDirs = [];
  if (stored.length === 0) return;
  const builtinDir = resolveBuiltinExtensionDir();
  for (const dir of stored) {
    // The bundled extension is loaded separately — never double-load it.
    if (builtinDir && path.resolve(dir) === path.resolve(builtinDir)) continue;
    if (!isExtensionDir(dir)) {
      missingExtensionDirs.push(dir);
      continue;
    }
    try {
      await session.defaultSession.loadExtension(dir);
    } catch (err) {
      console.error(`Failed to load extension at ${dir}:`, err);
      missingExtensionDirs.push(dir);
    }
  }
}

/**
 * List the currently loaded extensions in the default session, plus any
 * stored extensions whose folder is missing (marked `missing: true`; their
 * id is `missing:<path>` so removeExtension can forget them).
 */
export function listExtensions(): ExtensionInfo[] {
  let loaded: ExtensionInfo[] = [];
  try {
    loaded = session.defaultSession.getAllExtensions().map(toInfo);
  } catch {
    loaded = [];
  }
  const missing: ExtensionInfo[] = missingExtensionDirs.map(dir => ({
    id: `missing:${dir}`,
    name: path.basename(dir) || dir,
    version: '',
    path: dir,
    missing: true,
  }));
  return [...loaded, ...missing];
}

/**
 * Open a directory picker and load the chosen folder as an unpacked
 * extension. Returns the loaded extension info, or an error string.
 */
export async function addExtensionFromDialog(
  win: BrowserWindow,
): Promise<{ ok: true; extension: ExtensionInfo } | { ok: false; error: string }> {
  const result = await dialog.showOpenDialog(win, {
    title: 'Choose an unpacked extension folder',
    properties: ['openDirectory'],
  });
  if (result.canceled || result.filePaths.length === 0) {
    return { ok: false, error: 'cancelled' };
  }
  const dir = result.filePaths[0]!;
  if (!isExtensionDir(dir)) {
    return { ok: false, error: 'That folder has no manifest.json — pick the extension\'s root folder.' };
  }
  try {
    const ext = await session.defaultSession.loadExtension(dir);
    const stored = getStoredExtensionPaths();
    if (!stored.includes(dir)) storeExtensionPaths([...stored, dir]);
    // A previously "missing" path may have been restored — drop the stale flag.
    missingExtensionDirs = missingExtensionDirs.filter(
      d => path.resolve(d) !== path.resolve(dir),
    );
    return { ok: true, extension: toInfo(ext) };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return { ok: false, error: message };
  }
}

/** Remove a loaded extension (by id) and forget its stored path. */
export function removeExtension(id: string): boolean {
  if (builtinExtensionId && id === builtinExtensionId) return false;
  // Missing entry — just forget the stored path.
  if (id.startsWith('missing:')) {
    const dir = id.slice('missing:'.length);
    missingExtensionDirs = missingExtensionDirs.filter(d => d !== dir);
    storeExtensionPaths(getStoredExtensionPaths().filter(p => p !== dir));
    return true;
  }
  try {
    const ext = session.defaultSession.getExtension(id);
    if (!ext) return false;
    session.defaultSession.removeExtension(id);
    storeExtensionPaths(getStoredExtensionPaths().filter(p => p !== ext.path));
    return true;
  } catch {
    return false;
  }
}
