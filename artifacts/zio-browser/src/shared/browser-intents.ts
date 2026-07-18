/**
 * Client-side browser-management intent detection for the Zio chat assistant.
 * All intent matching runs locally — no browsing data is sent to the backend.
 */

export type BrowserIntent =
  | { action: 'show_history' }
  | { action: 'clear_history'; destructive: true }
  | { action: 'show_cookies' }
  | { action: 'clear_cookies_for_site'; destructive: true }
  | { action: 'clear_cookies_all'; destructive: true }
  | { action: 'show_passwords' }
  | { action: 'delete_password_for'; query: string; destructive: true }
  | { action: 'show_downloads' }
  | { action: 'clear_browsing_data'; destructive: true };

/**
 * Detect whether the user's message maps to a local browser-management action.
 * Returns null if no intent is matched (message goes to the regular AI assistant).
 */
export function detectBrowserIntent(message: string): BrowserIntent | null {
  const m = message.trim().toLowerCase().replace(/['"''""]/g, '');

  // ── Clear browsing data (most specific — check first) ─────────────────────
  if (
    /clear\s+(?:all\s+)?(?:browsing\s+data|browser\s+data|everything)|wipe\s+(?:all\s+)?(?:data|browser|everything)/.test(m) ||
    /clear\s+all\s+(?:history|cookies)\s+and/.test(m)
  ) {
    return { action: 'clear_browsing_data', destructive: true };
  }

  // ── History ───────────────────────────────────────────────────────────────
  if (
    /(?:clear|delete|erase|remove|wipe)\s+(?:my\s+)?(?:browsing\s+)?history/.test(m) ||
    /(?:clear|delete|wipe)\s+all\s+history/.test(m)
  ) {
    return { action: 'clear_history', destructive: true };
  }
  if (
    /(?:show|open|see|view|list|check)\s+(?:my\s+)?(?:browsing\s+)?history/.test(m) ||
    /(?:what\s+(?:sites?|pages?)\s+(?:have\s+i|did\s+i)\s+visit)/.test(m) ||
    m === 'history' ||
    m === 'my history' ||
    m === 'browsing history'
  ) {
    return { action: 'show_history' };
  }

  // ── Cookies ───────────────────────────────────────────────────────────────
  if (
    /clear\s+(?:all\s+)?cookies\s+(?:for\s+this\s+site|for\s+this\s+page|here|from\s+this)/.test(m) ||
    /delete\s+(?:all\s+)?cookies\s+(?:for\s+this\s+site|on\s+this\s+site|here)/.test(m) ||
    /clear\s+this\s+site(?:s)?\s+cookies/.test(m)
  ) {
    return { action: 'clear_cookies_for_site', destructive: true };
  }
  if (
    /clear\s+all\s+cookies/.test(m) ||
    /delete\s+all\s+cookies/.test(m) ||
    /remove\s+all\s+cookies/.test(m)
  ) {
    return { action: 'clear_cookies_all', destructive: true };
  }
  if (
    /(?:what|show|list|view|check|see|get)\s+(?:all\s+)?cookies/.test(m) ||
    /cookies?\s+(?:for|on|from)\s+this\s+site/.test(m) ||
    m === 'cookies' ||
    m === 'show cookies' ||
    m === 'list cookies' ||
    m === 'what cookies'
  ) {
    return { action: 'show_cookies' };
  }

  // ── Passwords ─────────────────────────────────────────────────────────────
  const pwDeleteMatch = m.match(
    /(?:delete|remove|forget|clear)\s+(?:my\s+)?(?:saved\s+)?password\s+(?:for|to)\s+(.+)/,
  );
  if (pwDeleteMatch) {
    return { action: 'delete_password_for', query: (pwDeleteMatch[1] ?? '').trim(), destructive: true };
  }
  if (
    /(?:show|list|view|check|see|manage)\s+(?:my\s+)?(?:saved\s+)?passwords?/.test(m) ||
    m === 'passwords' ||
    m === 'saved passwords' ||
    m === 'my passwords'
  ) {
    return { action: 'show_passwords' };
  }

  // ── Downloads ─────────────────────────────────────────────────────────────
  if (
    /(?:show|list|view|check|see|open)\s+(?:my\s+)?(?:recent\s+)?downloads?/.test(m) ||
    m === 'downloads' ||
    m === 'my downloads'
  ) {
    return { action: 'show_downloads' };
  }

  return null;
}

/** Human-readable description of an intent for the assistant response bubble. */
export function describeIntent(intent: BrowserIntent): string {
  switch (intent.action) {
    case 'show_history': return 'Opening your browsing history…';
    case 'clear_history': return 'Ready to clear your browsing history.';
    case 'show_cookies': return 'Loading cookies for this site…';
    case 'clear_cookies_for_site': return 'Ready to clear cookies for this site.';
    case 'clear_cookies_all': return 'Ready to clear all cookies.';
    case 'show_passwords': return 'Opening your saved passwords…';
    case 'delete_password_for': return `Ready to delete the saved password for "${intent.query}".`;
    case 'show_downloads': return 'Opening your downloads…';
    case 'clear_browsing_data': return 'Ready to clear all browsing data (history, cookies, and cache).';
  }
}
