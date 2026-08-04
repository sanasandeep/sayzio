import { describe, it, expect } from 'vitest';
import {
  trimPageContext,
  smartTruncate,
  extractPhones,
  extractEmails,
  buildAiSystemPrompt,
  extractContacts,
  buildMediaBlock,
  looksVisualQuestion,
  isCapturableUrl,
  MAX_MEDIA_LINES,
  MAX_MEDIA_LINE_CHARS,
  SCREENSHOT_MAX_BYTES,
  SCREENSHOT_MAX_WIDTH,
  type PageContext,
} from '../src/shared/context-extractor';

const baseContext: PageContext = {
  url: 'https://example.com/contact',
  title: 'Contact Us',
  description: 'Reach us at info@example.com',
  text: 'We are Example Corp. Call us at 555-123-4567. Email: sales@example.com. Address: 123 Main St.',
  selection: null,
  lang: 'en',
  author: 'Example Corp',
  publishedAt: null,
  phones: ['555-123-4567'],
  emails: ['info@example.com', 'sales@example.com'],
};

describe('trimPageContext', () => {
  it('includes url, title, and excerpt', () => {
    const result = trimPageContext(baseContext);
    expect(result.url).toBe(baseContext.url);
    expect(result.title).toBe(baseContext.title);
    expect(result.excerpt).toBeTruthy();
    expect(result.usedSelection).toBe(false);
  });

  it('prioritizes user selection when present', () => {
    const ctx: PageContext = { ...baseContext, selection: 'This is the selected text from the user which is fairly long' };
    const result = trimPageContext(ctx);
    expect(result.usedSelection).toBe(true);
    expect(result.excerpt).toContain('selected text from the user');
  });

  it('ignores very short selections', () => {
    const ctx: PageContext = { ...baseContext, selection: 'hi' };
    const result = trimPageContext(ctx);
    expect(result.usedSelection).toBe(false);
  });

  it('includes phones and emails', () => {
    const result = trimPageContext(baseContext);
    expect(result.phones).toContain('555-123-4567');
    expect(result.emails).toContain('info@example.com');
  });

  it('truncates long text to MAX_EXCERPT_CHARS', () => {
    const longText = 'A'.repeat(10000) + '. end.';
    const ctx: PageContext = { ...baseContext, text: longText };
    const result = trimPageContext(ctx);
    expect(result.excerpt.length).toBeLessThanOrEqual(6100);
  });

  it('limits phones to 20', () => {
    const phones = Array.from({ length: 30 }, (_, i) => `555-000-${i.toString().padStart(4, '0')}`);
    const ctx: PageContext = { ...baseContext, phones };
    const result = trimPageContext(ctx);
    expect(result.phones.length).toBeLessThanOrEqual(20);
  });
});

describe('smartTruncate', () => {
  it('returns the text unchanged if under the limit', () => {
    expect(smartTruncate('short text', 1000)).toBe('short text');
  });

  it('truncates at sentence boundary', () => {
    const text = 'First sentence. Second sentence. Third sentence.';
    const result = smartTruncate(text, 20);
    expect(result).toMatch(/First sentence\./);
    expect(result).not.toContain('Second sentence');
  });

  it('adds ellipsis when truncating at word boundary', () => {
    const text = 'word '.repeat(100);
    const result = smartTruncate(text, 50);
    expect(result.length).toBeLessThanOrEqual(60);
  });

  it('normalizes multiple blank lines', () => {
    const text = 'para one\n\n\n\n\npara two';
    const result = smartTruncate(text, 1000);
    expect(result).not.toMatch(/\n{3,}/);
  });
});

describe('extractPhones', () => {
  it('extracts US phone numbers', () => {
    const text = 'Call us at (555) 123-4567 or 555.987.6543';
    const phones = extractPhones(text);
    expect(phones.length).toBe(2);
  });

  it('extracts international numbers', () => {
    const text = 'International: +44 20 7946 0958';
    const phones = extractPhones(text);
    expect(phones.length).toBeGreaterThan(0);
  });

  it('deduplicates numbers', () => {
    const text = '555-123-4567 and 555-123-4567 again';
    const phones = extractPhones(text);
    expect(phones.length).toBe(1);
  });

  it('returns empty array for no phones', () => {
    expect(extractPhones('No phone numbers here')).toHaveLength(0);
  });
});

describe('extractEmails', () => {
  it('extracts standard email addresses', () => {
    const text = 'Contact info@example.com or support@sayzio.com';
    const emails = extractEmails(text);
    expect(emails).toContain('info@example.com');
    expect(emails).toContain('support@sayzio.com');
  });

  it('lowercases extracted emails', () => {
    const emails = extractEmails('Send to INFO@EXAMPLE.COM');
    expect(emails).toContain('info@example.com');
  });

  it('deduplicates emails', () => {
    const emails = extractEmails('info@example.com and info@example.com');
    expect(emails.length).toBe(1);
  });
});

describe('buildAiSystemPrompt', () => {
  it('includes url and title', () => {
    const ctx = trimPageContext(baseContext);
    const prompt = buildAiSystemPrompt(ctx, 'Summarize this page');
    expect(prompt).toContain(baseContext.url);
    expect(prompt).toContain(baseContext.title);
  });

  it('includes the task', () => {
    const ctx = trimPageContext(baseContext);
    const prompt = buildAiSystemPrompt(ctx, 'Summarize this page');
    expect(prompt).toContain('Summarize this page');
  });

  it('includes author when present', () => {
    const ctx = trimPageContext(baseContext);
    const prompt = buildAiSystemPrompt(ctx, 'test');
    expect(prompt).toContain('Example Corp');
  });
});

