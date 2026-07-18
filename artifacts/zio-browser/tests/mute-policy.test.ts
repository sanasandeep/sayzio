/**
 * Tests for per-domain mute memory helpers (shared/mute-policy).
 * Pure logic only — no better-sqlite3 / Electron imports.
 */
import { describe, it, expect } from 'vitest';
import {
  hostForMutePolicy,
  parseMutedDomains,
  serializeMutedDomains,
  addMutedDomain,
  removeMutedDomain,
  isDomainInMuteList,
} from '../src/shared/mute-policy';

describe('hostForMutePolicy', () => {
  it('extracts lowercase host from http(s) URLs', () => {
    expect(hostForMutePolicy('https://Example.COM/watch?v=1')).toBe('example.com');
    expect(hostForMutePolicy('http://music.youtube.com')).toBe('music.youtube.com');
  });

  it('rejects non-http(s) and invalid URLs', () => {
    expect(hostForMutePolicy('about:newtab')).toBeNull();
    expect(hostForMutePolicy('file:///tmp/a.html')).toBeNull();
    expect(hostForMutePolicy('chrome://settings')).toBeNull();
    expect(hostForMutePolicy('not a url')).toBeNull();
    expect(hostForMutePolicy('')).toBeNull();
    expect(hostForMutePolicy(null)).toBeNull();
    expect(hostForMutePolicy(undefined)).toBeNull();
  });
});

describe('parseMutedDomains', () => {
  it('parses a stored JSON array, lowercasing and deduplicating', () => {
    expect(parseMutedDomains('["a.com","B.com","a.com"]')).toEqual(['a.com', 'b.com']);
  });

  it('returns [] for missing, invalid, or non-array input', () => {
    expect(parseMutedDomains(null)).toEqual([]);
    expect(parseMutedDomains(undefined)).toEqual([]);
    expect(parseMutedDomains('')).toEqual([]);
    expect(parseMutedDomains('not json')).toEqual([]);
    expect(parseMutedDomains('{"a":1}')).toEqual([]);
    expect(parseMutedDomains('[1,2,null,""]')).toEqual([]);
  });
});

describe('add/remove/isDomainInMuteList', () => {
  it('adds hosts without duplicates (case-insensitive)', () => {
    const list = addMutedDomain([], 'YouTube.com');
    expect(list).toEqual(['youtube.com']);
    expect(addMutedDomain(list, 'youtube.com')).toEqual(['youtube.com']);
  });

  it('removes hosts case-insensitively', () => {
    expect(removeMutedDomain(['a.com', 'b.com'], 'A.COM')).toEqual(['b.com']);
  });

  it('membership check is case-insensitive', () => {
    expect(isDomainInMuteList(['a.com'], 'A.com')).toBe(true);
    expect(isDomainInMuteList(['a.com'], 'c.com')).toBe(false);
  });

  it('round-trips through serialize/parse', () => {
    const list = addMutedDomain(addMutedDomain([], 'a.com'), 'b.com');
    expect(parseMutedDomains(serializeMutedDomains(list))).toEqual(['a.com', 'b.com']);
  });
});
