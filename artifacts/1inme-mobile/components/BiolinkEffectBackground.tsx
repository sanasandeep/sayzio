import { LinearGradient } from "expo-linear-gradient";
import { useState } from "react";
import { LayoutChangeEvent, StyleSheet, View } from "react-native";
import Svg, {
  Circle,
  Defs,
  Line,
  Path,
  Pattern,
  RadialGradient,
  Rect,
  Stop,
} from "react-native-svg";

import type {
  MeshSpec,
  PatternSpec,
  TilesSpec,
} from "@/lib/bgEffectCatalog";

// Native approximations of the web Tiles / Mesh / Pattern effect
// backgrounds (Task #6204) for the mobile Appearance preview:
//   - tiles: a real 4-column packed grid of gradient cells honouring the
//     catalog's per-layout col/row span cycle (metro / brick / uniform)
//   - mesh: SVG radial-gradient blobs over the base color, mirroring the
//     web's `radial-gradient(at x% y%, color 0%, transparent spread%)`
//   - pattern: SVG repeating motifs (dots, grid, stripes, zigzag, waves,
//     checker, crosshatch, honeycomb, diamonds) built with <Pattern>
// All render inside an absolute-fill layer; the caller decides fallbacks.

// ---------------------------------------------------------------- tiles

type PlacedTile = {
  left: number;
  top: number;
  width: number;
  height: number;
  from: string;
  to: string;
};

const GRID_COLS = 4;
const TILE_GAP = 3;

// First-fit packing on a 4-column grid, mirroring CSS grid auto-flow
// closely enough for a preview: each spec cycles through the palette's
// gradients and the layout's [colSpan, rowSpan] cycle.
function packTiles(spec: TilesSpec, width: number, height: number) {
  const cellW = width / GRID_COLS;
  const cellH = cellW; // square-ish cells like the web grid
  const rows = Math.max(1, Math.ceil(height / cellH) + 1);
  const occupied: boolean[][] = Array.from({ length: rows }, () =>
    Array<boolean>(GRID_COLS).fill(false),
  );
  const placed: PlacedTile[] = [];
  let i = 0;
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < GRID_COLS; c++) {
      if (occupied[r][c]) continue;
      const [rawCol, rawRow] = spec.spans[i % spec.spans.length];
      const col = Math.min(rawCol, GRID_COLS - c);
      const row = Math.min(rawRow, rows - r);
      let fits = true;
      for (let rr = r; rr < r + row && fits; rr++) {
        for (let cc = c; cc < c + col; cc++) {
          if (occupied[rr][cc]) {
            fits = false;
            break;
          }
        }
      }
      const useCol = fits ? col : 1;
      const useRow = fits ? row : 1;
      for (let rr = r; rr < r + useRow; rr++) {
        for (let cc = c; cc < c + useCol; cc++) {
          occupied[rr][cc] = true;
        }
      }
      const [from, to] = spec.tiles[i % spec.tiles.length];
      placed.push({
        left: c * cellW,
        top: r * cellH,
        width: useCol * cellW,
        height: useRow * cellH,
        from,
        to,
      });
      i++;
    }
  }
  return placed;
}

