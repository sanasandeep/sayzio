/**
 * Ad-block policy resolver tests (Task #6453): tier precedence, domain
 * normalization/subdomain matching, admin-policy sanitization and the
 * request-level host override. Pure module — no Electron mocks needed.
 */
import { describe, it, expect } from 'vitest';

import {
  resolveAdBlockState,
  requestHostOverride,
  normalizeDomain,
  parseDomainList,
  hostMatchesList,
  sanitizeAdminPolicy,
  sanitizeStrength,
  EMPTY_ADMIN_POLICY,
  type ResolveInput,
} from '../src/shared/adblock-policy';

function baseInput(overrides: Partial<ResolveInput> = {}): ResolveInput {
  return {
    host: 'example.com',
    adminPolicy: EMPTY_ADMIN_POLICY,
    timedPauseActive: false,
    pagePaused: false,
    siteOverride: null,
    userAllow: [],
    userBlock: [],
    globalEnabled: true,
    ...overrides,
  };
}

describe('resolveAdBlockState tier precedence', () => {
  it('falls through to the global toggle', () => {
    expect(resolveAdBlockState(baseInput())).toEqual({ active: true, reason: 'global', adminLocked: false });
    expect(resolveAdBlockState(baseInput({ globalEnabled: false }))).toEqual({ active: false, reason: 'global', adminLocked: false });
  });

  it('admin block beats everything, including pauses and user allow', () => {
    const state = resolveAdBlockState(baseInput({
      adminPolicy: { version: 3, allow: [], block: ['example.com'] },
      timedPauseActive: true,
      pagePaused: true,
      siteOverride: false,
      userAllow: ['example.com'],
      globalEnabled: false,
    }));
    expect(state).toEqual({ active: true, reason: 'admin-block', adminLocked: true });
  });

  it('admin allow beats user block and global on', () => {
    const state = resolveAdBlockState(baseInput({
      adminPolicy: { version: 1, allow: ['example.com'], block: [] },
      userBlock: ['example.com'],
    }));
    expect(state).toEqual({ active: false, reason: 'admin-allow', adminLocked: true });
  });

  it('admin block beats admin allow when both match', () => {
    const state = resolveAdBlockState(baseInput({
      adminPolicy: { version: 1, allow: ['example.com'], block: ['example.com'] },
    }));
    expect(state.reason).toBe('admin-block');
  });

  it('admin policy matches subdomains', () => {
    const state = resolveAdBlockState(baseInput({
      host: 'shop.ads.example.com',
      adminPolicy: { version: 1, allow: [], block: ['example.com'] },
    }));
    expect(state).toEqual({ active: true, reason: 'admin-block', adminLocked: true });
  });

  it('timed pause beats page pause, site setting and user lists', () => {
    const state = resolveAdBlockState(baseInput({
      timedPauseActive: true,
      pagePaused: true,
      siteOverride: true,
      userBlock: ['example.com'],
    }));
    expect(state).toEqual({ active: false, reason: 'timed-pause', adminLocked: false });
  });

  it('page pause beats site setting and user lists', () => {
    const state = resolveAdBlockState(baseInput({
      pagePaused: true,
      siteOverride: true,
      userBlock: ['example.com'],
    }));
    expect(state).toEqual({ active: false, reason: 'page-pause', adminLocked: false });
  });

  it('site override beats user lists and global', () => {
    expect(resolveAdBlockState(baseInput({ siteOverride: false, userBlock: ['example.com'] })))
      .toEqual({ active: false, reason: 'site-setting', adminLocked: false });
    expect(resolveAdBlockState(baseInput({ siteOverride: true, globalEnabled: false })))
      .toEqual({ active: true, reason: 'site-setting', adminLocked: false });
  });

  it('user block beats user allow and applies with global off', () => {
    const state = resolveAdBlockState(baseInput({
      userAllow: ['example.com'],
      userBlock: ['example.com'],
      globalEnabled: false,
    }));
    expect(state).toEqual({ active: true, reason: 'user-block', adminLocked: false });
  });

  it('user allow disables blocking even when global is on (subdomain match)', () => {
    const state = resolveAdBlockState(baseInput({
      host: 'news.example.com',
      userAllow: ['example.com'],
    }));
    expect(state).toEqual({ active: false, reason: 'user-allow', adminLocked: false });
  });

  it('null host skips all host-based tiers and lands on global', () => {
    const state = resolveAdBlockState(baseInput({
      host: null,
      adminPolicy: { version: 1, allow: [], block: ['example.com'] },
      userBlock: ['example.com'],
    }));
    expect(state.reason).toBe('global');
  });
});

