/**
 * Clipboard content detection for the ChromeBar clipboard tool.
 * Pure functions — no Electron dependencies — fully testable.
 */

export type ClipboardContentKind = 'url' | 'email' | 'phone' | 'text' | 'empty';

export interface ClipboardContent {
  kind: ClipboardContentKind;
  /** Trimmed raw clipboard text. */
  text: string;
  /** For kind 'url': the normalized navigable URL (scheme added if missing). */
  url?: string;
}

const SCHEME_PATTERN = /^https?:\/\//i;
const DOMAIN_PATTERN = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z]{2,})+([/:?#].*)?$/i;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;
const PHONE_PATTERN = /^\+?[\d\s().-]{7,20}$/;

const MAX_DETECT_LENGTH = 4096;

/** Classify raw clipboard text into url / email / phone / text / empty. */
export function detectClipboardContent(raw: string): ClipboardContent {
  const text = (raw ?? '').trim();
  if (!text) return { kind: 'empty', text: '' };

  // Very large payloads are plain text — don't run regexes over megabytes.
  if (text.length > MAX_DETECT_LENGTH) {
    return { kind: 'text', text };
  }

  // Single-line checks only — multi-line content is plain text.
  const singleLine = !/[\r\n]/.test(text);

  if (singleLine) {
    // Explicit http(s) URL
    if (SCHEME_PATTERN.test(text)) {
      try {
        const u = new URL(text);
        return { kind: 'url', text, url: u.toString() };
      } catch {
        /* fall through */
      }
    }

    // Email (before domain check — "a@b.com" also matches domain-ish shapes)
    if (EMAIL_PATTERN.test(text)) {
      return { kind: 'email', text };
    }

    // Phone number
    const digitCount = (text.match(/\d/g) ?? []).length;
    if (PHONE_PATTERN.test(text) && digitCount >= 7) {
      return { kind: 'phone', text };
    }

    // Bare domain / domain+path → treat as URL with https://
    if (!/\s/.test(text) && DOMAIN_PATTERN.test(text)) {
      try {
        const u = new URL(`https://${text}`);
        return { kind: 'url', text, url: u.toString() };
      } catch {
        /* fall through */
      }
    }
  }

  return { kind: 'text', text };
}

/** Short human label for a detected kind. */
export function clipboardKindLabel(kind: ClipboardContentKind): string {
  switch (kind) {
    case 'url': return 'Web link';
    case 'email': return 'Email address';
    case 'phone': return 'Phone number';
    case 'text': return 'Text';
    case 'empty': return 'Empty';
  }
}
