/**
 * Permission request/check handler for Zio Browser.
 *
 * Intercepts Electron's session permission system:
 *   - setPermissionCheckHandler  — fast synchronous lookup from SQLite
 *   - setPermissionRequestHandler — routes unknown permissions to a renderer
 *     prompt and waits for user decision, then persists the result.
 *
 * Permissions covered: camera, microphone, notifications, geolocation, midi,
 * pointerLock, fullscreen, openExternal, clipboard-read, clipboard-sanitized-write.
 */
import { desktopCapturer } from 'electron';
import type { Session, BrowserWindow, WebContents } from 'electron';
import { getSitePermission, setSitePermission } from './db';

export type PermissionDecision = 'allow' | 'block';

interface PendingRequest {
  callback: (granted: boolean) => void;
  timer: ReturnType<typeof setTimeout>;
}

const _pending = new Map<string, PendingRequest>();

/** Permissions that we gate — anything else is allowed automatically. */
const GATED_PERMISSIONS = new Set([
  'camera', 'microphone', 'notifications', 'geolocation',
  'midi', 'midiSysex', 'pointerLock', 'fullscreen',
  'clipboard-read', 'clipboard-sanitized-write', 'media',
  'display-capture',
]);

/** Permissions that are safe to auto-allow without a prompt. */
const AUTO_ALLOW = new Set(['fullscreen']);

function extractOrigin(webContents: WebContents): string {
  try {
    const url = new URL(webContents.getURL());
    return url.origin;
  } catch {
    return 'unknown';
  }
}

/** Normalise Electron permission names for display and storage. */
function normalisePermission(permission: string): string {
  if (permission === 'midiSysex') return 'midi';
  if (permission === 'media') return 'camera';
  return permission;
}

/**
 * Install permission request and check handlers on the given session.
 * `win` must be the app-chrome BrowserWindow; IPC events are sent to it.
 * `isTabWebContents` should return true iff a given WebContents is one of the
 * managed tab views (so we don't accidentally gate the main renderer UI).
 */
export function setupPermissionHandlers(
  sess: Session,
  win: BrowserWindow,
  isTabWebContents: (wc: WebContents) => boolean,
): void {
  // ── Synchronous check (used by Permissions API before requesting) ──────────
  sess.setPermissionCheckHandler((webContents, permission) => {
    if (!webContents || !isTabWebContents(webContents)) return true;
    const perm = normalisePermission(permission);
    if (!GATED_PERMISSIONS.has(perm)) return true;
    if (AUTO_ALLOW.has(perm)) return true;

    const origin = extractOrigin(webContents);
    const stored = getSitePermission(origin, perm);
    if (stored === 'allow') return true;
    if (stored === 'block') return false;
    // Unknown → deny until explicitly asked
    return false;
  });

  // ── Async request (navigator.requestPermission / getUserMedia / etc.) ──────
  sess.setPermissionRequestHandler((webContents, permission, callback, details) => {
    if (!isTabWebContents(webContents)) {
      callback(true);
      return;
    }

    const perm = normalisePermission(permission);

    if (!GATED_PERMISSIONS.has(perm)) {
      callback(true);
      return;
    }

    if (AUTO_ALLOW.has(perm)) {
      callback(true);
      return;
    }

    const origin = extractOrigin(webContents);
    const stored = getSitePermission(origin, perm);

    if (stored === 'allow') {
      callback(true);
      return;
    }
    if (stored === 'block') {
      callback(false);
      return;
    }

    // Unknown — send a prompt to the renderer and wait for a response.
    const requestId = crypto.randomUUID();

    // Safety timeout: if the renderer never responds (e.g. it's hidden), deny.
    const timer = setTimeout(() => {
      if (_pending.has(requestId)) {
        _pending.delete(requestId);
        callback(false);
      }
    }, 30_000);

    _pending.set(requestId, { callback, timer });

    win.webContents.send('permission:request', {
      requestId,
      origin,
      permission: perm,
      requestingUrl: (details as { requestingUrl?: string }).requestingUrl ?? origin,
    });
  });

  // ── Screen sharing (getDisplayMedia) ────────────────────────────────────────
  // Electron requires an explicit display-media handler; without one every
  // getDisplayMedia call fails. Honor the stored per-site 'display-capture'
  // decision: allow → share the primary screen; anything else → deny (the
  // permission request handler above still drives the ask/remember prompt).
  sess.setDisplayMediaRequestHandler((request, callback) => {
    const origin = (() => {
      try { return new URL(request.securityOrigin ?? '').origin; } catch { return 'unknown'; }
    })();
    if (getSitePermission(origin, 'display-capture') !== 'allow') {
      callback({});
      return;
    }
    desktopCapturer.getSources({ types: ['screen'] }).then(sources => {
      if (sources.length > 0) callback({ video: sources[0] });
      else callback({});
    }).catch(() => callback({}));
  });
}

/**
 * Called by the IPC handler when the renderer resolves a permission prompt.
 * `remember` = true → persist the decision to SQLite.
 */
export function resolvePermissionRequest(
  requestId: string,
  decision: PermissionDecision,
  remember: boolean,
  origin: string,
  permission: string,
): void {
  const pending = _pending.get(requestId);
  if (!pending) return;

  clearTimeout(pending.timer);
  _pending.delete(requestId);

  if (remember) {
    setSitePermission(origin, permission, decision);
  }

  pending.callback(decision === 'allow');
}
