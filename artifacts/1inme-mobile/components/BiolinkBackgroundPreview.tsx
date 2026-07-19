import { useQuery } from "@tanstack/react-query";
import { Image } from "expo-image";
import { LinearGradient } from "expo-linear-gradient";
import { StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import { getBgPresets } from "@/lib/api/bgPresets";
import { getLink } from "@/lib/api/links";

// Live preview of the biolink page background on the mobile Appearance
// screen. Renders a tall phone-shaped card with the currently selected
// background behind a mock of the page (avatar, title, faux block bars),
// so users see roughly what visitors will see without leaving the app.
//
// Background resolution mirrors the public renderer's priority:
//   - settings.biolink.background_type === 'preset' → gradient built from
//     the preset's `colors` array (from /api/v1/bg-presets; RN can't render
//     the raw CSS string, so this is a color-faithful approximation)
//   - settings.appearance.background_image → the image, cover-fit
//   - settings.appearance.background_color → solid color
//   - otherwise a neutral default.
//
// It reads the same ["link", linkId] query the preset picker optimistically
// updates on tap, so the preview changes instantly when a swatch is chosen.

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

function str(v: unknown): string {
  return typeof v === "string" ? v.trim() : "";
}

function gradientStops(colors: string[]): [string, string, ...string[]] {
  if (colors.length >= 2) return colors as [string, string, ...string[]];
  const only = colors[0] ?? "#3d3654";
  return [only, only];
}

export function BiolinkBackgroundPreview({ linkId }: { linkId: number }) {
  const colors = useColors();

  const linkQ = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const settings = isRecord(linkQ.data?.settings)
    ? (linkQ.data.settings as Record<string, unknown>)
    : {};
  const biolink = isRecord(settings.biolink)
    ? (settings.biolink as Record<string, unknown>)
    : {};
  const appearance = isRecord(settings.appearance)
    ? (settings.appearance as Record<string, unknown>)
    : {};

  const presetActive = biolink.background_type === "preset";
  const presetKey = presetActive ? str(biolink.bg_preset_key) : "";

  // Catalog is needed to resolve the preset key into color stops; the
  // query key/staleTime match the picker's so the cache is shared.
  const catalogQ = useQuery({
    queryKey: ["bg-presets"],
    queryFn: getBgPresets,
    staleTime: 60 * 60 * 1000,
    enabled: !!presetKey,
  });

  const preset = presetKey
    ? catalogQ.data?.presets.find((p) => p.key === presetKey)
    : undefined;

  const bgImage = !presetKey ? str(appearance.background_image) : "";
  const bgColor = str(appearance.background_color);
  const textColor = str(appearance.text_color) || "#ffffff";

  const title = linkQ.data?.title || linkQ.data?.alias || "Your page";

  const mockContent = (
    <View style={styles.mock}>
      <View
        style={[styles.avatar, { borderColor: "rgba(255,255,255,0.65)" }]}
      >
        <Text style={styles.avatarInitial}>
          {title.trim().charAt(0).toUpperCase() || "1"}
        </Text>
      </View>
      <Text numberOfLines={1} style={[styles.mockTitle, { color: textColor }]}>
        {title}
      </Text>
      {[0.92, 0.8, 0.68].map((opacity, i) => (
        <View
          key={i}
          style={[
            styles.mockBlock,
            { backgroundColor: "rgba(255,255,255,0.22)", opacity },
          ]}
        />
      ))}
    </View>
  );

  const caption = preset
    ? `Preset · ${preset.label}`
    : presetKey
      ? "Preset background"
      : bgImage
        ? "Background image"
        : bgColor
          ? "Custom color"
          : "Default background";

  return (
    <View style={{ gap: 8 }} testID="bg-preview">
      <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
        Preview
      </Text>
      <View
        style={[
          styles.frame,
          { borderColor: colors.border, borderRadius: colors.radius + 6 },
        ]}
      >
        {preset || (!presetKey && !bgImage) ? (
          <LinearGradient
            colors={gradientStops(
              preset ? preset.colors : bgColor ? [bgColor] : ["#3d3654"],
            )}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.canvas}
          >
            {mockContent}
          </LinearGradient>
        ) : bgImage ? (
          <View style={styles.canvas}>
            <Image
              source={{ uri: bgImage }}
              style={StyleSheet.absoluteFill}
              contentFit="cover"
            />
            {mockContent}
          </View>
        ) : (
          // Preset selected but catalog still loading/errored: neutral fill.
          <View style={[styles.canvas, { backgroundColor: "#3d3654" }]}>
            {mockContent}
          </View>
        )}
      </View>
      <Text style={[styles.caption, { color: colors.mutedForeground }]}>
        {caption}. This is how your page looks to visitors.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  frame: {
    borderWidth: 1,
    overflow: "hidden",
    alignSelf: "center",
    width: "68%",
  },
  canvas: {
    width: "100%",
    aspectRatio: 9 / 16,
    alignItems: "center",
    justifyContent: "flex-start",
  },
  mock: {
    width: "100%",
    alignItems: "center",
    paddingTop: 28,
    paddingHorizontal: 18,
    gap: 10,
  },
  avatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    borderWidth: 2,
    backgroundColor: "rgba(0,0,0,0.28)",
    alignItems: "center",
    justifyContent: "center",
  },
  avatarInitial: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 20,
    color: "#ffffff",
  },
  mockTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    maxWidth: "90%",
  },
  mockBlock: {
    width: "100%",
    height: 34,
    borderRadius: 10,
    marginTop: 2,
  },
  caption: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
  },
});
