import { Feather } from "@expo/vector-icons";
import { StyleSheet, Text, View } from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { useColors } from "@/hooks/useColors";

export function EmptyState({
  icon = "inbox",
  title,
  body,
  action,
}: {
  // Accept either a native Feather name or a FontAwesome class string
  // returned by the API (e.g. "fa-user", "fas fa-save"). Unknown icons
  // resolve to a sensible fallback instead of vanishing.
  icon?: keyof typeof Feather.glyphMap | string;
  title: string;
  body?: string;
  action?: React.ReactNode;
}) {
  const colors = useColors();
  return (
    <View style={styles.wrap}>
      <View
        style={[
          styles.iconWrap,
          { backgroundColor: colors.primary + "1c" },
        ]}
      >
        <AppIcon name={icon} size={28} color={colors.primary} />
      </View>
      <Text style={[styles.title, { color: colors.foreground }]}>{title}</Text>
      {body ? (
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          {body}
        </Text>
      ) : null}
      {action ? <View style={{ marginTop: 12 }}>{action}</View> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 48,
    paddingHorizontal: 24,
    gap: 8,
  },
  iconWrap: {
    width: 64,
    height: 64,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 8,
  },
  title: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 18,
    textAlign: "center",
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    textAlign: "center",
    lineHeight: 20,
  },
});
