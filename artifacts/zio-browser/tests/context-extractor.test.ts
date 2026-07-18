import { describe, it, expect } from 'vitest';
import {
  trimPageContext,
  smartTruncate,
  extractPhones,
  extractEmails,
  buildAiSystemPrompt,
  extractContacts,
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
