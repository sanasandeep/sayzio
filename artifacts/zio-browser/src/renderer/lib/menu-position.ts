/**
 * Shared positioning for fixed-position header dropdown menus
 * (AccountButton, ProfileSwitcher, TabModeSwitcher).
 *
 * Menus anchor below-right of their trigger, but on very narrow/short
 * windows the raw `top: rect.bottom + 6` / `right: innerWidth - rect.right`
 * coordinates can push a wide menu past the left edge or below the bottom
 * edge. This clamps the coordinates so the menu stays fully on screen and
 * exposes a maxHeight so tall menus scroll instead of overflowing.
 */
import { useEffect } from 'react';
import type { RefObject } from 'react';

const MARGIN = 8;
const GAP = 6;
/** Smallest useful visible menu height before we'd rather push the top up. */
const MIN_MENU_HEIGHT = 48;

export interface MenuPos {
  top: number;
  right: number;
  maxHeight: number;
}

/**
 * Compute clamped viewport coordinates for a menu anchored to `rect`.
 * - `right` is clamped so the menu's LEFT edge never crosses the viewport
 *   (right <= innerWidth - menuWidth - MARGIN) while staying >= MARGIN.
 * - `top` is clamped so at least MIN_MENU_HEIGHT of menu fits above the
 *   bottom edge; `maxHeight` bounds the rest (menus scroll internally).
 */
export function computeMenuPos(rect: DOMRect, menuWidth: number): MenuPos {
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  const anchoredRight = Math.max(MARGIN, vw - rect.right);
  // Keep the left edge on screen; if the window is narrower than the menu
  // plus margins, fall back to pinning at the left margin.
  const maxRight = Math.max(MARGIN, vw - menuWidth - MARGIN);
  const right = Math.min(anchoredRight, maxRight);

  const anchoredTop = rect.bottom + GAP;
  const maxTop = Math.max(MARGIN, vh - MARGIN - MIN_MENU_HEIGHT);
  const top = Math.max(MARGIN, Math.min(anchoredTop, maxTop));

  const maxHeight = Math.max(MIN_MENU_HEIGHT, vh - top - MARGIN);
  return { top, right, maxHeight };
}

/**
 * While `open`, re-anchor the menu to its trigger on window resize and on
 * any ancestor scroll (capture phase — scroll events don't bubble, and the
 * trigger can live in a horizontally scrollable tab strip) so it never
 * floats detached. If the trigger is gone, close the menu instead.
 */
export function useMenuReanchor(
  open: boolean,
  triggerRef: RefObject<HTMLButtonElement | null>,
  menuWidth: number,
  setMenuPos: (pos: MenuPos) => void,
  close: () => void,
): void {
  useEffect(() => {
    if (!open) return;
    function handleResize() {
      const el = triggerRef.current;
      if (!el || !el.isConnected) {
        close();
        return;
      }
      setMenuPos(computeMenuPos(el.getBoundingClientRect(), menuWidth));
    }
    window.addEventListener('resize', handleResize);
    document.addEventListener('scroll', handleResize, true);
    return () => {
      window.removeEventListener('resize', handleResize);
      document.removeEventListener('scroll', handleResize, true);
    };
  }, [open, menuWidth, triggerRef, setMenuPos, close]);
}
