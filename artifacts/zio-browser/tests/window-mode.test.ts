import { describe, expect, it } from 'vitest';

import {
  TAB_MODES,
  normalizeTabMode,
  parseTabMode,
  tabModeIncludes,
  tabModeWithout,
} from '../src/shared/window-mode';

describe('normalizeTabMode', () => {
  it('returns canonical modes unchanged', () => {
    for (const mode of TAB_MODES) {
      expect(normalizeTabMode(mode)).toBe(mode);
    }
  });

  it('maps legacy v0.1.x modes', () => {
    expect(normalizeTabMode('web')).toBe('browser');
    expect(normalizeTabMode('sayzio-split')).toBe('dashboard+browser');
    expect(normalizeTabMode('zio-split')).toBe('browser+zio');
  });

  it('maps removed sayzio-pane modes to the nearest surviving mode', () => {
    expect(normalizeTabMode('sayzio')).toBe('browser');
    expect(normalizeTabMode('sayzio+browser')).toBe('browser');
    expect(normalizeTabMode('browser+sayzio')).toBe('browser');
    expect(normalizeTabMode('dashboard+sayzio')).toBe('dashboard');
    expect(normalizeTabMode('sayzio+dashboard')).toBe('dashboard');
    expect(normalizeTabMode('sayzio+zio')).toBe('zio');
    expect(normalizeTabMode('zio+sayzio')).toBe('zio');
  });

  it('normalizes flipped split orders', () => {
    expect(normalizeTabMode('browser+dashboard')).toBe('dashboard+browser');
    expect(normalizeTabMode('zio+browser')).toBe('browser+zio');
  });

  it('rejects invalid input', () => {
    expect(normalizeTabMode(null)).toBeNull();
    expect(normalizeTabMode(undefined)).toBeNull();
    expect(normalizeTabMode('')).toBeNull();
    expect(normalizeTabMode('bogus')).toBeNull();
    expect(normalizeTabMode('dashboard+dashboard')).toBeNull();
    expect(normalizeTabMode('browser+bogus')).toBeNull();
  });
});

describe('parseTabMode / tabModeIncludes / tabModeWithout', () => {
  it('parses singles and splits', () => {
    expect(parseTabMode('browser')).toEqual({ left: 'browser', right: null });
    expect(parseTabMode('dashboard+zio')).toEqual({ left: 'dashboard', right: 'zio' });
  });

  it('tabModeIncludes checks both panes', () => {
    expect(tabModeIncludes('dashboard+zio', 'zio')).toBe(true);
    expect(tabModeIncludes('dashboard+zio', 'browser')).toBe(false);
    expect(tabModeIncludes('zio', 'zio')).toBe(true);
  });

  it('tabModeWithout drops a pane', () => {
    expect(tabModeWithout('dashboard+zio', 'zio')).toBe('dashboard');
    // 'zio' is a standalone mode now — dropping the other pane keeps it.
    expect(tabModeWithout('dashboard+zio', 'dashboard')).toBe('zio');
    expect(tabModeWithout('browser+zio', 'dashboard')).toBe('browser+zio');
  });
});
