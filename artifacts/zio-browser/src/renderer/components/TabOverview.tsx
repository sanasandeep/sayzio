/**
 * TabOverview — Safari-style exposé grid of all open tabs.
 *
 * Renders an overlay covering the content area below the chrome bar (the
 * caller holds the ref-counted chrome overlay so native WebContentsViews are
 * detached underneath; the chrome bar itself stays visible and clickable so
 * the toolbar toggle can dismiss the overview). Opening zooms the active tab
 * down from the full content area into its grid slot (FLIP); picking a card
 * zooms it back up before activating. External closers (toolbar toggle,
 * keyboard shortcut) call the imperative `dismiss()` handle so every close
 * path runs the exit animation.
 *
 * Keyboard: Escape closes, arrow keys move selection, Enter activates.
 * Respects prefers-reduced-motion (fades only, no zoom transforms).
 */
import {
  useState, useRef, useEffect, useLayoutEffect, useCallback, useMemo,
  forwardRef, useImperativeHandle,
} from 'react';
import type { TabRecord } from '../store/tab-store';
import { resolveFavicon } from '../../shared/favicon';
import { FaviconImg } from './FaviconImg';

export interface TabOverviewHandle {
  /** Animated close without switching tabs (toolbar toggle / shortcut). */
  dismiss: () => void;
}

interface Props {
  tabs: Record<string, TabRecord>;
  tabOrder: string[];
  activeTabId: string | null;
  isPrivate: boolean;
  onClose: () => void;
  onActivate: (id: string) => void;
  onCloseTab: (id: string) => void;
  onNewTab: () => void;
}

const ANIM_MS = 300;
const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';

function usePrefersReducedMotion(): boolean {
  const [reduced, setReduced] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
  );
  useEffect(() => {
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
    const handler = () => setReduced(mq.matches);
    mq.addEventListener('change', handler);
    return () => mq.removeEventListener('change', handler);
  }, []);
  return reduced;
}

/** Transform that maps a card's slot rect onto the overlay's content area. */
function zoomTransform(cardEl: HTMLElement, containerEl: HTMLElement): string {
  const r = cardEl.getBoundingClientRect();
  const c = containerEl.getBoundingClientRect();
  const s = Math.max(c.width / r.width, c.height / r.height);
  const tx = c.left + c.width / 2 - (r.left + r.width / 2);
  const ty = c.top + c.height / 2 - (r.top + r.height / 2);
  return `translate(${tx}px, ${ty}px) scale(${s})`;
}

