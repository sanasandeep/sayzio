/**
 * Page context extraction and trimming for the AI panel.
 *
 * Runs in two stages:
 * 1. Extraction (runs in the page via preload script, returns raw PageContext)
 * 2. Trimming (runs in the main process / renderer, reduces payload size)
 *
 * Both stages are pure functions with no Electron or DOM dependencies (the
 * preload calls them with the raw DOM-extracted content), so they are fully
 * testable in Node/vitest.
 */

export interface PageContext {
  url: string;
  title: string;
  description: string | null;
  /** Full extracted readable text — may be very long */
  text: string;
  /** User's currently selected text (if any) */
  selection: string | null;
  /** Language detected from <html lang> or similar */
  lang: string | null;
  /** Author metadata if present */
  author: string | null;
  /** Publication date if detectable */
  publishedAt: string | null;
  /** List of visible phone numbers found on the page */
  phones: string[];
  /** List of visible email addresses found on the page */
  emails: string[];
  /**
   * Labeled one-line summaries of visual media on the page (optional —
   * older extractors don't send it). Examples:
   *   `Image: "Golden Gate Bridge at sunset"`
   *   `Figure: "Fig 3 — revenue by quarter"`
   *   `Video: "Product demo (2:31)"`
   */
  media?: string[];
}

export interface TrimmedContext {
  url: string;
  title: string;
  /** Trimmed/ranked text excerpt sent to the AI */
  excerpt: string;
  /** Whether the selection was used as the primary context */
  usedSelection: boolean;
  phones: string[];
  emails: string[];
  lang: string | null;
  author: string | null;
}

/** Maximum characters to send to the AI endpoint */
const MAX_EXCERPT_CHARS = 6000;
/** Maximum characters for the selection */
const MAX_SELECTION_CHARS = 3000;
/** Caps for the labeled media block appended to the excerpt */
export const MAX_MEDIA_LINES = 12;
export const MAX_MEDIA_LINE_CHARS = 160;

/**
 * Trim and rank a raw PageContext down to a short excerpt suitable for
 * sending to the AI API. Prioritizes:
 * 1. User's selection (if present and under the limit)
 * 2. Main readable text (truncated at word boundary)
 *
 * This runs in the main process — pure TS, no DOM.
 */
export function trimPageContext(ctx: PageContext, task?: string): TrimmedContext {
  let excerpt = '';
  let usedSelection = false;

  // If the user has selected text, prioritize it
  if (ctx.selection && ctx.selection.trim().length > 20) {
    const sel = ctx.selection.trim().slice(0, MAX_SELECTION_CHARS);
    excerpt = sel;
    usedSelection = true;
  } else {
    // Use the readable text, trimmed intelligently
    excerpt = smartTruncate(ctx.text, MAX_EXCERPT_CHARS, task);
  }

  // Append a clearly labeled, capped summary of visual media so the AI at
  // least knows an image/video exists (and its alt/caption/title) even when
  // no screenshot accompanies the question.
  const mediaBlock = buildMediaBlock(ctx.media);
  if (mediaBlock) excerpt = excerpt ? `${excerpt}\n\n${mediaBlock}` : mediaBlock;

  return {
    url: ctx.url,
    title: ctx.title,
    excerpt,
    usedSelection,
    phones: ctx.phones.slice(0, 20),
    emails: ctx.emails.slice(0, 20),
    lang: ctx.lang,
    author: ctx.author,
  };
}

/**
 * Smart truncation that:
 * 1. Removes excess whitespace/newlines
 * 2. Truncates at the nearest sentence boundary before the limit
 * 3. Falls back to word boundary if no sentence boundary is found
 */
