import Svg, {
  Circle,
  G,
  Line,
  Path,
  Rect,
  Text as SvgText,
} from "react-native-svg";

import type { LinkKind } from "@/lib/linkKinds";

/**
 * Small mini-mockup illustrations for each creatable link kind, ported
 * 1:1 from the web Create Link page SVGs
 * (artifacts/1inme/public/img/link-types/*.svg) so the at-a-glance visual
 * language stays identical across web and mobile. viewBox is 160x96 (5:3),
 * matching the web cards' aspect ratio. The translucent fills read well on
 * both light and dark cards.
 */

type Props = {
  kind: LinkKind;
  width?: number;
  height?: number;
};

const VB_W = 160;
const VB_H = 96;

function Art({ kind }: { kind: LinkKind }) {
  switch (kind) {
    case "url":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#a78bfa" fillOpacity={0.1} stroke="#a78bfa" strokeOpacity={0.3} />
          <Rect x={20} y={26} width={120} height={9} rx={4.5} fill="#94a3b8" fillOpacity={0.4} />
          <Rect x={20} y={40} width={84} height={7} rx={3.5} fill="#94a3b8" fillOpacity={0.25} />
          <Path d="M80 52 l6 8 h-12 z" fill="#a78bfa" />
          <Rect x={34} y={62} width={92} height={18} rx={9} fill="#a78bfa" fillOpacity={0.18} stroke="#a78bfa" strokeOpacity={0.55} />
          <Circle cx={48} cy={71} r={3} fill="#a78bfa" />
          <Rect x={58} y={68} width={44} height={6} rx={3} fill="#a78bfa" fillOpacity={0.7} />
          <Path d="M110 68 l6 3 l-6 3" fill="none" stroke="#a78bfa" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        </>
      );
    case "file":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#34d399" fillOpacity={0.1} stroke="#34d399" strokeOpacity={0.3} />
          <Path d="M58 16 h30 l16 16 v42 a4 4 0 0 1 -4 4 h-42 a4 4 0 0 1 -4 -4 v-54 a4 4 0 0 1 4 -4 z" fill="#94a3b8" fillOpacity={0.12} stroke="#94a3b8" strokeOpacity={0.45} />
          <Path d="M88 16 v16 h16" fill="none" stroke="#94a3b8" strokeOpacity={0.55} strokeWidth={2} />
          <Rect x={62} y={40} width={36} height={4} rx={2} fill="#94a3b8" fillOpacity={0.4} />
          <Rect x={62} y={48} width={28} height={4} rx={2} fill="#94a3b8" fillOpacity={0.4} />
          <Circle cx={98} cy={68} r={14} fill="#34d399" fillOpacity={0.2} stroke="#34d399" strokeOpacity={0.6} />
          <Path d="M98 61 v12 m0 0 l-5 -5 m5 5 l5 -5" fill="none" stroke="#34d399" strokeWidth={2.4} strokeLinecap="round" strokeLinejoin="round" />
        </>
      );
    case "calendar":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#fbbf24" fillOpacity={0.1} stroke="#fbbf24" strokeOpacity={0.3} />
          <Rect x={44} y={22} width={72} height={58} rx={8} fill="#94a3b8" fillOpacity={0.12} stroke="#94a3b8" strokeOpacity={0.4} />
          <Path d="M44 36 a8 8 0 0 1 8 -8 h56 a8 8 0 0 1 8 8 z" fill="#fbbf24" fillOpacity={0.45} />
          <Line x1={58} y1={18} x2={58} y2={30} stroke="#fbbf24" strokeWidth={3} strokeLinecap="round" />
          <Line x1={102} y1={18} x2={102} y2={30} stroke="#fbbf24" strokeWidth={3} strokeLinecap="round" />
          <G fill="#94a3b8" fillOpacity={0.45}>
            <Circle cx={58} cy={50} r={3} />
            <Circle cx={72} cy={50} r={3} />
            <Circle cx={100} cy={50} r={3} />
            <Circle cx={58} cy={64} r={3} />
            <Circle cx={100} cy={64} r={3} />
          </G>
          <Circle cx={86} cy={50} r={5} fill="#fbbf24" />
          <Circle cx={86} cy={64} r={3} fill="#94a3b8" fillOpacity={0.45} />
          <Circle cx={72} cy={64} r={3} fill="#94a3b8" fillOpacity={0.45} />
        </>
      );
    case "vcard":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#22d3ee" fillOpacity={0.1} stroke="#22d3ee" strokeOpacity={0.3} />
          <Rect x={22} y={26} width={116} height={44} rx={9} fill="#94a3b8" fillOpacity={0.12} stroke="#94a3b8" strokeOpacity={0.4} />
          <Circle cx={46} cy={48} r={12} fill="#22d3ee" fillOpacity={0.3} />
          <Circle cx={46} cy={43} r={4.5} fill="#22d3ee" />
          <Path d="M38 56 a8 7 0 0 1 16 0 z" fill="#22d3ee" />
          <Rect x={68} y={38} width={54} height={6} rx={3} fill="#22d3ee" fillOpacity={0.65} />
          <Rect x={68} y={49} width={44} height={5} rx={2.5} fill="#94a3b8" fillOpacity={0.45} />
          <Rect x={68} y={58} width={36} height={5} rx={2.5} fill="#94a3b8" fillOpacity={0.45} />
        </>
      );
    case "biolink":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#f472b6" fillOpacity={0.1} stroke="#f472b6" strokeOpacity={0.3} />
          <Rect x={58} y={10} width={44} height={76} rx={9} fill="#94a3b8" fillOpacity={0.12} stroke="#94a3b8" strokeOpacity={0.4} />
          <Circle cx={80} cy={26} r={8} fill="#f472b6" />
          <Rect x={70} y={38} width={20} height={4} rx={2} fill="#94a3b8" fillOpacity={0.5} />
          <Rect x={66} y={48} width={28} height={8} rx={4} fill="#f472b6" fillOpacity={0.45} />
          <Rect x={66} y={60} width={28} height={8} rx={4} fill="#f472b6" fillOpacity={0.32} />
          <Rect x={66} y={72} width={28} height={8} rx={4} fill="#f472b6" fillOpacity={0.22} />
        </>
      );
    case "resume":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#818cf8" fillOpacity={0.1} stroke="#818cf8" strokeOpacity={0.3} />
          <Rect x={50} y={12} width={60} height={72} rx={7} fill="#94a3b8" fillOpacity={0.12} stroke="#94a3b8" strokeOpacity={0.4} />
          <Rect x={50} y={12} width={60} height={20} rx={7} fill="#818cf8" fillOpacity={0.35} />
          <Circle cx={62} cy={22} r={5} fill="#818cf8" />
          <Rect x={72} y={18} width={30} height={4} rx={2} fill="#818cf8" fillOpacity={0.8} />
          <Rect x={72} y={25} width={22} height={3} rx={1.5} fill="#818cf8" fillOpacity={0.55} />
          <Rect x={56} y={40} width={20} height={3.5} rx={1.75} fill="#94a3b8" fillOpacity={0.5} />
          <Rect x={56} y={48} width={48} height={3.5} rx={1.75} fill="#94a3b8" fillOpacity={0.4} />
          <Rect x={56} y={55} width={40} height={3.5} rx={1.75} fill="#94a3b8" fillOpacity={0.4} />
          <Rect x={56} y={66} width={20} height={3.5} rx={1.75} fill="#94a3b8" fillOpacity={0.5} />
          <Rect x={56} y={74} width={48} height={3.5} rx={1.75} fill="#94a3b8" fillOpacity={0.4} />
        </>
      );
    case "paid_page":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#fb7185" fillOpacity={0.1} stroke="#fb7185" strokeOpacity={0.3} />
          <Path d="M70 18 l4 6 l6 -8 l6 8 l4 -6 v10 h-20 z" fill="#fb7185" />
          <Circle cx={80} cy={42} r={9} fill="#fb7185" fillOpacity={0.35} />
          <Circle cx={80} cy={42} r={3.5} fill="#fb7185" />
          <Rect x={24} y={58} width={30} height={24} rx={5} fill="#94a3b8" fillOpacity={0.14} stroke="#94a3b8" strokeOpacity={0.35} />
          <Rect x={65} y={58} width={30} height={24} rx={5} fill="#fb7185" fillOpacity={0.2} stroke="#fb7185" strokeOpacity={0.5} />
          <Rect x={106} y={58} width={30} height={24} rx={5} fill="#94a3b8" fillOpacity={0.14} stroke="#94a3b8" strokeOpacity={0.35} />
          <SvgText x={80} y={74} fontSize={12} fontWeight="700" fill="#fb7185" textAnchor="middle">
            $
          </SvgText>
        </>
      );
    case "ai_chat":
      return (
        <>
          <Rect x={4} y={4} width={152} height={88} rx={14} fill="#2dd4bf" fillOpacity={0.1} stroke="#2dd4bf" strokeOpacity={0.3} />
          <Line x1={80} y1={20} x2={80} y2={28} stroke="#2dd4bf" strokeWidth={2} strokeLinecap="round" />
          <Circle cx={80} cy={18} r={3} fill="#2dd4bf" />
          <Rect x={54} y={30} width={52} height={40} rx={12} fill="#2dd4bf" fillOpacity={0.16} stroke="#2dd4bf" strokeOpacity={0.55} />
          <Circle cx={69} cy={48} r={4} fill="#2dd4bf" />
          <Circle cx={91} cy={48} r={4} fill="#2dd4bf" />
          <Rect x={68} y={58} width={24} height={4} rx={2} fill="#2dd4bf" fillOpacity={0.6} />
          <Path d="M120 30 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 l5 -2 z" fill="#2dd4bf" />
        </>
      );
    default:
      return (
        <Rect x={4} y={4} width={152} height={88} rx={14} fill="#94a3b8" fillOpacity={0.1} stroke="#94a3b8" strokeOpacity={0.3} />
      );
  }
}

export function LinkTypeArt({ kind, width = 160, height = 96 }: Props) {
  return (
    <Svg width={width} height={height} viewBox={`0 0 ${VB_W} ${VB_H}`}>
      <Art kind={kind} />
    </Svg>
  );
}
