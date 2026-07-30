/**
 * Tests for the omnibox "jump to Sayzio" suggestion logic —
 * handle-eligibility pattern, the privacy gate, and checkSayzioExists
 * semantics (taken vs invalid/reserved, silent failure, cache hits).
 */
import { describe, it, expect, vi } from 'vitest';
import {
  SAYZIO_HANDLE_PATTERN,
  isSayzioSuggestEligible,
  checkSayzioExists,
  type SayzioExistsResult,
  type SayzioLookupClient,
} from '../src/shared/sayzio-suggest';

function makeClient(overrides: Partial<SayzioLookupClient> = {}): {
  client: SayzioLookupClient;
  checkAlias: ReturnType<typeof vi.fn>;
  creatorProfileMini: ReturnType<typeof vi.fn>;
} {
  const checkAlias = vi.fn(async () => ({ status: 'available' }));
  const creatorProfileMini = vi.fn(async () => ({ profile_published: false }));
  const client: SayzioLookupClient = {
    checkAlias: (overrides.checkAlias ?? checkAlias) as SayzioLookupClient['checkAlias'],
    creatorProfileMini: (overrides.creatorProfileMini ?? creatorProfileMini) as SayzioLookupClient['creatorProfileMini'],
  };
  return { client, checkAlias, creatorProfileMini };
}

describe('SAYZIO_HANDLE_PATTERN', () => {
  it('accepts simple handles', () => {
    for (const q of ['ab', 'alex', 'alex-doe', 'alex_doe', 'a1', '9lives', 'A_Mixed-Case1']) {
      expect(SAYZIO_HANDLE_PATTERN.test(q), q).toBe(true);
    }
  });

  it('accepts the max length (63) and rejects longer', () => {
    expect(SAYZIO_HANDLE_PATTERN.test('a'.repeat(63))).toBe(true);
    expect(SAYZIO_HANDLE_PATTERN.test('a'.repeat(64))).toBe(false);
  });

  it('rejects too-short queries', () => {
    expect(SAYZIO_HANDLE_PATTERN.test('')).toBe(false);
    expect(SAYZIO_HANDLE_PATTERN.test('a')).toBe(false);
  });

  it('rejects queries with spaces (multi-word searches)', () => {
    expect(SAYZIO_HANDLE_PATTERN.test('two words')).toBe(false);
    expect(SAYZIO_HANDLE_PATTERN.test(' alex')).toBe(false);
    expect(SAYZIO_HANDLE_PATTERN.test('alex ')).toBe(false);
  });

  it('rejects queries with dots (domains/URLs)', () => {
    expect(SAYZIO_HANDLE_PATTERN.test('example.com')).toBe(false);
    expect(SAYZIO_HANDLE_PATTERN.test('a.b')).toBe(false);
  });

  it('rejects full URLs and other punctuation', () => {
    for (const q of [
      'https://example.com',
      'sayzio.app/alex',
      'alex/doe',
      'alex?x=1',
      '@alex',
      'alex!',
      'alex:80',
      'a\u00e9b', // non-ASCII
    ]) {
      expect(SAYZIO_HANDLE_PATTERN.test(q), q).toBe(false);
    }
  });

  it('rejects handles starting with dash or underscore', () => {
    expect(SAYZIO_HANDLE_PATTERN.test('-alex')).toBe(false);
    expect(SAYZIO_HANDLE_PATTERN.test('_alex')).toBe(false);
  });
});

describe('isSayzioSuggestEligible (privacy gate)', () => {
  it('never eligible in private windows, even signed in with a valid handle', () => {
    expect(isSayzioSuggestEligible('alex', { isPrivate: true, token: 'tok' })).toBe(false);
  });

  it('never eligible when signed out', () => {
    expect(isSayzioSuggestEligible('alex', { isPrivate: false, token: null })).toBe(false);
    expect(isSayzioSuggestEligible('alex', { isPrivate: false, token: undefined })).toBe(false);
    expect(isSayzioSuggestEligible('alex', { isPrivate: false, token: '' })).toBe(false);
  });

  it('not eligible for non-handle queries even when signed in', () => {
    expect(isSayzioSuggestEligible('two words', { isPrivate: false, token: 'tok' })).toBe(false);
    expect(isSayzioSuggestEligible('example.com', { isPrivate: false, token: 'tok' })).toBe(false);
  });

  it('eligible only when public window + signed in + handle-like', () => {
    expect(isSayzioSuggestEligible('alex', { isPrivate: false, token: 'tok' })).toBe(true);
  });
});