export function smartTruncate(text: string, maxChars: number, _task?: string): string {
  const normalized = text
    .replace(/\r\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .replace(/[ \t]{2,}/g, ' ')
    .trim();

  if (normalized.length <= maxChars) {
    return normalized;
  }

  const truncated = normalized.slice(0, maxChars);

  // Try to find the last sentence boundary
  const sentenceEnd = truncated.search(/[.!?][^.!?]*$/);
  if (sentenceEnd > maxChars * 0.5) {
    return truncated.slice(0, sentenceEnd + 1).trim();
  }

  // Fall back to word boundary
  const wordEnd = truncated.lastIndexOf(' ');
  if (wordEnd > maxChars * 0.5) {
    return truncated.slice(0, wordEnd).trim() + '…';
  }

  return truncated + '…';
}

/**
 * Build the labeled `[Visual media on this page]` block from raw media
 * lines. Pure: dedupes, trims each line to MAX_MEDIA_LINE_CHARS, and caps
 * the count at MAX_MEDIA_LINES. Returns '' when there is nothing to say.
 */
export function buildMediaBlock(media: string[] | undefined): string {
  if (!media || media.length === 0) return '';
  const seen = new Set<string>();
  const lines: string[] = [];
  for (const raw of media) {
    const line = String(raw ?? '').replace(/\s+/g, ' ').trim();
    if (!line || seen.has(line)) continue;
    seen.add(line);
    lines.push(line.length > MAX_MEDIA_LINE_CHARS ? line.slice(0, MAX_MEDIA_LINE_CHARS - 1) + '…' : line);
    if (lines.length >= MAX_MEDIA_LINES) break;
  }
  if (lines.length === 0) return '';
  return '[Visual media on this page]:\n' + lines.map(l => `- ${l}`).join('\n');
}

/**
 * True when the user's question is likely about visual content on the
 * page (drives auto-attaching a page snapshot for the vision tier).
 */
export function looksVisualQuestion(text: string): boolean {
  return /\b(image|images|photo|photos|picture|pictures|pic|screenshot|chart|graph|diagram|infographic|figure|logo|icon|video|videos|thumbnail|banner|illustration|drawing|map)\b|\blook(s)? like\b|\bwhat does (this|it|that) show\b/i.test(text);
}

/** Screenshot payload caps shared by the capture IPC and the panel. */
export const SCREENSHOT_MAX_WIDTH = 1280;
export const SCREENSHOT_MAX_BYTES = 1_200_000; // decoded bytes, < server 1.5MB cap

/**
 * True when a page URL is eligible for AI screenshot capture. Refuses
 * internal/renderer-drawn pages and anything that is not plain http(s).
 */
export function isCapturableUrl(url: string | null | undefined): boolean {
  if (!url) return false;
  const u = url.trim().toLowerCase();
  if (!u || u.startsWith('about:') || u.startsWith('chrome') || u.startsWith('devtools:') || u.startsWith('file:') || u.startsWith('data:') || u.startsWith('view-source:')) {
    return false;
  }
  return u.startsWith('http://') || u.startsWith('https://');
}

/**
 * Extract phone numbers from text using a broad heuristic regex.
 * Returns unique, deduplicated numbers.
 */
export function extractPhones(text: string): string[] {
  // Matches NA format (optional +1 prefix) or international (+CC groups-with-spaces)
  const pattern = /(?:\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}|\+\d{1,3}(?:[-.\s]\d{1,5}){1,6}/g;
  const matches = text.match(pattern) ?? [];
  return [...new Set(matches.map(s => s.trim()))].slice(0, 30);
}

/**
 * Extract email addresses from text.
 * Returns unique, deduplicated emails.
 */
export function extractEmails(text: string): string[] {
  const pattern = /[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/g;
  const matches = text.match(pattern) ?? [];
  return [...new Set(matches.map(s => s.toLowerCase()))].slice(0, 30);
}

/**
 * Build a system prompt for the AI that includes page context.
 */
export function buildAiSystemPrompt(ctx: TrimmedContext, task: string): string {
  const lines: string[] = [
    `You are Zio, the AI assistant built into Zio Browser.`,
    `The user is currently viewing: ${ctx.title} (${ctx.url})`,
  ];

  if (ctx.author) lines.push(`Author: ${ctx.author}`);
  if (ctx.lang && ctx.lang !== 'en') lines.push(`Page language: ${ctx.lang}`);

  if (ctx.excerpt) {
    lines.push('');
    lines.push('=== Page Content ===');
    if (ctx.usedSelection) {
      lines.push('[User selection]:');
    }
    lines.push(ctx.excerpt);
    lines.push('===================');
  }

  lines.push('');
  lines.push(`Task: ${task}`);
  lines.push('Be concise and helpful. Ground your answer in the page content when relevant.');

  return lines.join('\n');
}

/**
 * Extract contact candidates from a page context.
 * Returns structured contact data ready for the Sayzio contacts API.
 */
export interface ExtractedContact {
  display_name?: string;
  emails: Array<{ value: string }>;
  phones: Array<{ value: string }>;
  source_url: string;
  notes?: string;
}

export function extractContacts(ctx: PageContext): ExtractedContact[] {
  const contacts: ExtractedContact[] = [];

  // Simple extraction: pair emails with phones if counts match
  const emails = ctx.emails;
  const phones = ctx.phones;

  if (emails.length === 0 && phones.length === 0) return contacts;

  // If the page has a title and some contacts, make a single contact record
  if (emails.length <= 3 && phones.length <= 3) {
    const contact: ExtractedContact = {
      emails: emails.map(e => ({ value: e })),
      phones: phones.map(p => ({ value: p })),
      source_url: ctx.url,
    };
    if (ctx.title) {
      contact.notes = `Extracted from: ${ctx.title}`;
    }
    if (contact.emails.length > 0 || contact.phones.length > 0) {
      contacts.push(contact);
    }
  } else {
    // Multiple contacts — create one per email (best-effort)
    for (const email of emails.slice(0, 10)) {
      contacts.push({
        emails: [{ value: email }],
        phones: [],
        source_url: ctx.url,
        notes: `Extracted from: ${ctx.title}`,
      });
    }
    for (const phone of phones.slice(0, 10)) {
      contacts.push({
        emails: [],
        phones: [{ value: phone }],
        source_url: ctx.url,
        notes: `Extracted from: ${ctx.title}`,
      });
    }
  }

  return contacts;
}