describe('extractContacts', () => {
  it('returns empty array when no contacts', () => {
    const ctx: PageContext = { ...baseContext, emails: [], phones: [] };
    expect(extractContacts(ctx)).toHaveLength(0);
  });

  it('creates a single contact for simple pages', () => {
    const contacts = extractContacts(baseContext);
    expect(contacts.length).toBeGreaterThan(0);
    expect(contacts[0]?.emails.length).toBeGreaterThan(0);
    expect(contacts[0]?.source_url).toBe(baseContext.url);
  });

  it('creates one contact per email for many-email pages', () => {
    const manyEmails = Array.from({ length: 10 }, (_, i) => `user${i}@example.com`);
    const ctx: PageContext = { ...baseContext, emails: manyEmails, phones: [] };
    const contacts = extractContacts(ctx);
    expect(contacts.length).toBe(10);
  });
});

describe('media block (Ask Zio vision text tier)', () => {
  it('appends a labeled media block to the excerpt', () => {
    const ctx: PageContext = {
      ...baseContext,
      media: ['Image: "Golden Gate Bridge at sunset"', 'Video: "Product demo"'],
    };
    const result = trimPageContext(ctx);
    expect(result.excerpt).toContain('[Visual media on this page]:');
    expect(result.excerpt).toContain('- Image: "Golden Gate Bridge at sunset"');
    expect(result.excerpt).toContain('- Video: "Product demo"');
  });

  it('appends media even when the selection is used', () => {
    const ctx: PageContext = {
      ...baseContext,
      selection: 'This is the selected text from the user which is fairly long',
      media: ['Figure: "Fig 3 — revenue by quarter"'],
    };
    const result = trimPageContext(ctx);
    expect(result.usedSelection).toBe(true);
    expect(result.excerpt).toContain('Figure: "Fig 3 — revenue by quarter"');
  });

  it('omits the block when media is missing or empty (back-compat)', () => {
    expect(trimPageContext(baseContext).excerpt).not.toContain('[Visual media');
    expect(trimPageContext({ ...baseContext, media: [] }).excerpt).not.toContain('[Visual media');
    expect(buildMediaBlock(undefined)).toBe('');
    expect(buildMediaBlock([])).toBe('');
  });

  it('caps line count and per-line length, dedupes, and drops blanks', () => {
    const media = [
      ...Array.from({ length: 30 }, (_, i) => `Image: "photo ${i}"`),
      'Image: "photo 0"', // dup
      '   ',
    ];
    const block = buildMediaBlock(media);
    const lines = block.split('\n').slice(1);
    expect(lines.length).toBe(MAX_MEDIA_LINES);

    const long = buildMediaBlock(['Image: "' + 'x'.repeat(500) + '"']);
    const line = long.split('\n')[1]!;
    expect(line.length).toBeLessThanOrEqual(2 + MAX_MEDIA_LINE_CHARS);
    expect(line.endsWith('…')).toBe(true);
  });
});

describe('looksVisualQuestion', () => {
  it('matches questions about images, charts, and videos', () => {
    expect(looksVisualQuestion('What does this image show?')).toBe(true);
    expect(looksVisualQuestion('explain the chart on this page')).toBe(true);
    expect(looksVisualQuestion('what is the video about')).toBe(true);
    expect(looksVisualQuestion('What does this look like?')).toBe(true);
    expect(looksVisualQuestion('describe the photo')).toBe(true);
  });

  it('does not match plain text questions', () => {
    expect(looksVisualQuestion('Summarize this page')).toBe(false);
    expect(looksVisualQuestion('What is the pricing?')).toBe(false);
    expect(looksVisualQuestion('imagine a better headline')).toBe(false);
  });
});

describe('isCapturableUrl (screenshot IPC guard)', () => {
  it('accepts plain http(s) pages', () => {
    expect(isCapturableUrl('https://example.com/page')).toBe(true);
    expect(isCapturableUrl('http://example.com')).toBe(true);
  });

  it('refuses internal and non-web pages', () => {
    expect(isCapturableUrl('about:newtab')).toBe(false);
    expect(isCapturableUrl('about:sayzio')).toBe(false);
    expect(isCapturableUrl('about:zio')).toBe(false);
    expect(isCapturableUrl('chrome://settings')).toBe(false);
    expect(isCapturableUrl('devtools://devtools/bundled')).toBe(false);
    expect(isCapturableUrl('file:///etc/passwd')).toBe(false);
    expect(isCapturableUrl('data:text/html,hi')).toBe(false);
    expect(isCapturableUrl('view-source:https://example.com')).toBe(false);
    expect(isCapturableUrl('')).toBe(false);
    expect(isCapturableUrl(null)).toBe(false);
    expect(isCapturableUrl(undefined)).toBe(false);
  });

  it('screenshot caps stay under the server-side limit', () => {
    expect(SCREENSHOT_MAX_BYTES).toBeLessThan(1_500_000);
    expect(SCREENSHOT_MAX_WIDTH).toBeLessThanOrEqual(1600);
  });
});
