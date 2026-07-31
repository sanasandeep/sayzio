import { useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { useColors } from "@/hooks/useColors";
import type { Link } from "@/lib/api/links";
import { metaForApiType } from "@/lib/linkKinds";

/**
 * Finder-style icon tile for the Links grid view — mobile parity for the
 * web My Links grid. The tile's icon square is tinted with the colour of
 * the folder (project) the link lives in, falling back to the app primary
 * when the link isn't in a folder (or the folder has no valid colour).
 */
export function LinkGridTile({ link }: { link: Link }) {
  const colors = useColors();
  const router = useRouter();
  const meta = metaForApiType(link.type);

  // Same per-link icon preference as LinkRow: web's icon picker stores a
  // FontAwesome class string on link.settings.icon.
  const settingsIcon =
    typeof link.settings === "object" && link.settings !== null
      ? (link.settings as Record<string, unknown>).icon
      : null;
  const iconName =
    typeof settingsIcon === "string" && settingsIcon.trim().length > 0
      ? settingsIcon
      : meta.icon;

  const rawColor = link.project?.color ?? null;
  const tint =
    typeof rawColor === "string" && /^#[0-9a-fA-F]{6}$/.test(rawColor)
      ? rawColor
      : colors.primary;

  return (
    <Pressable
      onPress={() => router.push(`/links/${link.id}/edit`)}
      style={({ pressed }) => [
        styles.tile,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      {!link.is_active ? (
        <View style={[styles.inactiveDot, { backgroundColor: colors.destructive }]} />
      ) : null}
      <View
        style={[
          styles.iconWrap,
          { backgroundColor: tint + "1f", borderColor: tint + "40" },
        ]}
      >
        <AppIcon name={iconName} size={22} color={tint} />
      </View>
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
      <View style={styles.metaRow}>
        {link.project ? (
          <>
            <View style={[styles.folderDot, { backgroundColor: tint }]} />
            <Text
              numberOfLines={1}
              style={[styles.metaText, { color: colors.mutedForeground, maxWidth: 70 }]}
            >
              {link.project.name}
            </Text>
            <Text style={[styles.metaText, { color: colors.mutedForeground }]}>·</Text>
          </>
        ) : null}
        <Text style={[styles.metaText, { color: colors.mutedForeground }]}>
          {link.total_clicks} clicks
        </Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  tile: {
    flex: 1,
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
    gap: 2,
  },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 16,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 8,
  },
  title: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    maxWidth: "100%",
  },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, maxWidth: "100%" },
  metaRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    marginTop: 4,
  },
  metaText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 10 },
  folderDot: { width: 6, height: 6, borderRadius: 999 },
  inactiveDot: {
    position: "absolute",
    top: 8,
    right: 8,
    width: 7,
    height: 7,
    borderRadius: 999,
  },
});
