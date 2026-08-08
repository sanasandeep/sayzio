/**
 * SayzioRail — collapsible Sayzio menu rail on the LEFT edge of the browser
 * content area. Clicking an item opens that Sayzio page in the active tab's
 * dashboard pane: tabs already showing a dashboard just navigate; any other
 * tab is switched into a split that keeps its current content and adds the
 * dashboard (main-process logic — tabs:navigate-dashboard).
 *
 * The rail is renderer-drawn DOM, but native WebContentsViews sit ABOVE the
 * DOM — so the main process reserves the rail's width on the left of every
 * native view (window:set-sayzio-rail-reserve, reported by App). The rail
 * itself never overlaps a native view.
 */
import { useState } from 'react';

export const SAYZIO_RAIL_COLLAPSED_WIDTH = 52;
export const SAYZIO_RAIL_EXPANDED_WIDTH = 168;

const EXPANDED_KEY = 'zio.sayzioRail.expanded';

export function loadRailExpanded(): boolean {
  try { return localStorage.getItem(EXPANDED_KEY) === '1'; } catch { return false; }
}

const NAV_ITEMS: Array<{ icon: string; label: string; path: string }> = [
  { icon: '🏠', label: 'Dashboard', path: '/user/dashboard' },
  { icon: '🔗', label: 'All Links', path: '/user/links' },
  { icon: '📅', label: 'My Calendar', path: '/user/my-calendar' },
  { icon: '💬', label: 'Inbox', path: '/user/inbox' },
  { icon: '🔔', label: 'Notifications', path: '/user/notifications' },
  { icon: '📈', label: 'Stats', path: '/user/stats' },
  { icon: '👥', label: 'Visitors', path: '/user/visitors' },
  { icon: '⊞', label: 'QR Codes', path: '/user/qr-codes' },
];

interface Props {
  expanded: boolean;
  onToggleExpanded: (expanded: boolean) => void;
  onNavigate: (path: string) => void;
}

export function SayzioRail({ expanded, onToggleExpanded, onNavigate }: Props) {
  const [hovered, setHovered] = useState<string | null>(null);
  const width = expanded ? SAYZIO_RAIL_EXPANDED_WIDTH : SAYZIO_RAIL_COLLAPSED_WIDTH;

  return (
    <div
      style={{
        width,
        flexShrink: 0,
        display: 'flex',
        flexDirection: 'column',
        background: 'var(--color-bg-surface)',
        borderRight: '1px solid var(--color-border)',
        overflowY: 'auto',
        overflowX: 'hidden',
        // No width transition: the main process moves native views to the
        // final width instantly, so an animated rail would be covered (or
        // leave a gap) mid-transition.
        zIndex: 5,
      }}
    >
      <button
        onClick={() => {
          const next = !expanded;
          try { localStorage.setItem(EXPANDED_KEY, next ? '1' : '0'); } catch { /* private storage */ }
          onToggleExpanded(next);
        }}
        title={expanded ? 'Collapse Sayzio menu' : 'Expand Sayzio menu'}
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: expanded ? 'flex-end' : 'center',
          gap: 8,
          padding: '8px 10px',
          background: 'transparent',
          border: 'none',
          borderBottom: '1px solid var(--color-border)',
          color: 'var(--color-text-muted)',
          cursor: 'pointer',
          fontSize: 13,
        }}
      >
        {expanded ? '«' : '»'}
      </button>

      {NAV_ITEMS.map(item => (
        <button
          key={item.path}
          onClick={() => onNavigate(item.path)}
          onMouseEnter={() => setHovered(item.path)}
          onMouseLeave={() => setHovered(prev => (prev === item.path ? null : prev))}
          title={expanded ? undefined : item.label}
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: expanded ? 'flex-start' : 'center',
            gap: 10,
            padding: expanded ? '9px 14px' : '9px 0',
            background: hovered === item.path ? 'var(--color-bg-elevated)' : 'transparent',
            border: 'none',
            color: 'var(--color-text)',
            cursor: 'pointer',
            textAlign: 'left',
            whiteSpace: 'nowrap',
            transition: 'background 0.12s',
          }}
        >
          <span style={{ fontSize: 16, flexShrink: 0 }}>{item.icon}</span>
          {expanded && <span style={{ fontSize: 12.5, fontWeight: 500 }}>{item.label}</span>}
        </button>
      ))}
    </div>
  );
}