function TilesLayer({
  spec,
  width,
  height,
}: {
  spec: TilesSpec;
  width: number;
  height: number;
}) {
  const tiles = packTiles(spec, width, height);
  return (
    <View style={StyleSheet.absoluteFill} pointerEvents="none">
      {tiles.map((t, idx) => (
        <LinearGradient
          key={idx}
          colors={[t.from, t.to]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={{
            position: "absolute",
            left: t.left + TILE_GAP / 2,
            top: t.top + TILE_GAP / 2,
            width: t.width - TILE_GAP,
            height: t.height - TILE_GAP,
            borderRadius: 4,
          }}
        />
      ))}
    </View>
  );
}

// ----------------------------------------------------------------- mesh

function MeshLayer({
  spec,
  width,
  height,
}: {
  spec: MeshSpec;
  width: number;
  height: number;
}) {
  return (
    <Svg
      width={width}
      height={height}
      style={StyleSheet.absoluteFill}
      pointerEvents="none"
    >
      <Defs>
        {spec.blobs.map((b, i) => (
          <RadialGradient key={i} id={`mesh-blob-${i}`} cx="50%" cy="50%" r="50%">
            <Stop offset="0" stopColor={b.color} stopOpacity={0.9} />
            <Stop offset="0.65" stopColor={b.color} stopOpacity={0.45} />
            <Stop offset="1" stopColor={b.color} stopOpacity={0} />
          </RadialGradient>
        ))}
      </Defs>
      <Rect x={0} y={0} width={width} height={height} fill={spec.base} />
      {spec.blobs.map((b, i) => {
        const r = (b.spread / 100) * Math.max(width, height);
        return (
          <Circle
            key={i}
            cx={(b.x / 100) * width}
            cy={(b.y / 100) * height}
            r={r}
            fill={`url(#mesh-blob-${i})`}
          />
        );
      })}
    </Svg>
  );
}

// -------------------------------------------------------------- pattern

function patternTile(spec: PatternSpec) {
  switch (spec.kind) {
    case "dots":
      return {
        w: spec.size,
        h: spec.size,
        content: (
          <Circle
            cx={spec.size / 2}
            cy={spec.size / 2}
            r={spec.radius}
            fill={spec.accent}
          />
        ),
      };
    case "grid":
      return {
        w: spec.size,
        h: spec.size,
        content: (
          <>
            <Rect x={0} y={0} width={spec.size} height={1} fill={spec.accent} />
            <Rect x={0} y={0} width={1} height={spec.size} fill={spec.accent} />
          </>
        ),
      };
    case "stripes":
      return {
        w: spec.period,
        h: spec.period,
        transform: "rotate(45)",
        content: (
          <Rect x={0} y={0} width={spec.stripe} height={spec.period} fill={spec.accent} />
        ),
      };
    case "zigzag": {
      const s = spec.size;
      return {
        w: s,
        h: s,
        content: (
          <Path
            d={`M0 ${s * 0.55} L${s / 2} ${s * 0.2} L${s} ${s * 0.55} L${s} ${s * 0.75} L${s / 2} ${s * 0.4} L0 ${s * 0.75} Z`}
            fill={spec.accent}
          />
        ),
      };
    }
    case "waves": {
      const p = spec.period;
      return {
        w: p * 2,
        h: p * 2,
        content: (
          <>
            <Circle cx={0} cy={0} r={p - 1} stroke={spec.accent} strokeWidth={2} fill="none" />
            <Circle cx={p * 2} cy={p * 2} r={p - 1} stroke={spec.accent} strokeWidth={2} fill="none" />
          </>
        ),
      };
    }
    case "checker": {
      const half = spec.size / 2;
      return {
        w: spec.size,
        h: spec.size,
        content: (
          <>
            <Rect x={0} y={0} width={half} height={half} fill={spec.accent} />
            <Rect x={half} y={half} width={half} height={half} fill={spec.accent} />
          </>
        ),
      };
    }
    case "crosshatch": {
      const g = spec.gap;
      return {
        w: g,
        h: g,
        content: (
          <>
            <Line x1={0} y1={g} x2={g} y2={0} stroke={spec.accent} strokeWidth={1} />
            <Line x1={0} y1={0} x2={g} y2={g} stroke={spec.accent} strokeWidth={1} />
          </>
        ),
      };
    }
    case "honeycomb": {
      const { w, h } = spec;
      // Flattened hexagon outline spanning the tile, echoing the web's
      // two-radial-gradient honeycomb approximation.
      return {
        w,
        h,
        content: (
          <Path
            d={`M${w * 0.25} 1 L${w * 0.75} 1 L${w - 1} ${h / 2} L${w * 0.75} ${h - 1} L${w * 0.25} ${h - 1} L1 ${h / 2} Z`}
            stroke={spec.accent}
            strokeWidth={2}
            fill="none"
          />
        ),
      };
    }
    case "diamonds": {
      const s = spec.size;
      return {
        w: s,
        h: s,
        content: (
          <Path
            d={`M${s / 2} ${s * 0.12} L${s * 0.88} ${s / 2} L${s / 2} ${s * 0.88} L${s * 0.12} ${s / 2} Z`}
            fill={spec.accent}
          />
        ),
      };
    }
  }
}

function PatternLayer({
  spec,
  width,
  height,
}: {
  spec: PatternSpec;
  width: number;
  height: number;
}) {
  const tile = patternTile(spec);
  return (
    <Svg
      width={width}
      height={height}
      style={StyleSheet.absoluteFill}
      pointerEvents="none"
    >
      <Defs>
        <Pattern
          id="bg-pattern-tile"
          patternUnits="userSpaceOnUse"
          width={tile.w}
          height={tile.h}
          patternTransform={tile.transform}
        >
          {tile.content}
        </Pattern>
      </Defs>
      <Rect x={0} y={0} width={width} height={height} fill={spec.base} />
      <Rect x={0} y={0} width={width} height={height} fill="url(#bg-pattern-tile)" />
    </Svg>
  );
}

// --------------------------------------------------------------- public

export type EffectSpec =
  | { type: "tiles"; tiles: TilesSpec }
  | { type: "mesh"; mesh: MeshSpec }
  | { type: "pattern"; pattern: PatternSpec };

export function BiolinkEffectBackground({
  spec,
  baseColor,
}: {
  spec: EffectSpec;
  baseColor: string;
}) {
  const [size, setSize] = useState<{ w: number; h: number } | null>(null);
  const onLayout = (e: LayoutChangeEvent) => {
    const { width, height } = e.nativeEvent.layout;
    if (width > 0 && height > 0) setSize({ w: width, h: height });
  };
  return (
    <View
      style={[StyleSheet.absoluteFill, { backgroundColor: baseColor }]}
      onLayout={onLayout}
      pointerEvents="none"
    >
      {size ? (
        spec.type === "tiles" ? (
          <TilesLayer spec={spec.tiles} width={size.w} height={size.h} />
        ) : spec.type === "mesh" ? (
          <MeshLayer spec={spec.mesh} width={size.w} height={size.h} />
        ) : (
          <PatternLayer spec={spec.pattern} width={size.w} height={size.h} />
        )
      ) : null}
    </View>
  );
}
