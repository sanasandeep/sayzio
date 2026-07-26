/**
 * Privacy controls for Zio Browser.
 *
 * - "Do Not Track": appends the DNT: 1 header to every outgoing request.
 * - "Block third-party cookies": strips Cookie request headers and Set-Cookie
 *   response headers when the request host does not belong to the same site
 *   as the initiating page (approximated by the request's referrer host).
 *
 * Handlers are installed once per session and short-circuit via module flags,
 * mirroring the tracker-blocker pattern.
 */
import type { Session } from 'electron';

let _doNotTrack = false;
let _blockThirdPartyCookies = false;
const _installedSessions = new WeakSet<Session>();

/** Registrable base-domain approximation: last two labels (last three for co.uk-style suffixes). */
function baseDomain(host: string): string {
  const parts = host.toLowerCase().split('.').filter(Boolean);
  if (parts.length <= 2) return parts.join('.');
  const twoLevelSuffixes = new Set(['co', 'com', 'net', 'org', 'gov', 'edu', 'ac']);
  const secondLast = parts[parts.length - 2];
  if (secondLast && secondLast.length <= 3 && twoLevelSuffixes.has(secondLast)) {
    return parts.slice(-3).join('.');
  }
  return parts.slice(-2).join('.');
}

/** True when the request URL is third-party relative to the page that initiated it. */
function isThirdParty(url: string, referrer: string | undefined): boolean {
  if (!referrer) return false; // top-level navigations have no referrer — treat as first-party
  try {
    const reqHost = new URL(url).hostname;
    const refHost = new URL(referrer).hostname;
    if (!reqHost || !refHost) return false;
    return baseDomain(reqHost) !== baseDomain(refHost);
  } catch {
    return false;
  }
}

/**
 * Install the privacy webRequest handlers on a session.
 * Safe to call once at startup; runtime toggling goes through the setters.
 */
export function setupPrivacyControls(
  sess: Session,
  initialDoNotTrack: boolean,
  initialBlockThirdPartyCookies: boolean,
): void {
  _doNotTrack = initialDoNotTrack;
  _blockThirdPartyCookies = initialBlockThirdPartyCookies;
  installPrivacyHooks(sess);
}

/**
 * Idempotently install the webRequest hooks on a session. Call this for every
 * session that carries tab traffic (default session + each profile partition)
 * so DNT / third-party-cookie blocking applies everywhere.
 */
export function installPrivacyHooks(sess: Session): void {
  if (_installedSessions.has(sess)) return;
  _installedSessions.add(sess);

  sess.webRequest.onBeforeSendHeaders((details, callback) => {
    if (!_doNotTrack && !_blockThirdPartyCookies) {
      callback({ requestHeaders: details.requestHeaders });
      return;
    }
    const headers = { ...details.requestHeaders };
    if (_doNotTrack) {
      headers['DNT'] = '1';
    }
    if (_blockThirdPartyCookies && isThirdParty(details.url, details.referrer)) {
      delete headers['Cookie'];
      delete headers['cookie'];
    }
    callback({ requestHeaders: headers });
  });

  sess.webRequest.onHeadersReceived((details, callback) => {
    if (!_blockThirdPartyCookies || !isThirdParty(details.url, details.referrer)) {
      callback({ responseHeaders: details.responseHeaders });
      return;
    }
    const headers: Record<string, string[]> = {};
    for (const [key, value] of Object.entries(details.responseHeaders ?? {})) {
      if (key.toLowerCase() === 'set-cookie') continue;
      headers[key] = value;
    }
    callback({ responseHeaders: headers });
  });
}

export function setDoNotTrack(enabled: boolean): void {
  _doNotTrack = enabled;
}

export function isDoNotTrackEnabled(): boolean {
  return _doNotTrack;
}

export function setBlockThirdPartyCookies(enabled: boolean): void {
  _blockThirdPartyCookies = enabled;
}

export function isBlockThirdPartyCookiesEnabled(): boolean {
  return _blockThirdPartyCookies;
}
