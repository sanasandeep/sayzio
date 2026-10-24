import { useQuery } from "@tanstack/react-query";
import { useCallback, useEffect, useRef, useState } from "react";
import { Platform, Pressable, StyleSheet, Text, View } from "react-native";
import { useColors } from "@/hooks/useColors";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { getBaseUrl } from "@/lib/api";
import { getCreatorPreviewUrl } from "@/lib/api/creatorProfile";
// Lazy-require so the web bundle never evaluates the native module
// (same pattern as components/MapPickerModal.tsx).
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

type PvDensity = "small" | "medium" | "large";
type PvTheme = "dark" | "light";

// Same modes + heights as the web editor's Small/Medium/Large preview
// (user/creator-profile/edit.blade.php pvSizes/pvHeights).
const DENSITIES: { key: PvDensity; label: string }[] = [
  { key: "small", label: "Small" },
  { key: "medium", label: "Medium" },
  { key: "large", label: "Large" },
];
const PV_HEIGHTS: Record<PvDensity, number> = {
  small: 440,
  medium: 620,
  large: 760,
};

/**
 * Task #5480 — live public-profile preview with the same density +
 * theme controls as the web Creator Profile editor. Loads the signed
 * /@handle?cp_preview=1 URL in a WebView (iframe on web) and posts the
 * exact same `cpLive` message ({density, theme}) the page already
 * understands. Real visitor rendering is untouched — the page only
 * honors the message under its cp_preview gate.
 */
export function ProfilePreview({ hasHandle }: { hasHandle: boolean }) {
  const colors = useColors();
  const { activeWorkspace } = useWorkspace();
  const [density, setDensity] = useState<PvDensity>("medium");
  const [theme, setTheme] = useState<PvTheme>("dark");
  const webRef = useRef<import("react-native-webview").WebView | null>(null);
  const iframeRef = useRef<HTMLIFrameElement | null>(null);
  const [loaded, setLoaded] = useState(false);

  const urlQ = useQuery({
    queryKey: ["cp-preview-url", activeWorkspace?.id],
    queryFn: getCreatorPreviewUrl,
    enabled: hasHandle,
    staleTime: 20 * 60 * 1000, // the signature lives ~30 min
    retry: false,
  });

  const previewUri = urlQ.data ? `${getBaseUrl()}${urlQ.data.url}` : null;

  const postLive = useCallback((d: PvDensity, t: PvTheme) => {
    const msg = { type: "cpLive", density: d, theme: t };
    if (Platform.OS === "web") {
      try {
        iframeRef.current?.contentWindow?.postMessage(msg, getBaseUrl());
      } catch {
        /* cross-origin in odd dev setups — preview just stays default */
      }
    } else {
      webRef.current?.injectJavaScript(
        // Post from inside the page so e.origin matches the page's own
        // origin (the listener requires that).
        `window.postMessage(${JSON.stringify(msg)}, window.location.origin); true;`,
      );
    }
  }, []);

  useEffect(() => {
    if (loaded) postLive(density, theme);
  }, [loaded, density, theme, postLive]);

  if (!hasHandle) return null;

  return (
    <View style={{ gap: 10 }}>
      <Text style={[styles.section, { color: colors.foreground }]}>
        Public profile preview
      </Text>

      <View style={styles.pvControls}>
        <View
          style={[
            styles.pvSeg,
            { borderColor: colors.border, backgroundColor: colors.card },
          ]}
        >
          {DENSITIES.map((d) => (
            <Pressable
              key={d.key}
              onPress={() => setDensity(d.key)}
              style={[
                styles.pvSegBtn,
                density === d.key && { backgroundColor: colors.primary },
              ]}
              accessibilityRole="button"
              accessibilityState={{ selected: density === d.key }}
            >
              <Text
                style={[
                  styles.pvSegLabel,
                  {
                    color: density === d.key ? "#fff" : colors.mutedForeground,
                  },
                ]}
              >
                {d.label}
              </Text>
            </Pressable>
          ))}
        </View>

        <Pressable
          onPress={() => setTheme((t) => (t === "dark" ? "light" : "dark"))}
          style={[
            styles.pvThemeBtn,
            { borderColor: colors.border, backgroundColor: colors.card },
          ]}
          accessibilityRole="button"
        >
          <Text style={[styles.pvSegLabel, { color: colors.foreground }]}>
            {theme === "dark" ? "🌙 Dark" : "☀️ Light"}
          </Text>
        </Pressable>
      </View>

      {urlQ.isError ? (
        <Text style={[styles.tHint, { color: colors.mutedForeground }]}>
          Couldn&apos;t load the preview right now.
        </Text>
      ) : previewUri ? (
        <View
          style={[
            styles.pvFrameWrap,
            {
              borderColor: colors.border,
              height: PV_HEIGHTS[density],
            },
          ]}
        >
          {Platform.OS === "web" ? (
            <iframe
              ref={iframeRef}
              src={previewUri}
              title="Profile preview"
              onLoad={() => setLoaded(true)}
              style={{ border: 0, width: "100%", height: "100%" }}
            />
          ) : WebView ? (
            <WebView
              ref={webRef}
              source={{ uri: previewUri }}
              onLoadEnd={() => setLoaded(true)}
              // Keep the preview inert-ish: no new windows.
              setSupportMultipleWindows={false}
              style={{ flex: 1 }}
            />
          ) : null}
        </View>
      ) : (
        <Text style={[styles.tHint, { color: colors.mutedForeground }]}>
          Loading preview…
        </Text>
      )}
      <Text style={[styles.tHint, { color: colors.mutedForeground }]}>
        Preview only: visitors always see your published profile at its normal
        size and theme.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
    borderWidth: 1,
  },
  tLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  tHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
  section: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 13,
    textTransform: "uppercase",
    letterSpacing: 0.6,
    marginTop: 12,
  },
  pvControls: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    flexWrap: "wrap",
  },
  pvSeg: {
    flexDirection: "row",
    borderWidth: 1,
    borderRadius: 10,
    overflow: "hidden",
  },
  pvSegBtn: {
    paddingVertical: 8,
    paddingHorizontal: 14,
  },
  pvSegLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  pvThemeBtn: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderWidth: 1,
    borderRadius: 10,
  },
  pvFrameWrap: {
    borderWidth: 1,
    borderRadius: 14,
    overflow: "hidden",
  },
});
