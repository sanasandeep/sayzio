import { Pressable, StyleSheet, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { normalizeColor, useRecentColors } from "@/lib/recentColors";

/**
 * Shared tap-to-pick color swatch row used across the block editor
 * (border color, per-side border colors, gradient stops, background
 * color) and the SettingsForm #hex fields (appearance / block theme).
 *
 * Renders the preset swatches plus the creator's recently used custom
 * colors (persisted via lib/recentColors, shown as circles after a thin
 * divider) so a typed brand hex only ever has to be typed once.
 *
 * Tapping a swatch calls `onPick(color)` — it feeds the exact same
 * state setter as the adjacent text input, so a tap flows through to
 * the saved payload identically to typing the value.
 *
 * testID pattern: `{prefix}-{color}` for preset swatches (e.g.
 * `block-border-color-swatch-#2563eb`) and `{prefix}-recent-{index}`
 * for recent custom colors.
 */

export const DEFAULT_SWATCH_COLORS = [
  "#7c3aed",
  "#2563eb",
  "#059669",
  "#dc2626",
  "#f59e0b",
  "#0f172a",
  "#ffffff",
] as const;

export function ColorSwatchRow({
  prefix,
  value,
  onPick,
  palette,
  size = 28,
}: {
  prefix: string;
  value: string;
  onPick: (color: string) => void;
  palette?: readonly string[];
  size?: number;
}) {
  const colors = useColors();
  const list = palette ?? DEFAULT_SWATCH_COLORS;
  const recents = useRecentColors();
  const presetSet = new Set(list.map((c) => normalizeColor(c)));
  // Recent custom colors that aren't already covered by a preset swatch.
  const extraRecents = recents.filter((c) => !presetSet.has(normalizeColor(c)));
  const current = typeof value === "string" ? normalizeColor(value.trim()) : "";

  const renderSwatch = (c: string, recent: boolean, idx: number) => {
    const sel = current !== "" && current === normalizeColor(c);
    return (
      <Pressable
        {...WEB_FOCUS_RING_PROPS}
        key={(recent ? "r:" : "p:") + c}
        testID={recent ? `${prefix}-recent-${idx}` : `${prefix}-${c}`}
        accessibilityRole="button"
        accessibilityLabel={(recent ? "Use recent color " : "Use color ") + c}
        onPress={() => onPick(c)}
        style={{
          width: size,
          height: size,
          borderRadius: recent ? size / 2 : 8,
          backgroundColor: c,
          borderWidth: sel ? 2 : 1,
          borderColor: sel ? colors.primary : colors.border,
        }}
      />
    );
  };

  return (
    <View style={styles.row} testID={`${prefix}-row`}>
      {list.map((c, i) => renderSwatch(c, false, i))}
      {extraRecents.length > 0 ? (
        <View
          style={[
            styles.divider,
            { backgroundColor: colors.border, height: size - 6 },
          ]}
        />
      ) : null}
      {extraRecents.map((c, i) => renderSwatch(c, true, i))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 6,
  },
  divider: { width: 1, marginHorizontal: 2, alignSelf: "center" },
});
