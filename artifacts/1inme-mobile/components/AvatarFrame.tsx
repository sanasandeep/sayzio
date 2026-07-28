// Decorative avatar frame shapes for biolink profile cards (Task #5910).
//
// Mobile mirror of artifacts/1inme/app/Modules/User/Support/AvatarFrameCatalog.php
// — the KEYS must stay in lockstep with the server catalog (the server
// sanitizer is the source of truth; unknown keys are stripped on save, and
// this component simply renders nothing for keys it doesn't know).
//
// Geometry matches the web SVGs: 120x120 viewBox, avatar assumed to be a
// centered circle of radius ~44 (frame drawn at ~1.36x the avatar size).
import React from "react";
import Svg, { Circle, Ellipse, Path, Polygon } from "react-native-svg";

export const AVATAR_FRAME_KEYS = [
  "starburst",
  "scalloped",
  "zigzag",
  "wavy",
  "double_ring",
  "dotted_ring",
  "petal",
] as const;

export type AvatarFrameKey = (typeof AVATAR_FRAME_KEYS)[number];

export const AVATAR_FRAME_LABELS: Record<AvatarFrameKey, string> = {
  starburst: "Starburst",
  scalloped: "Scalloped",
  zigzag: "Sunburst",
  wavy: "Wavy Blob",
  double_ring: "Double Ring",
  dotted_ring: "Dotted Ring",
  petal: "Petal Bloom",
};

export function isAvatarFrameKey(v: unknown): v is AvatarFrameKey {
  return typeof v === "string" && (AVATAR_FRAME_KEYS as readonly string[]).includes(v);
}

/** Star polygon points alternating between inner and outer radii. */
function starPoints(points: number, rInner: number, rOuter: number): string {
  const pts: string[] = [];
  const steps = points * 2;
  for (let i = 0; i < steps; i++) {
    const r = i % 2 === 0 ? rOuter : rInner;
    const a = (Math.PI * 2 * i) / steps - Math.PI / 2;
    pts.push(`${(60 + r * Math.cos(a)).toFixed(2)},${(60 + r * Math.sin(a)).toFixed(2)}`);
  }
  return pts.join(" ");
}

/** Smooth radial sine-wave blob path. */
function wavyBlobPath(): string {
  const steps = 72;
  let d = "";
  for (let i = 0; i <= steps; i++) {
    const a = (Math.PI * 2 * i) / steps;
    const r = 51 + 6 * Math.sin(a * 6);
    const x = (60 + r * Math.cos(a)).toFixed(2);
    const y = (60 + r * Math.sin(a)).toFixed(2);
    d += `${i === 0 ? "M" : "L"}${x} ${y}`;
  }
  return d + "Z";
}

function ringOfCircles(n: number, radius: number, r: number, color: string) {
  const out: React.ReactElement[] = [];
  for (let i = 0; i < n; i++) {
    const a = (Math.PI * 2 * i) / n - Math.PI / 2;
    out.push(
      <Circle
        key={i}
        cx={(60 + radius * Math.cos(a)).toFixed(2)}
        cy={(60 + radius * Math.sin(a)).toFixed(2)}
        r={r}
        fill={color}
      />,
    );
  }
  return out;
}

function petalEllipses(color: string) {
  const out: React.ReactElement[] = [];
  const n = 8;
  for (let i = 0; i < n; i++) {
    const deg = (360 * i) / n;
    out.push(
      <Ellipse
        key={i}
        cx={60}
        cy={14}
        rx={11}
        ry={19}
        fill={color}
        transform={`rotate(${deg.toFixed(2)} 60 60)`}
      />,
    );
  }
  return out;
}

export function AvatarFrame({
  shape,
  color,
  size,
}: {
  shape: AvatarFrameKey;
  color: string;
  size: number;
}) {
  let body: React.ReactNode = null;
  switch (shape) {
    case "starburst":
      body = <Polygon points={starPoints(16, 46, 58)} fill={color} />;
      break;
    case "zigzag":
      body = <Polygon points={starPoints(28, 45, 56)} fill={color} />;
      break;
    case "scalloped":
      body = ringOfCircles(12, 48, 11, color);
      break;
    case "wavy":
      body = <Path d={wavyBlobPath()} fill={color} />;
      break;
    case "double_ring":
      body = (
        <>
          <Circle cx={60} cy={60} r={48} fill="none" stroke={color} strokeWidth={3} />
          <Circle cx={60} cy={60} r={55} fill="none" stroke={color} strokeWidth={1.6} />
        </>
      );
      break;
    case "dotted_ring":
      body = ringOfCircles(20, 52, 3.4, color);
      break;
    case "petal":
      body = petalEllipses(color);
      break;
    default:
      return null;
  }
  return (
    <Svg width={size} height={size} viewBox="0 0 120 120">
      {body}
    </Svg>
  );
}