describe('requestHostOverride', () => {
  const admin = { version: 1, allow: ['cdn.good.com'], block: ['ads.bad.com'] };

  it('admin lists win over user lists', () => {
    expect(requestHostOverride('ads.bad.com', admin, ['ads.bad.com'], [])).toBe('block');
    expect(requestHostOverride('cdn.good.com', admin, [], ['cdn.good.com'])).toBe('allow');
  });

  it('user lists apply when no admin match; null otherwise', () => {
    expect(requestHostOverride('tracker.net', EMPTY_ADMIN_POLICY, [], ['tracker.net'])).toBe('block');
    expect(requestHostOverride('ok.net', EMPTY_ADMIN_POLICY, ['ok.net'], [])).toBe('allow');
    expect(requestHostOverride('neutral.net', EMPTY_ADMIN_POLICY, [], [])).toBeNull();
  });

  it('matches subdomains', () => {
    expect(requestHostOverride('x.ads.bad.com', admin, [], [])).toBe('block');
  });
});

describe('normalizeDomain / parseDomainList / hostMatchesList', () => {
  it('normalizes URLs, ports, www and case', () => {
    expect(normalizeDomain('HTTPS://WWW.Example.COM:8080/path?q=1#f')).toBe('example.com');
    expect(normalizeDomain('  sub.example.co.uk. ')).toBeNull(); // trailing dot fails hostname regex
    expect(normalizeDomain('sub.example.co.uk')).toBe('sub.example.co.uk');
  });

  it('rejects invalid hosts', () => {
    for (const bad of ['', 'localhost', 'no spaces.com x', 'exa mple.com', '-bad.com', 123, null]) {
      expect(normalizeDomain(bad as never)).toBeNull();
    }
  });

  it('parseDomainList dedupes, normalizes and tolerates bad JSON', () => {
    expect(parseDomainList(JSON.stringify(['Example.com', 'www.example.com', 'bad host', 'ok.net'])))
      .toEqual(['example.com', 'ok.net']);
    expect(parseDomainList('not json')).toEqual([]);
    expect(parseDomainList(null)).toEqual([]);
  });

  it('hostMatchesList handles www and subdomains without over-matching', () => {
    expect(hostMatchesList('www.example.com', ['example.com'])).toBe(true);
    expect(hostMatchesList('a.b.example.com', ['example.com'])).toBe(true);
    expect(hostMatchesList('notexample.com', ['example.com'])).toBe(false);
    expect(hostMatchesList(null, ['example.com'])).toBe(false);
  });
});

describe('sanitizeAdminPolicy / sanitizeStrength', () => {
  it('cleans malformed payloads', () => {
    expect(sanitizeAdminPolicy(null)).toEqual(EMPTY_ADMIN_POLICY);
    expect(sanitizeAdminPolicy({ version: -2, allow: 'x', block: ['Ads.Bad.com', 42, 'dup.com', 'dup.com'] }))
      .toEqual({ version: 0, allow: [], block: ['ads.bad.com', 'dup.com'] });
    expect(sanitizeAdminPolicy({ version: 7.9, allow: ['ok.com'], block: [] }).version).toBe(7);
  });

  it('strength defaults to balanced', () => {
    expect(sanitizeStrength('strict')).toBe('strict');
    expect(sanitizeStrength('balanced')).toBe('balanced');
    expect(sanitizeStrength('off')).toBe('balanced');
    expect(sanitizeStrength(undefined)).toBe('balanced');
  });
});