describe('checkSayzioExists', () => {
  it('taken alias => link true', async () => {
    const { client } = makeClient({ checkAlias: async () => ({ status: 'taken' }) });
    const res = await checkSayzioExists('alex', client, new Map());
    expect(res.link).toBe(true);
    expect(res.profile).toBe(false);
  });

  it.each(['invalid', 'reserved', 'banned', 'available'])(
    'alias status %s => link false',
    async (status) => {
      const { client } = makeClient({ checkAlias: async () => ({ status }) });
      const res = await checkSayzioExists('alex', client, new Map());
      expect(res.link).toBe(false);
    },
  );

  it('published creator profile => profile true', async () => {
    const { client } = makeClient({
      creatorProfileMini: async () => ({ profile_published: true }),
    });
    const res = await checkSayzioExists('alex', client, new Map());
    expect(res.profile).toBe(true);
    expect(res.link).toBe(false);
  });

  it('unpublished profile => profile false', async () => {
    const { client } = makeClient({
      creatorProfileMini: async () => ({ profile_published: false }),
    });
    const res = await checkSayzioExists('alex', client, new Map());
    expect(res.profile).toBe(false);
  });

  it('API errors fail silently to false (never throw)', async () => {
    const { client } = makeClient({
      checkAlias: async () => { throw new Error('network down'); },
      creatorProfileMini: async () => { throw new Error('HTTP 404'); },
    });
    const res = await checkSayzioExists('alex', client, new Map());
    expect(res).toEqual({ link: false, profile: false });
  });

  it('one side failing does not break the other', async () => {
    const { client } = makeClient({
      checkAlias: async () => { throw new Error('boom'); },
      creatorProfileMini: async () => ({ profile_published: true }),
    });
    const res = await checkSayzioExists('alex', client, new Map());
    expect(res).toEqual({ link: false, profile: true });
  });

  it('cache hit skips the network entirely', async () => {
    const cache = new Map<string, SayzioExistsResult>();
    cache.set('alex', { link: true, profile: true });
    const { client, checkAlias, creatorProfileMini } = makeClient();
    const res = await checkSayzioExists('alex', client, cache);
    expect(res).toEqual({ link: true, profile: true });
    expect(checkAlias).not.toHaveBeenCalled();
    expect(creatorProfileMini).not.toHaveBeenCalled();
  });

  it('cache key is lowercased (Alex hits the alex entry)', async () => {
    const cache = new Map<string, SayzioExistsResult>();
    cache.set('alex', { link: true, profile: false });
    const { client, checkAlias } = makeClient();
    const res = await checkSayzioExists('Alex', client, cache);
    expect(res).toEqual({ link: true, profile: false });
    expect(checkAlias).not.toHaveBeenCalled();
  });

  it('populates the cache after a lookup (second call skips network)', async () => {
    const cache = new Map<string, SayzioExistsResult>();
    const checkAlias = vi.fn(async () => ({ status: 'taken' }));
    const { client } = makeClient({ checkAlias });
    await checkSayzioExists('alex', client, cache);
    await checkSayzioExists('alex', client, cache);
    expect(checkAlias).toHaveBeenCalledTimes(1);
    expect(cache.get('alex')).toEqual({ link: true, profile: false });
  });

  it('failed lookups are cached too (no retry storm while typing)', async () => {
    const cache = new Map<string, SayzioExistsResult>();
    const checkAlias = vi.fn(async () => { throw new Error('down'); });
    const creatorProfileMini = vi.fn(async () => { throw new Error('down'); });
    const { client } = makeClient({ checkAlias, creatorProfileMini });
    await checkSayzioExists('alex', client, cache);
    await checkSayzioExists('alex', client, cache);
    expect(checkAlias).toHaveBeenCalledTimes(1);
    expect(creatorProfileMini).toHaveBeenCalledTimes(1);
  });
});
