/**
 * TabSearchPopover — fuzzy filter over open tabs.
 * Opens via Ctrl/Cmd+Shift+A or the tab strip search button.
 * Keyboard: ArrowUp/Down to navigate, Enter to activate, Escape to close.
 */
import { useState, useEffect, useRef, useCallback } from 'react';
import { useTabStore } from '../store/tab-store';

interface Props {
  onClose: () => void;
}

function matchesQuery(text: string, query: string): boolean {
  if (!query) return true;
  const q = query.toLowerCase();
  const t = text.toLowerCase();
  // Substring match (good enough for tab search)
  return t.includes(q);
}

export function TabSearchPopover({ onClose }: Props) {
  const { tabs, tabOrder, activeTabId, activateTab } = useTabStore();
  const [query, setQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);

  const filtered = tabOrder.filter(id => {
    const tab = tabs[id];
    if (!tab) return false;
    const text = `${tab.title ?? ''} ${tab.url ?? ''}`;
    return matchesQuery(text, query);
  });

  // Reset selection when query changes
  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  // Auto-focus the input
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  // Scroll selected item into view
  useEffect(() => {
    const list = listRef.current;
    if (!list) return;
    const items = list.querySelectorAll('[data-tab-item]');
    const item = items[selectedIndex] as HTMLElement | undefined;
    item?.scrollIntoView({ block: 'nearest' });
  }, [selectedIndex]);

  const handleSelect = useCallback(async (id: string) => {
    await activateTab(id);
    onClose();
  }, [activateTab, onClose]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex(i => Math.min(i + 1, filtered.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex(i => Math.max(i - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const id = filtered[selectedIndex];
      if (id) void handleSelect(id);
    } else if (e.key === 'Escape') {
      onClose();
    }
  }, [filtered, selectedIndex, handleSelect, onClose]);

  return (
    <>
      {/* Backdrop */}
      <div
        style={{
          position: 'fixed',
          inset: 0,
          zIndex: 9998,
        }}
        onClick={onClose}
      />

      {/* Popover */}
      <div
        style={{
          position: 'fixed',
          top: 72,
          left: '50%',
          transform: 'translateX(-50%)',
          width: 480,
          maxWidth: 'calc(100vw - 32px)',
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 12,
          boxShadow: '0 8px 32px rgba(0,0,0,0.3)',
          zIndex: 9999,
          overflow: 'hidden',
        }}
        onKeyDown={handleKeyDown}
      >
        {/* Search input */}
        <div style={{
          padding: '10px 12px',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          alignItems: 'center',
          gap: 8,
        }}>
          <span style={{ fontSize: 14, opacity: 0.5 }}>🔍</span>
          <input
            ref={inputRef}
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Search open tabs…"
            style={{
              flex: 1,
              background: 'transparent',
              border: 'none',
              outline: 'none',
              fontSize: 14,
              color: 'var(--color-text)',
            }}
          />
          <kbd style={{
            fontSize: 10,
            padding: '2px 5px',
            borderRadius: 4,
            background: 'var(--color-bg-elevated)',
            border: '1px solid var(--color-border)',
            color: 'var(--color-text-muted)',
          }}>Esc</kbd>
        </div>

        {/* Results */}
        <div
          ref={listRef}
          style={{
            maxHeight: 340,
            overflowY: 'auto',
            padding: '4px 0',
          }}
        >
          {filtered.length === 0 ? (
            <div style={{
              padding: '20px 16px',
              textAlign: 'center',
              fontSize: 13,
              color: 'var(--color-text-muted)',
            }}>
              No tabs match "{query}"
            </div>
          ) : (
            filtered.map((id, idx) => {
              const tab = tabs[id];
              if (!tab) return null;
              const isSelected = idx === selectedIndex;
              const isActive = id === activeTabId;
              return (
                <div
                  key={id}
                  data-tab-item="1"
                  onClick={() => void handleSelect(id)}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    padding: '7px 12px',
                    cursor: 'pointer',
                    background: isSelected
                      ? 'var(--color-bg-elevated)'
                      : 'transparent',
                    borderLeft: isActive ? '2px solid var(--color-primary)' : '2px solid transparent',
                  }}
                >
                  {tab.favicon ? (
                    <img src={tab.favicon} width={14} height={14} style={{ borderRadius: 2, flexShrink: 0 }} alt="" />
                  ) : (
                    <div style={{ width: 14, height: 14, borderRadius: 2, background: 'var(--color-border)', flexShrink: 0 }} />
                  )}
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{
                      fontSize: 13,
                      fontWeight: isActive ? 600 : 400,
                      color: 'var(--color-text)',
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                    }}>
                      {tab.title || 'New Tab'}
                    </div>
                    <div style={{
                      fontSize: 11,
                      color: 'var(--color-text-muted)',
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                      marginTop: 1,
                    }}>
                      {tab.url || 'about:newtab'}
                    </div>
                  </div>
                  {tab.pinned && (
                    <span style={{ fontSize: 10, opacity: 0.6 }} title="Pinned">📌</span>
                  )}
                  {tab.isAudible && !tab.isMuted && (
                    <span style={{ fontSize: 11, opacity: 0.7 }}>🔊</span>
                  )}
                  {tab.isMuted && (
                    <span style={{ fontSize: 11, opacity: 0.5 }}>🔇</span>
                  )}
                </div>
              );
            })
          )}
        </div>

        {/* Footer hint */}
        <div style={{
          padding: '6px 12px',
          borderTop: '1px solid var(--color-border)',
          display: 'flex',
          gap: 12,
          fontSize: 10,
          color: 'var(--color-text-muted)',
        }}>
          <span><kbd style={{ fontSize: 10, padding: '1px 4px', borderRadius: 3, background: 'var(--color-bg-elevated)', border: '1px solid var(--color-border)' }}>↑↓</kbd> navigate</span>
          <span><kbd style={{ fontSize: 10, padding: '1px 4px', borderRadius: 3, background: 'var(--color-bg-elevated)', border: '1px solid var(--color-border)' }}>↵</kbd> switch to tab</span>
          <span><kbd style={{ fontSize: 10, padding: '1px 4px', borderRadius: 3, background: 'var(--color-bg-elevated)', border: '1px solid var(--color-border)' }}>Esc</kbd> close</span>
        </div>
      </div>
    </>
  );
}
