import { useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { NfcWriteTrigger } from "@/components/NfcWriteTrigger";
import { useColors } from "@/hooks/useColors";
import type { Link } from "@/lib/api/links";
import { metaForApiType } from "@/lib/linkKinds";

export function LinkRow({
  link,
  showNfcButton,
}: {
  link: Link;
  showNfcButton?: boolean;
}) {
  const colors = useColors();
  const router = useRouter();
  const meta = metaForApiType(link.type);
  // Prefer a per-link icon if the API surfaced one (web's icon picker
  // stores FontAwesome class strings on link.settings.icon); fall back
  // to the static per-kind icon. The resolver tolerates both formats.
  const settingsIcon =
    typeof link.settings === "object" && link.settings !== null
      ? (link.settings as Record<string, unknown>).icon
      : null;
  const iconName =
    typeof settingsIcon === "string" && settingsIcon.trim().length > 0
      ? settingsIcon
      : meta.icon;

  return (
    <Pressable
      onPress={() => router.push(`/links/${link.id}/edit`)}
      style={({ pressed }) => [
        styles.row,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      <View
        style={[
          styles.iconWrap,
          { backgroundColor: colors.primary + "1c" },
        ]}
      >
        <AppIcon name={iconName} size={18} color={colors.primary} />
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <Text
          numberOfLines={1}
          style={[styles.title, { color: colors.foreground }]}
        >
          {link.title || link.alias}
        </Text>
        <Text
          numberOfLines={1}
          style={[styles.sub, { color: colors.mutedForeground }]}
        >
          {meta.label} · /{link.alias}
        </Text>
      </View>
      <View style={{ alignItems: "flex-end", gap: 2 }}>
        <Text style={[styles.clicks, { color: colors.foreground }]}>
          {link.total_clicks}
        </Text>
        <Text style={[styles.clicksLabel, { color: colors.mutedForeground }]}>
          clicks
        </Text>
      </View>
      {showNfcButton && link.short_url ? (
        <NfcWriteTrigger
          linkId={link.id}
          url={link.short_url}
          variant="icon"
        />
      ) : null}
      {!link.is_active ? (
        <View
          style={[
            styles.dot,
            { backgroundColor: colors.mutedForeground + "55" },
          ]}
        />
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  title: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  clicks: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  clicksLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 10,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  dot: { width: 8, height: 8, borderRadius: 999, marginLeft: 4 },
});
