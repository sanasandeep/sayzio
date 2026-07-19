/**
 * ProfileBadge — persistent visual indicator of the active browser profile.
 *
 * Two variants:
 *  - 'ribbon': a "You are in: <name>" ribbon for the new-tab page and side
 *    panels, tinted with the profile's avatar colour.
 *  - 'pill': a compact coloured pill (dot + name) for tighter surfaces.
 *
 * The personal/default profile renders nothing by default (there is no risk
 * of confusion when only one profile exists) unless `showPersonal` is set.
 */
import { useProfileStore } from '../store/profile-store';
import { profileColor } from './ProfileSwitcher';

interface Props {
  variant?: 'ribbon' | 'pill';
  /** Also render for the personal/default profile. Defaults to false. */
  showPersonal?: boolean;
  style?: React.CSSProperties;
}

export function ProfileBadge({ variant = 'ribbon', showPersonal = false, style }: Props) {
  const { profiles, activeProfileId } = useProfileStore();
  const active = profiles.find(p => p.id === activeProfileId) ?? profiles[0];
  if (!active) return null;
  if (active.isPersonal && !showPersonal) return null;

  const color = profileColor(active.id);
  const initial = active.name.charAt(0).toUpperCase();

  if (variant === 'pill') {
    return (
      <span
        title={`Active profile: ${active.name}`}
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: 6,
          padding: '2px 10px 2px 4px',
          borderRadius: 999,
          background: `${color}1f`,
          border: `1px solid ${color}55`,
          fontSize: 11,
          fontWeight: 600,
          color: 'var(--color-text)',
          maxWidth: 160,
          ...style,
        }}
      >
        <span style={{
          width: 16,
          height: 16,
          borderRadius: '50%',
          background: color,
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 9,
          fontWeight: 700,
          color: '#fff',
          flexShrink: 0,
        }}>{initial}</span>
        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {active.name}
        </span>
      </span>
    );
  }

  // Ribbon variant
  return (
    <div
      title={`Active profile: ${active.name}`}
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8,
        padding: '5px 14px 5px 6px',
        borderRadius: 999,
        background: `${color}18`,
        border: `1px solid ${color}55`,
        maxWidth: 260,
        ...style,
      }}
    >
      <span style={{
        width: 22,
        height: 22,
        borderRadius: '50%',
        background: color,
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: 11,
        fontWeight: 700,
        color: '#fff',
        flexShrink: 0,
      }}>{initial}</span>
      <span style={{ display: 'flex', flexDirection: 'column', minWidth: 0, lineHeight: 1.25 }}>
        <span style={{ fontSize: 9, fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.6, color: 'var(--color-text-muted)' }}>
          You are in
        </span>
        <span style={{
          fontSize: 12,
          fontWeight: 700,
          color: 'var(--color-text)',
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
        }}>
          {active.name}
        </span>
      </span>
    </div>
  );
}
