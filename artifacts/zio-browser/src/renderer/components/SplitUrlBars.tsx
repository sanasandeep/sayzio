/**
 * Dual address bars for the Website + Website split.
 *
 * The main process reserves a strip (SPLIT_URL_BAR_HEIGHT) above both native
 * panes; this component fills it with one URL input per pane so each side can
 * be navigated independently. The inputs mirror primaryUrl / secondUrl from
 * tab state and commit on Enter via tabs.navigatePane.
 */
import React, { useEffect, useRef, useState } from 'react';
import { SPLIT_URL_BAR_HEIGHT, TAB_SPLIT_DIVIDER_WIDTH } from '../../shared/window-mode';

interface Props {
  tabId: string;
  primaryUrl: string;
  secondUrl: string;
  splitRatio: number;
}

function PaneUrlInput({ tabId, pane, url }: { tabId: string; pane: 'primary' | 'second'; url: string }) {
  const [value, setValue] = useState(url);
  const [focused, setFocused] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  // Follow navigation while the user isn't editing.
  useEffect(() => {
    if (!focused) setValue(url);
  }, [url, focused]);

  const commit = () => {
    const input = value.trim();
    if (!input) return;
    void window.zio.tabs.navigatePane(tabId, pane, input);
    inputRef.current?.blur();
  };

  return (
    <input
      ref={inputRef}
      value={value}
      onChange={(e) => setValue(e.target.value)}
      onFocus={(e) => { setFocused(true); e.target.select(); }}
      onBlur={() => { setFocused(false); setValue(url); }}
      onKeyDown={(e) => {
        if (e.key === 'Enter') { e.preventDefault(); commit(); }
        if (e.key === 'Escape') { setValue(url); inputRef.current?.blur(); }
      }}
      placeholder="Enter address or search"
      spellCheck={false}
      style={{
        flex: 1,
        minWidth: 0,
        height: 26,
        padding: '0 10px',
        borderRadius: 13,
        border: `1px solid ${focused ? 'var(--color-primary, #4f7cff)' : 'var(--color-border)'}`,
        background: 'var(--color-bg-base)',
        color: 'var(--color-text)',
        fontSize: 12,
        outline: 'none',
      }}
      title={url}
    />
  );
}

export function SplitUrlBars({ tabId, primaryUrl, secondUrl, splitRatio }: Props) {
  const half = Math.ceil(TAB_SPLIT_DIVIDER_WIDTH / 2);
  return (
    <div
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: SPLIT_URL_BAR_HEIGHT,
        display: 'flex',
        alignItems: 'center',
        background: 'var(--color-bg-surface)',
        borderBottom: '1px solid var(--color-border)',
        zIndex: 5,
      }}
    >
      <div style={{ width: `calc(${splitRatio * 100}% - ${half}px)`, display: 'flex', padding: '0 8px' }}>
        <PaneUrlInput tabId={tabId} pane="primary" url={primaryUrl} />
      </div>
      <div style={{ width: TAB_SPLIT_DIVIDER_WIDTH, flexShrink: 0 }} />
      <div style={{ flex: 1, display: 'flex', padding: '0 8px', minWidth: 0 }}>
        <PaneUrlInput tabId={tabId} pane="second" url={secondUrl} />
      </div>
    </div>
  );
}
