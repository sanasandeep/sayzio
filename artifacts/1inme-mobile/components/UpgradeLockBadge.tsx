import { Feather } from "@expo/vector-icons";
import { StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

/**
 * A small "Upgrade" pill with a lock glyph, shown on plan-gated entry
 * points so the boundary is visible BEFORE the user taps. Purely
 * presentational — the tap handler that routes to /upgrade lives with the
 * surface that renders this.
 */
export function UpgradeLockBadge({ label = "Upgrade" }: { label?: string }) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.pill,
        { backgroundColor: colors.primary + "1f", borderColor: colors.primary + "55" },
      ]}
    >
      <Feather name="lock" size={11} color={colors.primary} />
      <Text style={[styles.text, { color: colors.primary }]}>{label}</Text>
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
