/**
 * FindBar — find-in-page overlay bar, positioned at the top-right of the
 * browser content area, similar to Chrome's built-in find bar.
 *
 * Opened via Ctrl/Cmd+F (or Edit → Find on Page); Esc closes it.
 * Keyboard: Enter = next match, Shift+Enter = prev match.
 */
import { useEffect, useRef } from 'react';
import { useFindStore } from '../store/find-store';

interface Props {
  activeTabId: string | null;
}

export function FindBar({ activeTabId }: Props) {
  const {
    query,
    matchCase,
    activeMatch,
    matchCount,
    closeFind,
    setQuery,
    toggleMatchCase,
    searchNext,
    searchPrev,
  } = useFindStore();

  const inputRef = useRef<HTMLInputElement>(null);

  // Auto-focus input whenever the bar mounts (or re-mounts after being closed)
  useEffect(() => {
    const raf = requestAnimationFrame(() => {
      inputRef.current?.focus();
      inputRef.current?.select();
    });
    return () => cancelAnimationFrame(raf);
  }, []);

  // Global Esc handler to close the bar
  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        closeFind(activeTabId);
      }
    };
    document.addEventListener('keydown', onKeyDown, { capture: true });
    return () => document.removeEventListener('keydown', onKeyDown, { capture: true });
  }, [activeTabId, closeFind]);

  const handleInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (e.shiftKey) {
        searchPrev(activeTabId);
      } else {
        searchNext(activeTabId);
      }
    }
  };

  const hasQuery = query.length > 0;
  const noResults = hasQuery && matchCount === 0;

  const matchLabel = !hasQuery
    ? ''
    : matchCount === 0
      ? 'No results'
      : `${activeMatch} of ${matchCount}`;

  return (
    <div
      role="search"
      aria-label="Find in page"
      style={{
        position: 'absolute',
        top: 0,
        right: 0,
        zIndex: 200,
        display: 'flex',
        alignItems: 'center',
        gap: 4,
        padding: '5px 8px',
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderTop: 'none',
        borderRight: 'none',
        borderRadius: '0 0 0 8px',
        boxShadow: '0 2px 12px rgba(0,0,0,0.18)',
        minWidth: 280,
      }}
    >
      {/* Search input */}
      <input
        ref={inputRef}
        type="text"
        value={query}
        onChange={e => setQuery(e.target.value, activeTabId)}
        onKeyDown={handleInputKeyDown}
        placeholder="Find in page…"
        aria-label="Search text"
        style={{
          flex: 1,
          height: 26,
          borderRadius: 6,
          border: `1px solid ${noResults ? 'var(--color-danger, #e53e3e)' : 'var(--color-border)'}`,
          background: noResults
            ? 'rgba(229,62,62,0.08)'
            : 'var(--color-bg-elevated)',
          color: 'var(--color-text)',
          padding: '0 8px',
          fontSize: 13,
          outline: 'none',
          minWidth: 0,
          transition: 'border-color 0.12s, background 0.12s',
        }}
      />

      {/* Match counter */}
      <span
        aria-live="polite"
        style={{
          fontSize: 11,
          color: noResults ? 'var(--color-danger, #e53e3e)' : 'var(--color-text-muted)',
          whiteSpace: 'nowrap',
          minWidth: 56,
          textAlign: 'center',
          flexShrink: 0,
        }}
      >
        {matchLabel}
      </span>

      {/* Prev match */}
      <button
        onClick={() => searchPrev(activeTabId)}
        disabled={!hasQuery || matchCount === 0}
        title="Previous match (Shift+Enter)"
        style={{
          width: 24,
          height: 24,
          borderRadius: 5,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 12,
          background: 'var(--color-bg-elevated)',
          border: '1px solid var(--color-border)',
          opacity: (!hasQuery || matchCount === 0) ? 0.35 : 1,
          cursor: (!hasQuery || matchCount === 0) ? 'not-allowed' : 'pointer',
          flexShrink: 0,
          transition: 'opacity 0.1s',
        }}
      >↑</button>

      {/* Next match */}
      <button
        onClick={() => searchNext(activeTabId)}
        disabled={!hasQuery || matchCount === 0}
        title="Next match (Enter)"
        style={{
          width: 24,
          height: 24,
          borderRadius: 5,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 12,
          background: 'var(--color-bg-elevated)',
          border: '1px solid var(--color-border)',
          opacity: (!hasQuery || matchCount === 0) ? 0.35 : 1,
          cursor: (!hasQuery || matchCount === 0) ? 'not-allowed' : 'pointer',
          flexShrink: 0,
          transition: 'opacity 0.1s',
        }}
      >↓</button>

      {/* Case-sensitivity toggle */}
      <button
        onClick={() => toggleMatchCase(activeTabId)}
        title={matchCase ? 'Case sensitive (on)' : 'Case sensitive (off)'}
        style={{
          width: 24,
          height: 24,
          borderRadius: 5,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 11,
          fontWeight: 700,
          background: matchCase ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
          color: matchCase ? '#fff' : 'var(--color-text-muted)',
          border: `1px solid ${matchCase ? 'var(--color-primary)' : 'var(--color-border)'}`,
          flexShrink: 0,
          cursor: 'pointer',
          transition: 'all 0.12s',
        }}
      >Aa</button>

      {/* Close */}
      <button
        onClick={() => closeFind(activeTabId)}
        title="Close (Esc)"
        style={{
          width: 22,
          height: 22,
          borderRadius: 5,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 12,
          background: 'transparent',
          border: 'none',
          color: 'var(--color-text-muted)',
          opacity: 0.7,
          flexShrink: 0,
          cursor: 'pointer',
        }}
      >✕</button>
    </div>
  );
}