export const TabOverview = forwardRef<TabOverviewHandle, Props>(function TabOverview({
  tabs, tabOrder, activeTabId, isPrivate,
  onClose, onActivate, onCloseTab, onNewTab,
}, ref) {
  const [thumbs, setThumbs] = useState<Record<string, string | null>>({});
  const [selectedId, setSelectedId] = useState<string | null>(activeTabId);
  // 'measure' — first paintless pass to read layout; 'enter' — initial
  // transforms applied; 'in' — settled; 'out' — zoom back to a tab.
  const [phase, setPhase] = useState<'measure' | 'enter' | 'in' | 'out'>('measure');
  const [zoomTargetId, setZoomTargetId] = useState<string | null>(null);
  // FLIP transform for the active card during the enter phase.
  const [enterTransform, setEnterTransform] = useState<string | null>(null);
  const reducedMotion = usePrefersReducedMotion();
  const containerRef = useRef<HTMLDivElement>(null);
  const cardRefs = useRef(new Map<string, HTMLDivElement>());
  const closingRef = useRef(false);

  // Refresh thumbnails when the overview opens.
  useEffect(() => {
    let cancelled = false;
    void window.zio.tabs.captureThumbnails().then((t) => {
      if (!cancelled) setThumbs(t ?? {});
    }).catch(() => { /* placeholders will render */ });
    return () => { cancelled = true; };
  }, []);

  // Enter FLIP: measure the active card's slot before first paint, start it
  // covering the whole content area (where the live page just was), then
  // release to identity so it zooms down into its slot.
  useLayoutEffect(() => {
    if (phase !== 'measure') return;
    if (!reducedMotion && activeTabId && containerRef.current) {
      const el = cardRefs.current.get(activeTabId);
      if (el) setEnterTransform(zoomTransform(el, containerRef.current));
    }
    setPhase('enter');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase]);

  useEffect(() => {
    if (phase !== 'enter') return;
    const raf = requestAnimationFrame(() => {
      requestAnimationFrame(() => setPhase('in'));
    });
    return () => cancelAnimationFrame(raf);
  }, [phase]);

  /** Zoom the chosen card up to fill the content area, then activate + close. */
  const pickTab = useCallback((id: string) => {
    if (closingRef.current) return;
    closingRef.current = true;
    if (reducedMotion) {
      onActivate(id);
      onClose();
      return;
    }
    setZoomTargetId(id);
    setPhase('out');
    window.setTimeout(() => {
      onActivate(id);
      onClose();
    }, ANIM_MS);
  }, [reducedMotion, onActivate, onClose]);

  /** Close without switching tabs (Escape / toolbar toggle / backdrop). */
  const dismiss = useCallback(() => {
    if (closingRef.current) return;
    closingRef.current = true;
    if (reducedMotion || !activeTabId || !tabs[activeTabId]) {
      onClose();
      return;
    }
    setZoomTargetId(activeTabId);
    setPhase('out');
    window.setTimeout(() => onClose(), ANIM_MS);
  }, [reducedMotion, activeTabId, tabs, onClose]);

  useImperativeHandle(ref, () => ({ dismiss }), [dismiss]);

  // Grid column count mirrors the CSS auto-fill so arrow navigation works.
  const columns = useMemo(() => {
    const w = typeof window !== 'undefined' ? window.innerWidth : 1280;
    return Math.max(1, Math.min(tabOrder.length + 1, Math.floor((w - 96) / 264)));
  }, [tabOrder.length]);

  // Keyboard navigation. The "+" tile is a virtual last slot (id '__new__').
  useEffect(() => {
    const slots = [...tabOrder, '__new__'];
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        dismiss();
        return;
      }
      const idx = selectedId ? slots.indexOf(selectedId) : -1;
      const move = (delta: number) => {
        e.preventDefault();
        const next = idx === -1 ? 0 : Math.min(slots.length - 1, Math.max(0, idx + delta));
        setSelectedId(slots[next] ?? null);
      };
      switch (e.key) {
        case 'ArrowRight': move(1); break;
        case 'ArrowLeft': move(-1); break;
        case 'ArrowDown': move(columns); break;
        case 'ArrowUp': move(-columns); break;
        case 'Enter':
          e.preventDefault();
          if (selectedId === '__new__') {
            onNewTab();
            onClose();
          } else if (selectedId) {
            pickTab(selectedId);
          }
          break;
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [tabOrder, selectedId, columns, dismiss, pickTab, onNewTab, onClose]);

  /**
   * Card style per phase. The active card FLIP-zooms between the content
   * area and its grid slot on enter/exit; other cards scale/fade.
   */
  const cardStyle = (id: string): React.CSSProperties => {
    const base: React.CSSProperties = {
      position: 'relative',
      borderRadius: 12,
      overflow: 'hidden',
      cursor: 'pointer',
      background: 'var(--color-bg-surface)',
      border: `2px solid ${
        selectedId === id
          ? 'var(--color-primary)'
          : id === activeTabId
            ? 'var(--color-border)'
            : 'transparent'
      }`,
      boxShadow: selectedId === id
        ? '0 8px 30px rgba(0,0,0,0.4)'
        : '0 4px 16px rgba(0,0,0,0.25)',
      display: 'flex',
      flexDirection: 'column',
    };

    if (reducedMotion) {
      base.transition = `opacity ${ANIM_MS}ms ease`;
      base.opacity = phase === 'measure' || phase === 'out' ? 0 : 1;
      return base;
    }

    const isActiveEnter = id === activeTabId && enterTransform != null;
    // No transition during the paintless measure pass or when applying the
    // initial enter transforms — the animation starts on the flip to 'in'.
    base.transition = phase === 'measure' || phase === 'enter'
      ? 'none'
      : `transform ${ANIM_MS}ms ${EASE}, opacity ${ANIM_MS}ms ${EASE}, border-color 0.15s, box-shadow 0.15s`;

    if (phase === 'measure') {
      // Laid out normally so rects are measurable, but not yet visible.
      base.opacity = 0;
    } else if (phase === 'enter') {
      if (isActiveEnter) {
        base.transform = enterTransform;
        base.opacity = 1;
        base.zIndex = 5;
      } else {
        base.opacity = 0;
        base.transform = 'scale(0.86)';
      }
    } else if (phase === 'out') {
      if (zoomTargetId === id) {
        const el = cardRefs.current.get(id);
        if (el && containerRef.current) {
          base.transform = zoomTransform(el, containerRef.current);
          base.opacity = 0;
          base.zIndex = 5;
        }
      } else {
        base.opacity = 0;
        base.transform = 'scale(0.92)';
      }
    } else {
      base.opacity = 1;
      base.transform = 'scale(1)';
      if (id === activeTabId) base.zIndex = 1;
    }
    return base;
  };

  return (
    <div
      ref={containerRef}
      data-testid="tab-overview"
      onMouseDown={(e) => {
        // Clicks on the backdrop (not a card) dismiss the overview.
        if (e.target === e.currentTarget) dismiss();
      }}
      style={{
        // Sits below the chrome bar so the toolbar cluster stays clickable
        // (the Tab Overview toggle can animate the overview closed).
        position: 'fixed',
        top: 'var(--chrome-height, 72px)',
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 9000,
        overflowY: 'auto',
        overflowX: 'hidden',
        padding: '32px 48px 64px',
        background: isPrivate
          ? 'linear-gradient(180deg, #14121d 0%, #1a1726 100%)'
          : 'var(--color-bg-base, #101014)',
        opacity: phase === 'measure' ? 1 : phase === 'out' && reducedMotion ? 0 : 1,
        transition: `opacity ${ANIM_MS}ms ease`,
      }}
    >
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
          gap: 24,
          maxWidth: 1400,
          margin: '0 auto',
          pointerEvents: phase === 'out' ? 'none' : 'auto',
        }}
        onMouseDown={(e) => {
          if (e.target === e.currentTarget) dismiss();
        }}
      >
        {tabOrder.map((id) => {
          const tab = tabs[id];
          if (!tab) return null;
          const thumb = thumbs[id];
          return (
            <div
              key={id}
              ref={(el) => { if (el) cardRefs.current.set(id, el); else cardRefs.current.delete(id); }}
              data-testid={`tab-overview-card-${id}`}
              onClick={() => pickTab(id)}
              onMouseEnter={() => setSelectedId(id)}
              style={cardStyle(id)}
            >
              {/* Thumbnail (16:10-ish) or placeholder */}
              <div style={{
                aspectRatio: '16 / 10',
                background: isPrivate ? '#221e30' : 'var(--color-bg-elevated)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
              }}>
                {thumb ? (
                  <img
                    src={thumb}
                    alt=""
                    draggable={false}
                    style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'top' }}
                  />
                ) : (
                  <FaviconImg
                    src={resolveFavicon(tab.favicon ?? null, tab.url)}
                    size={40}
                    fallback={<span style={{ fontSize: 34, opacity: 0.35 }}>🌐</span>}
                  />
                )}
              </div>

              {/* Title bar */}
              <div style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '8px 10px',
                background: 'var(--color-bg-surface)',
                borderTop: '1px solid var(--color-border)',
                minWidth: 0,
              }}>
                <FaviconImg
                  src={resolveFavicon(tab.favicon ?? null, tab.url)}
                  size={14}
                  fallback={<div style={{ width: 14, height: 14, borderRadius: 3, background: 'var(--color-border)', flexShrink: 0 }} />}
                />
                <span style={{
                  fontSize: 12,
                  color: 'var(--color-text)',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  whiteSpace: 'nowrap',
                  flex: 1,
                }}>
                  {tab.title || tab.url || 'New Tab'}
                </span>
              </div>

              {/* Hover close × */}
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  onCloseTab(id);
                }}
                title="Close tab"
                aria-label={`Close ${tab.title || 'tab'}`}
                style={{
                  position: 'absolute',
                  top: 6,
                  left: 6,
                  width: 22,
                  height: 22,
                  borderRadius: 11,
                  border: 'none',
                  background: 'rgba(0,0,0,0.55)',
                  color: '#fff',
                  fontSize: 12,
                  lineHeight: 1,
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  opacity: selectedId === id ? 1 : 0,
                  transition: 'opacity 0.12s',
                }}
              >
                ×
              </button>
            </div>
          );
        })}

        {/* "+" new-tab tile */}
        <div
          data-testid="tab-overview-new-tab"
          onClick={() => { onNewTab(); onClose(); }}
          onMouseEnter={() => setSelectedId('__new__')}
          style={{
            borderRadius: 12,
            border: `2px dashed ${selectedId === '__new__' ? 'var(--color-primary)' : 'var(--color-border)'}`,
            background: 'transparent',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            minHeight: 150,
            aspectRatio: '16 / 12.4',
            cursor: 'pointer',
            color: selectedId === '__new__' ? 'var(--color-primary)' : 'var(--color-text-muted)',
            fontSize: 40,
            fontWeight: 300,
            opacity: phase === 'in' ? 1 : 0,
            transform: reducedMotion ? undefined : phase === 'in' ? 'scale(1)' : 'scale(0.86)',
            transition: reducedMotion
              ? `opacity ${ANIM_MS}ms ease`
              : phase === 'measure' || phase === 'enter'
                ? 'none'
                : `transform ${ANIM_MS}ms ${EASE}, opacity ${ANIM_MS}ms ${EASE}, border-color 0.15s, color 0.15s`,
          }}
          title="New tab"
        >
          +
        </div>
      </div>

      {/* Private-window hint */}
      {isPrivate && (
        <div style={{
          textAlign: 'center',
          marginTop: 28,
          fontSize: 12,
          color: 'rgba(167,139,250,0.7)',
        }}>
          🕶 Private window — tabs are not saved
        </div>
      )}
    </div>
  );
});
