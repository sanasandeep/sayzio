import { Feather } from "@expo/vector-icons";
import { StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

/**
 * A small "Soon" pill with a clock glyph, shown on menu entries whose
 * feature resolves to `coming_soon` from `GET /api/v1/feature-states`. Mirrors
 * the web "Coming soon" badge so the boundary is visible BEFORE the user taps
 * (the tap reroutes to the branded /coming-soon preview screen). Purely
 * presentational.
 */
export function SoonBadge({ label = "Soon" }: { label?: string }) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.pill,
        {
          backgroundColor: colors.muted,
          borderColor: colors.border,
        },
      ]}
    >
      <Feather name="clock" size={11} color={colors.mutedForeground} />
      <Text style={[styles.text, { color: colors.mutedForeground }]}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    borderWidth: 1,
  },
  text: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.2,
  },
});
