/**
 * useChromeOverlay — hold the ref-counted chrome overlay while `active` is true.
 *
 * Native WebContentsViews sit ABOVE the renderer DOM, so any DOM panel/menu
 * that extends into the content area is otherwise covered and its clicks are
 * silently swallowed. The overlay detaches all native views while held and
 * re-applies the current mode (re-attaching everything) on release.
 *
 * Acquire/release is balanced exactly 1:1 via a wasActive ref, including on
 * unmount — the main process clamps the count at 0, so never over-release.
 */
import { useEffect, useRef } from 'react';

export function useChromeOverlay(active: boolean): void {
  const wasActive = useRef(false);

  useEffect(() => {
    if (active) {
      wasActive.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (wasActive.current) {
      wasActive.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [active]);

  // Release on unmount if still held.
  useEffect(() => () => {
    if (wasActive.current) {
      wasActive.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, []);
}
