import { describe, it, expect } from 'vitest';
import {
  detectSayzioLink,
  suggestAlias,
  buildShortUrl,
  quickQrImageUrl,
  PLATFORM_DOMAINS,
} from '../src/shared/link-tools';

describe('detectSayzioLink', () => {
  it('detects a short link on 1in.me', () => {
    const result = detectSayzioLink('https://1in.me/abc123');
    expect(result).not.toBeNull();
    expect(result?.alias).toBe('abc123');
    expect(result?.guessedType).toBe('short');
    expect(result?.host).toBe('1in.me');
  });

  it('detects a biolink (/@handle) on 1in.me', () => {
    const result = detectSayzioLink('https://1in.me/@john');
    expect(result).not.toBeNull();
    expect(result?.alias).toBe('john');
    expect(result?.guessedType).toBe('biolink');
  });

  it('detects a link on sayzio.com', () => {
    const result = detectSayzioLink('https://sayzio.com/mylink');
    expect(result).not.toBeNull();
    expect(result?.alias).toBe('mylink');
  });

  it('accepts a custom host via extraHosts', () => {
    const result = detectSayzioLink('https://links.mybrand.com/promo', ['links.mybrand.com']);
    expect(result).not.toBeNull();
    expect(result?.alias).toBe('promo');
  });

  it('returns null for a non-Sayzio URL', () => {
    expect(detectSayzioLink('https://example.com/page')).toBeNull();
    expect(detectSayzioLink('https://google.com/search?q=test')).toBeNull();
  });

  it('returns null for the bare domain root', () => {
    expect(detectSayzioLink('https://1in.me')).toBeNull();
    expect(detectSayzioLink('https://1in.me/')).toBeNull();
  });

  it('returns null for reserved paths', () => {
    expect(detectSayzioLink('https://1in.me/login')).toBeNull();
    expect(detectSayzioLink('https://1in.me/user/settings')).toBeNull();
    expect(detectSayzioLink('https://1in.me/admin/dashboard')).toBeNull();
    expect(detectSayzioLink('https://1in.me/pricing')).toBeNull();
  });

  it('returns null for non-http(s) URLs', () => {
    expect(detectSayzioLink('file:///home/user/test')).toBeNull();
    expect(detectSayzioLink('ftp://1in.me/file')).toBeNull();
  });

  it('returns null for invalid URLs', () => {
    expect(detectSayzioLink('not-a-url')).toBeNull();
    expect(detectSayzioLink('')).toBeNull();
  });

  it('is case-insensitive for the host', () => {
    const result = detectSayzioLink('https://1IN.ME/hello');
    expect(result).not.toBeNull();
    expect(result?.alias).toBe('hello');
  });

  it('only uses the first path segment as alias', () => {
    const result = detectSayzioLink('https://1in.me/myalias/extra-path');
    expect(result).not.toBeNull();
    expect(result?.alias).toBe('myalias');
  });

  it('returns null for very short single-char paths that look invalid', () => {
    const result = detectSayzioLink('https://1in.me/a');
    expect(result).toBeNull();
  });

  it('PLATFORM_DOMAINS contains expected entries', () => {
    const domains = PLATFORM_DOMAINS as readonly string[];
    expect(domains).toContain('1in.me');
    expect(domains).toContain('sayzio.com');
  });
});

describe('suggestAlias', () => {
  it('lowercases and strips special chars', () => {
    expect(suggestAlias('Hello World!')).toBe('hello-world');
  });

  it('collapses multiple spaces/hyphens', () => {
    expect(suggestAlias('Top  10  Tips')).toBe('top-10-tips');
  });

  it('truncates to 30 chars', () => {
    const long = 'a'.repeat(50);
    expect(suggestAlias(long).length).toBeLessThanOrEqual(30);
  });

  it('strips leading/trailing hyphens', () => {
    expect(suggestAlias(' -hello-world- ')).toBe('hello-world');
  });

  it('returns empty string for non-alphanumeric input', () => {
    expect(suggestAlias('!!! ???')).toBe('');
  });

  it('handles empty string', () => {
    expect(suggestAlias('')).toBe('');
  });
});

describe('buildShortUrl', () => {
  it('builds a canonical https short URL', () => {
    expect(buildShortUrl('abc123', '1in.me')).toBe('https://1in.me/abc123');
  });

  it('works with custom domains', () => {
    expect(buildShortUrl('promo', 'links.brand.com')).toBe('https://links.brand.com/promo');
  });
});

describe('quickQrImageUrl', () => {
  it('returns a locally generated SVG data URL (no external service)', () => {
    const url = quickQrImageUrl('https://example.com/page?q=1&a=2');
    expect(url.startsWith('data:image/svg+xml;charset=utf-8,')).toBe(true);
    expect(url).not.toContain('api.qrserver.com');
    const svg = decodeURIComponent(url.slice('data:image/svg+xml;charset=utf-8,'.length));
    expect(svg).toContain('<svg xmlns="http://www.w3.org/2000/svg"');
    expect(svg).toContain('<path d="M');
  });

  it('includes the requested size', () => {
    const url = quickQrImageUrl('https://example.com', 300);
    const svg = decodeURIComponent(url.slice('data:image/svg+xml;charset=utf-8,'.length));
    expect(svg).toContain('width="300" height="300"');
  });

  it('defaults to 200x200', () => {
    const url = quickQrImageUrl('https://example.com');
    const svg = decodeURIComponent(url.slice('data:image/svg+xml;charset=utf-8,'.length));
    expect(svg).toContain('width="200" height="200"');
  });

  it('produces different QR content for different data', () => {
    expect(quickQrImageUrl('https://example.com/a')).not.toBe(quickQrImageUrl('https://example.com/b'));
  });
});
