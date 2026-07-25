/**
 * Extension manager — loads unpacked Chrome extensions into the default
 * browsing session and persists the chosen directories as a preference so
 * they reload on the next launch.
 *
 * Private windows never get extensions (their isolated session is untouched).
 */
import { session, dialog, BrowserWindow } from 'electron';
import * as fs from 'fs';
import * as path from 'path';
import { getPreference, setPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

export interface ExtensionInfo {
  id: string;
  name: string;
  version: string;
  path: string;
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

function toInfo(ext: Electron.Extension): ExtensionInfo {
  return { id: ext.id, name: ext.name, version: ext.version, path: ext.path };
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
 * Load all persisted extensions at startup. Silently drops entries whose
 * directory no longer exists (and prunes them from the stored list).
 */
export async function loadStoredExtensions(): Promise<void> {
  const stored = getStoredExtensionPaths();
  if (stored.length === 0) return;
  const kept: string[] = [];
  for (const dir of stored) {
    if (!isExtensionDir(dir)) continue;
    try {
      await session.defaultSession.loadExtension(dir);
      kept.push(dir);
    } catch (err) {
      console.error(`Failed to load extension at ${dir}:`, err);
    }
  }
  if (kept.length !== stored.length) storeExtensionPaths(kept);
}

/** List the currently loaded extensions in the default session. */
export function listExtensions(): ExtensionInfo[] {
  try {
    return session.defaultSession.getAllExtensions().map(toInfo);
  } catch {
    return [];
  }
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
    return { ok: true, extension: toInfo(ext) };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return { ok: false, error: message };
  }
}

/** Remove a loaded extension (by id) and forget its stored path. */
export function removeExtension(id: string): boolean {
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
