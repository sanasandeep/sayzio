import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Image as ExpoImage } from "expo-image";
import { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import {
  getPlatformAssets,
  type PlatformAsset,
  type PlatformAssetFolder,
} from "@/lib/api/platformAssets";

// Task #6015 — curated avatar gallery (mobile parity for the web
// avatar-gallery-picker partial). Three tabs of platform-provided
// avatars (people photos / illustrated / hand-drawn), available on
// every plan. Tapping a tile hands the asset's public CDN URL to the
// parent, which saves it through its existing profile-update flow.

const TABS: { folder: PlatformAssetFolder; label: string }[] = [
  { folder: "people-avatars", label: "Photos" },
  { folder: "stock-avatars", label: "Illustrated" },
  { folder: "hand-drawn", label: "Hand-drawn" },
];

export function AvatarGalleryPicker({
  selectedUrl,
  onSelect,
  saving = false,
}: {
  /** Currently applied avatar URL (marks the matching tile). */
  selectedUrl?: string | null;
  /** Called with the asset's public URL when a tile is tapped. */
  onSelect: (url: string, asset: PlatformAsset) => void;
  saving?: boolean;
}) {
  const colors = useColors();
  const [open, setOpen] = useState(false);
  const [tab, setTab] = useState<PlatformAssetFolder>("people-avatars");
  const [limit, setLimit] = useState(30);

  const assetsQ = useQuery({
    queryKey: ["platform-assets", tab],
    queryFn: () => getPlatformAssets(tab),
    staleTime: 10 * 60 * 1000,
    enabled: open,
  });

  const assets = assetsQ.data ?? [];

  return (
    <View style={{ gap: 8 }}>
      <Pressable
        {...WEB_FOCUS_RING_PROPS}
        testID="avatar-gallery-toggle"
        onPress={() => setOpen((o) => !o)}
        style={[
          styles.row,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <Feather name="smile" size={16} color={colors.primary} />
        <View style={{ flex: 1 }}>
          <Text style={[styles.label, { color: colors.foreground }]}>
            Avatar gallery
          </Text>
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Pick a ready-made avatar
          </Text>
        </View>
        <Feather
          name={open ? "chevron-up" : "chevron-down"}
          size={18}
          color={colors.mutedForeground}
        />
      </Pressable>

      {open ? (
        <View style={{ gap: 10 }}>
          <View style={styles.chipRow}>
            {TABS.map((t) => {
              const on = tab === t.folder;
              return (
                <Pressable
                  {...WEB_FOCUS_RING_PROPS}
                  key={t.folder}
                  onPress={() => {
                    setTab(t.folder);
                    setLimit(30);
                  }}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: on ? colors.primary : colors.card,
                      borderColor: on ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.chipText,
                      {
                        color: on
                          ? colors.primaryForeground
                          : colors.mutedForeground,
                      },
                    ]}
                  >
                    {t.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {assetsQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : assetsQ.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't load the gallery. Please try again.
            </Text>
          ) : assets.length === 0 ? (
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              No gallery avatars available yet.
            </Text>
          ) : (
            <>
              <View style={styles.grid} testID="avatar-gallery-grid">
                {assets.slice(0, limit).map((a) => {
                  const on = !!selectedUrl && selectedUrl === a.url;
                  return (
                    <Pressable
                      {...WEB_FOCUS_RING_PROPS}
                      key={a.key}
                      accessibilityLabel={a.label}
                      disabled={saving}
                      onPress={() => onSelect(a.url, a)}
                      style={[
                        styles.cell,
                        {
                          borderColor: on ? colors.primary : colors.border,
                          borderWidth: on ? 2 : 1,
                        },
                      ]}
                    >
                      <ExpoImage
                        source={{ uri: a.url }}
                        style={StyleSheet.absoluteFill}
                        contentFit="cover"
                        transition={0}
                        cachePolicy="memory-disk"
                      />
                      {on ? (
                        <View style={styles.checkBadge}>
                          <Feather name="check" size={11} color="#ffffff" />
                        </View>
                      ) : null}
                    </Pressable>
                  );
                })}
              </View>
              {assets.length > limit ? (
                <Pressable
                  {...WEB_FOCUS_RING_PROPS}
                  onPress={() => setLimit((l) => l + 30)}
                  style={[
                    styles.moreBtn,
                    { borderColor: colors.border, borderRadius: colors.radius },
                  ]}
                >
                  <Text style={[styles.chipText, { color: colors.primary }]}>
                    Show more
                  </Text>
                </Pressable>
              ) : null}
            </>
          )}
        </View>
      ) : null}
    </View>
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
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  cell: {
    width: 56,
    height: 56,
    borderRadius: 28,
    overflow: "hidden",
    alignItems: "flex-end",
    justifyContent: "flex-end",
  },
  checkBadge: {
    width: 18,
    height: 18,
    borderRadius: 9,
    margin: 2,
    backgroundColor: "rgba(37,99,235,0.9)",
    alignItems: "center",
    justifyContent: "center",
  },
  moreBtn: {
    alignSelf: "flex-start",
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderWidth: 1,
    borderStyle: "dashed",
  },
  empty: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    textAlign: "center",
    paddingVertical: 8,
  },
});
