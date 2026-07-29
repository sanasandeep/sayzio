import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
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
import { getLink, updateLink } from "@/lib/api/links";
import { getPlatformAssets } from "@/lib/api/platformAssets";

// Task #6015 — curated background-image gallery (mobile parity for the
// web biolink background IMAGE gallery). Lists the platform-provided
// `biolink-backgrounds` S3 folder (every plan) and applies a pick by
// saving `settings.biolink.background_type = 'image'` +
// `background_image = <public URL>` through the REST link-update
// endpoint, with the same optimistic cache patch as BgPresetPicker so
// the Appearance preview flips immediately.

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

export function BgImageGalleryPicker({ linkId }: { linkId: number }) {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [limit, setLimit] = useState(24);
  const [pendingUrl, setPendingUrl] = useState<string | null>(null);

  const linkQ = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const assetsQ = useQuery({
    queryKey: ["platform-assets", "biolink-backgrounds"],
    queryFn: () => getPlatformAssets("biolink-backgrounds"),
    staleTime: 10 * 60 * 1000,
    enabled: open,
  });

  const biolink = isRecord(linkQ.data?.settings)
    ? isRecord((linkQ.data.settings as Record<string, unknown>).biolink)
      ? ((linkQ.data.settings as Record<string, unknown>)
          .biolink as Record<string, unknown>)
      : {}
    : {};
  const imageActive = biolink.background_type === "image";
  const selectedUrl =
    imageActive && typeof biolink.background_image === "string"
      ? biolink.background_image
      : "";

  const save = useMutation({
    mutationFn: (url: string) =>
      updateLink(linkId, {
        settings: {
          biolink: { background_type: "image", background_image: url },
        },
      }),
    onMutate: (url: string) => {
      qc.setQueryData(["link", linkId], (prev: unknown) => {
        if (!isRecord(prev)) return prev;
        const settings = isRecord(prev.settings) ? prev.settings : {};
        const biolink = isRecord(settings.biolink) ? settings.biolink : {};
        return {
          ...prev,
          settings: {
            ...settings,
            biolink: {
              ...biolink,
              background_type: "image",
              background_image: url,
            },
          },
        };
      });
    },
    onSettled: () => {
      setPendingUrl(null);
      qc.invalidateQueries({ queryKey: ["link", linkId] });
    },
  });

  const assets = assetsQ.data ?? [];

  return (
    <View style={{ gap: 8 }}>
      <Pressable
        {...WEB_FOCUS_RING_PROPS}
        testID="bg-gallery-toggle"
        onPress={() => setOpen((o) => !o)}
        style={[
          styles.row,
          {
            backgroundColor: colors.card,
            borderColor: imageActive && selectedUrl ? colors.primary : colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <Feather name="image" size={16} color={colors.primary} />
        <View style={{ flex: 1 }}>
          <Text style={[styles.label, { color: colors.foreground }]}>
            Background gallery
          </Text>
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Curated background photos, free on every plan
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
          {assetsQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : assetsQ.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't load the gallery. Please try again.
            </Text>
          ) : assets.length === 0 ? (
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              No gallery backgrounds available yet.
            </Text>
          ) : (
            <>
              <View style={styles.grid} testID="bg-gallery-grid">
                {assets.slice(0, limit).map((a) => {
                  const on = !!selectedUrl && selectedUrl === a.url;
                  const saving = pendingUrl === a.url && save.isPending;
                  return (
                    <Pressable
                      {...WEB_FOCUS_RING_PROPS}
                      key={a.key}
                      accessibilityLabel={a.label}
                      disabled={save.isPending}
                      onPress={() => {
                        if (on) return;
                        setPendingUrl(a.url);
                        save.mutate(a.url);
                      }}
                      style={[
                        styles.cell,
                        {
                          borderColor: on ? colors.primary : colors.border,
                          borderWidth: on ? 2 : 1,
                          borderRadius: colors.radius,
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
                      {saving ? (
                        <ActivityIndicator size="small" color="#ffffff" />
                      ) : on ? (
                        <View style={styles.checkBadge}>
                          <Feather name="check" size={12} color="#ffffff" />
                        </View>
                      ) : null}
                    </Pressable>
                  );
                })}
              </View>
              {assets.length > limit ? (
                <Pressable
                  {...WEB_FOCUS_RING_PROPS}
                  onPress={() => setLimit((l) => l + 24)}
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

          {save.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't apply the background.{" "}
              {(save.error as Error | null)?.message ?? "Please try again."}
            </Text>
          ) : null}
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
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  cell: {
    width: "30.5%",
    aspectRatio: 9 / 14,
    overflow: "hidden",
    alignItems: "center",
    justifyContent: "center",
  },
  checkBadge: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: "rgba(0,0,0,0.45)",
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
  chipText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  empty: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    textAlign: "center",
    paddingVertical: 8,
  },
});
