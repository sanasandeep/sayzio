import { describe, it, expect } from 'vitest';
import { detectClipboardContent, clipboardKindLabel } from '../src/shared/clipboard-content';

describe('detectClipboardContent', () => {
  it('detects empty clipboard', () => {
    expect(detectClipboardContent('').kind).toBe('empty');
    expect(detectClipboardContent('   ').kind).toBe('empty');
  });

  it('detects explicit http(s) URLs', () => {
    const r = detectClipboardContent('https://example.com/path?q=1');
    expect(r.kind).toBe('url');
    expect(r.url).toBe('https://example.com/path?q=1');

    expect(detectClipboardContent('http://localhost:5000/foo').kind).toBe('url');
  });

  it('detects bare domains as URLs with https prefix', () => {
    const r = detectClipboardContent('example.com/some/path');
    expect(r.kind).toBe('url');
    expect(r.url).toBe('https://example.com/some/path');

    expect(detectClipboardContent('sayz.io').kind).toBe('url');
  });

  it('detects email addresses (not as domains)', () => {
    expect(detectClipboardContent('someone@example.com').kind).toBe('email');
  });

  it('detects phone numbers', () => {
    expect(detectClipboardContent('+1 (555) 123-4567').kind).toBe('phone');
    expect(detectClipboardContent('9876543210').kind).toBe('phone');
  });

  it('classifies everything else as text', () => {
    expect(detectClipboardContent('hello world').kind).toBe('text');
    expect(detectClipboardContent('line one\nline two example.com').kind).toBe('text');
    expect(detectClipboardContent('12ab').kind).toBe('text');
  });

  it('treats multi-line content as text even if a line is a URL', () => {
    expect(detectClipboardContent('https://example.com\nmore').kind).toBe('text');
  });

  it('handles very large payloads as text without hanging', () => {
    const big = 'a'.repeat(10_000);
    expect(detectClipboardContent(big).kind).toBe('text');
  });

  it('labels every kind', () => {
    for (const k of ['url', 'email', 'phone', 'text', 'empty'] as const) {
      expect(clipboardKindLabel(k)).toBeTruthy();
    }
  });
});
