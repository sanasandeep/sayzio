import React, { useMemo } from "react";
import { StyleSheet, Text, View } from "react-native";
import Svg, { Line, Rect } from "react-native-svg";

import { useColors } from "@/hooks/useColors";

export type TrendPoint = {
  d: string;
  visitors: number;
  new: number;
  returning: number;
};

/**
 * Zero-dependency native stacked-bar trend chart for the Visitors screens
 * (Task #3816). Renders one bar per day with a "new" (primary) and a
 * "returning" (muted accent) segment stacked, replacing the web Chart.js
 * new-vs-returning trend. Uses the react-native-svg lib already bundled for
 * the QR/heatmap surfaces so it adds no new dependency.
 */
export function VisitorTrendChart({
  data,
  height = 140,
}: {
  data: TrendPoint[];
  height?: number;
}) {
  const colors = useColors();
  const width = 320;
  const pad = 4;
  const chartH = height - pad * 2;

  const max = useMemo(
    () => Math.max(1, ...data.map((p) => p.visitors)),
    [data],
  );

  if (data.length === 0) {
    return (
      <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
        No visitors in the selected window.
      </Text>
    );
  }

  const n = data.length;
  const gap = n > 40 ? 0.5 : n > 20 ? 1 : 2;
  const slot = (width - pad * 2) / n;
  const barW = Math.max(1, slot - gap);

  return (
    <View style={{ gap: 8 }}>
      <Svg
        width="100%"
        height={height}
        viewBox={`0 0 ${width} ${height}`}
        preserveAspectRatio="none"
      >
        <Line
          x1={pad}
          y1={height - pad}
          x2={width - pad}
          y2={height - pad}
          stroke={colors.border}
          strokeWidth={1}
        />
        {data.map((p, i) => {
          const x = pad + i * slot + (slot - barW) / 2;
          const totalH = (p.visitors / max) * chartH;
          const retH = (p.returning / max) * chartH;
          const newH = Math.max(0, totalH - retH);
          const baseY = height - pad;
          return (
            <React.Fragment key={p.d}>
              {retH > 0 ? (
                <Rect
                  x={x}
                  y={baseY - retH}
                  width={barW}
                  height={retH}
                  fill={colors.mutedForeground}
                  opacity={0.55}
                  rx={0.5}
                />
              ) : null}
              {newH > 0 ? (
                <Rect
                  x={x}
                  y={baseY - retH - newH}
                  width={barW}
                  height={newH}
                  fill={colors.primary}
                  rx={0.5}
                />
              ) : null}
            </React.Fragment>
          );
        })}
      </Svg>
      <View style={styles.legend}>
        <LegendDot color={colors.primary} label="New" />
        <LegendDot color={colors.mutedForeground} label="Returning" />
      </View>
    </View>
  );
}

function LegendDot({ color, label }: { color: string; label: string }) {
  const colors = useColors();
  return (
    <View style={styles.legendItem}>
      <View style={[styles.dot, { backgroundColor: color }]} />
      <Text style={[styles.legendText, { color: colors.mutedForeground }]}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  legend: { flexDirection: "row", gap: 16, justifyContent: "center" },
  legendItem: { flexDirection: "row", alignItems: "center", gap: 6 },
  dot: { width: 10, height: 10, borderRadius: 3 },
  legendText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
  },
});
