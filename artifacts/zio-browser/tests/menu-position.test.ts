// @vitest-environment jsdom
/**
 * computeMenuPos — clamped positioning for header dropdown menus.
 * Menus must never overflow the left or bottom viewport edges, even on
 * very narrow/short windows.
 */
import { describe, it, expect } from 'vitest';
import { computeMenuPos } from '../src/renderer/lib/menu-position';

function rect(partial: Partial<DOMRect>): DOMRect {
  return { top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0, x: 0, y: 0, toJSON: () => ({}), ...partial } as DOMRect;
}

function setViewport(width: number, height: number) {
  Object.defineProperty(window, 'innerWidth', { value: width, configurable: true, writable: true });
  Object.defineProperty(window, 'innerHeight', { value: height, configurable: true, writable: true });
}

describe('computeMenuPos', () => {
  it('anchors below-right of the trigger on a roomy viewport', () => {
    setViewport(1200, 800);
    const pos = computeMenuPos(rect({ right: 1100, bottom: 40 }), 250);
    expect(pos.top).toBe(46);
    expect(pos.right).toBe(100);
    // Left edge = 1200 - 100 - 250 = 850, on screen.
    expect(window.innerWidth - pos.right - 250).toBeGreaterThanOrEqual(8);
  });

  it('clamps so a wide menu never crosses the left edge on a narrow window', () => {
    setViewport(260, 800);
    const pos = computeMenuPos(rect({ right: 250, bottom: 40 }), 250);
    const left = window.innerWidth - pos.right - 250;
    expect(left).toBeGreaterThanOrEqual(0);
    expect(pos.right).toBeGreaterThanOrEqual(8);
  });

  it('pins to the left margin when the window is narrower than the menu', () => {
    setViewport(200, 800);
    const pos = computeMenuPos(rect({ right: 190, bottom: 40 }), 250);
    expect(pos.right).toBe(8);
  });

  it('clamps top and provides a maxHeight on a short window', () => {
    setViewport(1200, 120);
    const pos = computeMenuPos(rect({ right: 1100, bottom: 110 }), 250);
    expect(pos.top).toBeLessThanOrEqual(120 - 8 - 48);
    expect(pos.top + pos.maxHeight).toBeLessThanOrEqual(120 - 8);
    expect(pos.maxHeight).toBeGreaterThanOrEqual(48);
  });
});
