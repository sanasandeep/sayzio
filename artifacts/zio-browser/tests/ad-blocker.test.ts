/**
 * Ad blocker unit tests: filter-engine matching, mainFrame safety, per-site
 * override precedence and cosmetic CSS retrieval.
 *
 * Uses __setEngineFromTextForTests so no bundled list parsing (or Electron
 * runtime) is needed.
 */
import { describe, it, expect, vi, beforeAll } from 'vitest';

// The module imports electron + ./db at load time; stub both.
vi.mock('electron', () => ({
  app: {
    isPackaged: false,
    getAppPath: () => '/tmp',
    getPath: () => '/tmp',
  },
}));
vi.mock('../src/main/db', () => ({
  getPreference: () => null,
  setPreference: () => undefined,
  isDbInitialized: () => false,
}));

import {
  __setEngineFromTextForTests,
  matchAdRequest,
  isAdBlockingEffectiveForWc,
  setAdBlockPolicyResolver,
  setAdBlockingEnabled,
  isAdBlockEngineReady,
  getCosmeticStylesForUrl,
} from '../src/main/ad-blocker';

const FILTERS = [
  '||ads.example.com^',
  '/banner-ad.',
  'example.org##.ad-banner',
].join('\n');

beforeAll(() => {
  __setEngineFromTextForTests(FILTERS);
});

describe('matchAdRequest', () => {
  it('blocks a request to a filtered ad host', () => {
    expect(matchAdRequest({
      url: 'https://ads.example.com/pixel.js',
      resourceType: 'script',
      referrer: 'https://example.org/',
    })).toBe(true);
  });

  it('blocks a URL-pattern match', () => {
    expect(matchAdRequest({
      url: 'https://cdn.site.com/banner-ad.png',
      resourceType: 'image',
      referrer: 'https://example.org/',
    })).toBe(true);
  });

  it('does not block benign requests', () => {
    expect(matchAdRequest({
      url: 'https://example.org/app.js',
      resourceType: 'script',
      referrer: 'https://example.org/',
    })).toBe(false);
  });

  it('never blocks top-level navigations, even to a filtered host', () => {
    expect(matchAdRequest({
      url: 'https://ads.example.com/landing',
      resourceType: 'mainFrame',
      referrer: '',
    })).toBe(false);
  });

  it('reports the engine as ready after test injection', () => {
    expect(isAdBlockEngineReady()).toBe(true);
  });
});

describe('isAdBlockingEffectiveForWc (policy resolver delegation)', () => {
  it('the registered policy resolver decides in both directions', () => {
    setAdBlockingEnabled(false);
    setAdBlockPolicyResolver(() => true);
    expect(isAdBlockingEffectiveForWc(1)).toBe(true);

    setAdBlockingEnabled(true);
    setAdBlockPolicyResolver(() => false);
    expect(isAdBlockingEffectiveForWc(1)).toBe(false);
  });

  it('the resolver also decides when no webContents id is available', () => {
    setAdBlockPolicyResolver((wcId) => wcId === undefined);
    setAdBlockingEnabled(false);
    expect(isAdBlockingEffectiveForWc(undefined)).toBe(true);
    setAdBlockingEnabled(true);
    expect(isAdBlockingEffectiveForWc(1)).toBe(false);
  });

  it('a throwing resolver falls back to the global flag', () => {
    setAdBlockPolicyResolver(() => { throw new Error('boom'); });
    setAdBlockingEnabled(true);
    expect(isAdBlockingEffectiveForWc(1)).toBe(true);
    setAdBlockingEnabled(false);
    expect(isAdBlockingEffectiveForWc(1)).toBe(false);
  });
});

describe('getCosmeticStylesForUrl', () => {
  it('returns element-hiding CSS for a host with cosmetic filters', () => {
    const styles = getCosmeticStylesForUrl('https://example.org/page');
    expect(styles).toBeTruthy();
    expect(styles).toContain('.ad-banner');
  });

  it('returns null for non-http(s) URLs', () => {
    expect(getCosmeticStylesForUrl('zio://settings')).toBeNull();
    expect(getCosmeticStylesForUrl('about:blank')).toBeNull();
  });
});
