import { describe, it, expect } from 'vitest';
import {
  parseOmniboxInput,
  formatDisplayUrl,
  extractSearchQuery,
  normalizeUrlForHistory,
  SEARCH_ENGINES,
} from '../src/shared/omnibox';

describe('parseOmniboxInput', () => {
  it('returns search for empty input', () => {
    const result = parseOmniboxInput('');
    expect(result.kind).toBe('search');
  });

  it('recognizes https:// URLs', () => {
    const result = parseOmniboxInput('https://example.com/path?q=1');
    expect(result.kind).toBe('url');
    expect(result.navigateUrl).toBe('https://example.com/path?q=1');
  });

  it('recognizes http:// URLs', () => {
    const result = parseOmniboxInput('http://example.com');
    expect(result.kind).toBe('url');
    expect(result.navigateUrl).toBe('http://example.com');
  });

  it('recognizes file:// URLs', () => {
    const result = parseOmniboxInput('file:///Users/test/doc.pdf');
    expect(result.kind).toBe('file');
    expect(result.navigateUrl).toBe('file:///Users/test/doc.pdf');
  });

  it('recognizes localhost', () => {
    const result = parseOmniboxInput('localhost:3000');
    expect(result.kind).toBe('localhost');
    expect(result.navigateUrl).toBe('http://localhost:3000');
  });

  it('recognizes localhost without port', () => {
    const result = parseOmniboxInput('localhost');
    expect(result.kind).toBe('localhost');
    expect(result.navigateUrl).toBe('http://localhost');
  });

  it('recognizes IP addresses', () => {
    const result = parseOmniboxInput('192.168.1.1');
    expect(result.kind).toBe('ip');
    expect(result.navigateUrl).toBe('http://192.168.1.1');
  });

  it('recognizes domain names with TLD', () => {
    const result = parseOmniboxInput('sayzio.com');
    expect(result.kind).toBe('url');
    expect(result.navigateUrl).toBe('https://sayzio.com');
  });

  it('recognizes domain names with subdomain', () => {
    const result = parseOmniboxInput('app.sayzio.com/dashboard');
    expect(result.kind).toBe('url');
    expect(result.navigateUrl).toBe('https://app.sayzio.com/dashboard');
  });

  it('treats single words as searches', () => {
    const result = parseOmniboxInput('hello');
    expect(result.kind).toBe('search');
  });

  it('treats query strings as searches', () => {
    const result = parseOmniboxInput('how to make pizza');
    expect(result.kind).toBe('search');
    expect(result.navigateUrl).toContain('how+to+make+pizza');
  });

  it('uses the specified search engine', () => {
    const result = parseOmniboxInput('test query', SEARCH_ENGINES.duckduckgo);
    expect(result.navigateUrl).toContain('duckduckgo.com');
    expect(result.navigateUrl).toContain('test+query');
  });

  it('treats input with spaces as searches even if it contains a dot', () => {
    const result = parseOmniboxInput('some .env file');
    expect(result.kind).toBe('search');
  });

  it('handles ftp:// scheme', () => {
    const result = parseOmniboxInput('ftp://files.example.com');
    expect(result.kind).toBe('url');
  });
});

describe('formatDisplayUrl', () => {
  it('strips scheme and trailing slash', () => {
    expect(formatDisplayUrl('https://example.com/')).toBe('example.com');
  });

  it('keeps path', () => {
    expect(formatDisplayUrl('https://example.com/path/to/page')).toBe('example.com/path/to/page');
  });

  it('keeps query string', () => {
    expect(formatDisplayUrl('https://example.com/?q=test')).toBe('example.com/?q=test');
  });

  it('returns the original on invalid URL', () => {
    expect(formatDisplayUrl('not-a-url')).toBe('not-a-url');
  });
});

describe('extractSearchQuery', () => {
  it('extracts q param from Google URL', () => {
    expect(extractSearchQuery('https://www.google.com/search?q=hello+world&hl=en')).toBe('hello world');
  });

  it('extracts q param from DuckDuckGo URL', () => {
    expect(extractSearchQuery('https://duckduckgo.com/?q=pizza+recipe')).toBe('pizza recipe');
  });

  it('returns null for non-search URLs', () => {
    expect(extractSearchQuery('https://example.com/page')).toBeNull();
  });

  it('returns null for invalid URLs', () => {
    expect(extractSearchQuery('not-a-url')).toBeNull();
  });
});

describe('normalizeUrlForHistory', () => {
  it('removes fragment', () => {
    expect(normalizeUrlForHistory('https://example.com/page#section')).toBe('https://example.com/page');
  });

  it('removes trailing slash', () => {
    expect(normalizeUrlForHistory('https://example.com/')).toBe('https://example.com');
  });

  it('preserves query string', () => {
    expect(normalizeUrlForHistory('https://example.com/search?q=test')).toBe('https://example.com/search?q=test');
  });

  it('handles invalid URL gracefully', () => {
    expect(normalizeUrlForHistory('invalid')).toBe('invalid');
  });
});
