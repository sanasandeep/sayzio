import { describe, it, expect } from 'vitest';
import {
  parsePinnedTools,
  serializePinnedTools,
  togglePinnedTool,
  reorderPinnedTools,
  MAX_PINNED_TOOLS,
  PINNABLE_TOOLS,
  isPinnableTool,
} from '../src/shared/toolbar-pins';

describe('parsePinnedTools', () => {
  it('returns empty for null/undefined/empty', () => {
    expect(parsePinnedTools(null)).toEqual([]);
    expect(parsePinnedTools(undefined)).toEqual([]);
    expect(parsePinnedTools('')).toEqual([]);
  });

  it('returns empty for malformed JSON and non-array values', () => {
    expect(parsePinnedTools('not json')).toEqual([]);
    expect(parsePinnedTools('{"a":1}')).toEqual([]);
    expect(parsePinnedTools('"dialer"')).toEqual([]);
  });

  it('parses valid tool ids', () => {
    expect(parsePinnedTools('["dialer","screenshot"]')).toEqual(['dialer', 'screenshot']);
  });

  it('drops unknown ids and duplicates', () => {
    expect(parsePinnedTools('["dialer","bogus","dialer","device_lab"]'))
      .toEqual(['dialer', 'device_lab']);
  });

  it('caps the list at MAX_PINNED_TOOLS', () => {
    const raw = JSON.stringify([...PINNABLE_TOOLS]);
    expect(parsePinnedTools(raw)).toHaveLength(MAX_PINNED_TOOLS);
  });
});

describe('serializePinnedTools', () => {
  it('round-trips through parse', () => {
    const tools = ['reading_list', 'screenshot'] as const;
    expect(parsePinnedTools(serializePinnedTools([...tools]))).toEqual([...tools]);
  });

  it('caps at MAX_PINNED_TOOLS on serialize', () => {
    const serialized = serializePinnedTools([...PINNABLE_TOOLS]);
    expect(JSON.parse(serialized)).toHaveLength(MAX_PINNED_TOOLS);
  });
});

describe('togglePinnedTool', () => {
  it('pins an unpinned tool', () => {
    expect(togglePinnedTool([], 'dialer')).toEqual(['dialer']);
  });

  it('unpins a pinned tool', () => {
    expect(togglePinnedTool(['dialer', 'screenshot'], 'dialer')).toEqual(['screenshot']);
  });

  it('refuses to pin beyond the cap (returns the same list)', () => {
    const current = ['dialer', 'screenshot'] as const;
    const result = togglePinnedTool([...current], 'device_lab');
    expect(result).toEqual([...current]);
  });

  it('still allows unpinning when at the cap', () => {
    expect(togglePinnedTool(['dialer', 'screenshot'], 'screenshot')).toEqual(['dialer']);
  });
});

describe('reorderPinnedTools', () => {
  it('moves a tool to a new position', () => {
    expect(reorderPinnedTools(['dialer', 'screenshot'], 'screenshot', 0))
      .toEqual(['screenshot', 'dialer']);
    expect(reorderPinnedTools(['dialer', 'screenshot'], 'dialer', 1))
      .toEqual(['screenshot', 'dialer']);
  });

  it('returns the same reference when the tool is not pinned', () => {
    const current: Parameters<typeof reorderPinnedTools>[0] = ['dialer'];
    expect(reorderPinnedTools(current, 'screenshot', 0)).toBe(current);
  });

  it('returns the same reference when already at the target index', () => {
    const current: Parameters<typeof reorderPinnedTools>[0] = ['dialer', 'screenshot'];
    expect(reorderPinnedTools(current, 'dialer', 0)).toBe(current);
  });

  it('clamps out-of-range target indices', () => {
    expect(reorderPinnedTools(['dialer', 'screenshot'], 'dialer', 99))
      .toEqual(['screenshot', 'dialer']);
    expect(reorderPinnedTools(['dialer', 'screenshot'], 'screenshot', -5))
      .toEqual(['screenshot', 'dialer']);
  });

  it('round-trips through serialize/parse (order persists)', () => {
    const reordered = reorderPinnedTools(['dialer', 'screenshot'], 'screenshot', 0);
    expect(parsePinnedTools(serializePinnedTools(reordered)))
      .toEqual(['screenshot', 'dialer']);
  });
});

describe('isPinnableTool', () => {
  it('accepts known ids and rejects others', () => {
    expect(isPinnableTool('dialer')).toBe(true);
    expect(isPinnableTool('bogus')).toBe(false);
    expect(isPinnableTool(42)).toBe(false);
  });
});
