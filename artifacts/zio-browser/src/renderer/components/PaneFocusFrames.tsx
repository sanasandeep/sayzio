/**
 * Focus frames for the Website + Website tab split (one per pane).
 *
 * The main process insets both native panes by TAB_SPLIT_FOCUS_FRAME, so
 * these renderer-drawn frames stay visible and clickable around each pane.
 * The focused pane gets the accent frame plus an "Address bar · Left/Right
 * pane" tag; clicking a frame (or anywhere inside a pane's page) moves pane
 * focus — keeping the omnibox target unmistakable when both panes show
 * similar-looking sites.
 */
import React from 'react';
import {
  TAB_SPLIT_DIVIDER_WIDTH,
  TAB_SPLIT_FOCUS_FRAME,
} from '../../shared/window-mode';

export function PaneFocusFrames({
  tabId,
  focusedPane,
  splitRatio,
}: {
  tabId: string;
  focusedPane: 'primary' | 'second';
  splitRatio: number;
}) {
  return (
    <>
      {(['primary', 'second'] as const).map((pane) => {
        const focused = focusedPane === pane;
        const dividerHalf = Math.ceil(TAB_SPLIT_DIVIDER_WIDTH / 2);
        return (
          <div
            key={pane}
            onMouseDown={() => { void window.zio.tabs.focusPane(tabId, pane); }}
            title={focused ? 'Address bar controls this pane' : 'Click to control this pane from the address bar'}
            style={{
              position: 'absolute',
              top: 0,
              bottom: 0,
              left: pane === 'primary' ? 0 : `calc(${splitRatio * 100}% + ${dividerHalf}px)`,
              right: pane === 'primary' ? undefined : 0,
              width: pane === 'primary' ? `calc(${splitRatio * 100}% - ${dividerHalf}px)` : undefined,
              border: focused
                ? `${TAB_SPLIT_FOCUS_FRAME}px solid var(--color-primary, #6366f1)`
                : `${TAB_SPLIT_FOCUS_FRAME}px solid var(--color-border)`,
              boxSizing: 'border-box',
              zIndex: 9,
            }}
          >
            {/* Focused-pane tag — makes the address-bar target unmistakable
                when both panes show similar-looking sites. */}
            {focused && (
              <span style={{
                position: 'absolute',
                top: 0,
                left: '50%',
                transform: 'translateX(-50%)',
                background: 'var(--color-primary, #6366f1)',
                color: '#fff',
                fontSize: 10,
                fontWeight: 700,
                letterSpacing: 0.3,
                textTransform: 'uppercase',
                lineHeight: 1,
                padding: '3px 8px 4px',
                borderRadius: '0 0 8px 8px',
                whiteSpace: 'nowrap',
                pointerEvents: 'none',
              }}>
                Address bar · {pane === 'primary' ? 'Left' : 'Right'} pane
              </span>
            )}
          </div>
        );
      })}
    </>
  );
}
