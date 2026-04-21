import { Feather } from "@expo/vector-icons";
import { StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

export function StatTile({
  label,
  value,
  icon,
  hint,
}: {
  label: string;
  value: string | number;
  icon?: keyof typeof Feather.glyphMap;
  hint?: string;
}) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.tile,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      {icon ? (
        <View
          style={[
            styles.iconWrap,
            { backgroundColor: colors.primary + "1c" },
          ]}
        >
          <Feather name={icon} size={16} color={colors.primary} />
        </View>
      ) : null}
      <Text style={[styles.value, { color: colors.foreground }]}>{value}</Text>
      <Text style={[styles.label, { color: colors.mutedForeground }]}>
        {label}
      </Text>
      {hint ? (
        <Text style={[styles.hint, { color: colors.mutedForeground }]}>
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  tile: {
    flex: 1,
    borderWidth: 1,
    padding: 14,
    gap: 4,
    minWidth: 140,
  },
  iconWrap: {
    width: 28,
    height: 28,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  value: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 24 },
  label: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, marginTop: 2 },
});
