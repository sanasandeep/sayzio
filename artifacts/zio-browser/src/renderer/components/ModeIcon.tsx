/**
 * ModeIcon — colorful SVG icons for the three window modes, replacing the
 * old unicode glyphs (⊡ / ⬛ / 🌐). Each icon uses its own gradient fill so
 * modes are instantly distinguishable at any size; gradient IDs are suffixed
 * per instance so multiple icons on one screen never collide.
 */
import { useId } from 'react';
import type { WindowMode } from '../../shared/window-mode';

interface Props {
  mode: WindowMode;
  /** Rendered width/height in px. */
  size?: number;
}

export function ModeIcon({ mode, size = 28 }: Props) {
  const uid = useId().replace(/[^a-zA-Z0-9]/g, '');
  const gid = `mode-${mode}-${uid}`;

  if (mode === 'dashboard') {
    // Dashboard — grid of tiles, indigo→violet gradient.
    return (
      <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true" style={{ display: 'block' }}>
        <defs>
          <linearGradient id={gid} x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse">
            <stop stopColor="#6366f1" />
            <stop offset="1" stopColor="#a855f7" />
          </linearGradient>
        </defs>
        <rect x="3" y="3" width="8" height="8" rx="2" fill={`url(#${gid})`} />
        <rect x="13" y="3" width="8" height="8" rx="2" fill={`url(#${gid})`} opacity="0.55" />
        <rect x="3" y="13" width="8" height="8" rx="2" fill={`url(#${gid})`} opacity="0.55" />
        <rect x="13" y="13" width="8" height="8" rx="2" fill={`url(#${gid})`} />
      </svg>
    );
  }

  if (mode === 'split') {
    // Split — two side-by-side panes, cyan→blue gradient.
    return (
      <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true" style={{ display: 'block' }}>
        <defs>
          <linearGradient id={gid} x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse">
            <stop stopColor="#06b6d4" />
            <stop offset="1" stopColor="#3b82f6" />
          </linearGradient>
        </defs>
        <rect x="3" y="4" width="8.5" height="16" rx="2" fill={`url(#${gid})`} />
        <rect x="13.5" y="4" width="7.5" height="16" rx="2" fill={`url(#${gid})`} opacity="0.5" />
        <rect x="15" y="7" width="4.5" height="1.8" rx="0.9" fill="#fff" opacity="0.85" />
        <rect x="15" y="10.4" width="4.5" height="1.8" rx="0.9" fill="#fff" opacity="0.55" />
      </svg>
    );
  }

  // Browser — globe with meridians, emerald→teal gradient.
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true" style={{ display: 'block' }}>
      <defs>
        <linearGradient id={gid} x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse">
          <stop stopColor="#10b981" />
          <stop offset="1" stopColor="#0ea5e9" />
        </linearGradient>
      </defs>
      <circle cx="12" cy="12" r="9" fill={`url(#${gid})`} />
      <path
        d="M3.4 12h17.2M12 3a14.2 14.2 0 0 1 3.2 9A14.2 14.2 0 0 1 12 21a14.2 14.2 0 0 1-3.2-9A14.2 14.2 0 0 1 12 3z"
        stroke="#fff"
        strokeWidth="1.4"
        strokeLinecap="round"
        opacity="0.85"
        fill="none"
      />
      <circle cx="12" cy="12" r="9" stroke="#fff" strokeWidth="1.4" opacity="0.85" fill="none" />
    </svg>
  );
}
