import Svg, { Circle, Defs, LinearGradient, Path, Rect, Stop } from "react-native-svg";

/**
 * Host/organizer avatar placeholder — ported 1:1 from the web asset
 * (artifacts/1inme/public/images/events/host-avatar-placeholder.svg,
 * Task #3798) so a host with no organizer logo and no personal avatar shows
 * the same clean gradient silhouette on mobile as on the web event page,
 * instead of a bare person icon. Preference order stays organizer logo ->
 * personal avatar -> this placeholder.
 */

type Props = {
  size?: number;
};

export function HostAvatarPlaceholder({ size = 36 }: Props) {
  return (
    <Svg width={size} height={size} viewBox="0 0 96 96">
      <Defs>
        <LinearGradient id="hostph-bg" x1="0" y1="0" x2="1" y2="1">
          <Stop offset="0%" stopColor="#2342c7" />
          <Stop offset="100%" stopColor="#3d6bff" />
        </LinearGradient>
      </Defs>
      <Rect width={96} height={96} fill="url(#hostph-bg)" />
      <Circle cx={48} cy={38} r={17} fill="#ffffff" fillOpacity={0.85} />
      <Path
        d="M14 88c3-19.5 17.5-32 34-32s31 12.5 34 32"
        fill="#ffffff"
        fillOpacity={0.85}
      />
    </Svg>
  );
}
