import { LinearGradient } from "expo-linear-gradient";
import { StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

export function BrandWordmark({ size = 36 }: { size?: number }) {
  const colors = useColors();
  return (
    <View style={styles.row}>
      <LinearGradient
        colors={[colors.primary, colors.accent]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={[
          styles.badge,
          { width: size * 1.05, height: size * 1.05, borderRadius: size * 0.28 },
        ]}
      >
        <Text
          style={[
            styles.badgeText,
            { fontSize: size * 0.62, color: colors.primaryForeground },
          ]}
        >
          1
        </Text>
      </LinearGradient>
      <Text
        style={[
          styles.word,
          { color: colors.foreground, fontSize: size * 0.7 },
        ]}
      >
        INME
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: 10 },
  badge: { alignItems: "center", justifyContent: "center" },
  badgeText: {
    fontFamily: "SpaceGrotesk_700Bold",
    includeFontPadding: false,
    marginTop: -2,
  },
  word: {
    fontFamily: "SpaceGrotesk_700Bold",
    letterSpacing: 4,
  },
});
