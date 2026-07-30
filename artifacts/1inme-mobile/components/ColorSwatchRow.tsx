import { Pressable, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";

/**
 * Shared tap-to-pick color swatch row used across the block editor
 * (border color, per-side border colors, gradient stops, background
 * color) and the SettingsForm #hex fields (appearance / block theme).
 *
 * Tapping a swatch calls `onPick(color)` — it feeds the exact same
 * state setter as the adjacent text input, so a tap flows through to
 * the saved payload identically to typing the value.
 *
 * testID pattern: `{prefix}-{color}` (e.g. `block-border-color-swatch-#2563eb`).
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
}: {
  prefix: string;
  value: string;
  onPick: (color: string) => void;
  palette?: readonly string[];
}) {
  const colors = useColors();
  const list = palette ?? DEFAULT_SWATCH_COLORS;
  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
      {list.map((c) => {
        const sel =
          typeof value === "string" &&
          value.trim().toLowerCase() === c.toLowerCase();
        return (
          <Pressable
            {...WEB_FOCUS_RING_PROPS}
            key={c}
            testID={`${prefix}-${c}`}
            accessibilityRole="button"
            accessibilityLabel={`Use color ${c}`}
            onPress={() => onPick(c)}
            style={{
              width: 28,
              height: 28,
              borderRadius: 8,
              backgroundColor: c,
              borderWidth: sel ? 2 : 1,
              borderColor: sel ? colors.primary : colors.border,
            }}
          />
        );
      })}
    </View>
  );
}
