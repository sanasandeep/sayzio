import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import * as Linking from "expo-linking";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useRef } from "react";
import {
  ActivityIndicator,
  Image,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { useColors } from "@/hooks/useColors";
import {
  type BiolinkBlock,
  type BiolinkPayload,
  getBiolink,
  trackBiolinkBlockTap,
  trackBiolinkVisit,
} from "@/lib/api/biolinks";

function pickStr(s: Record<string, unknown> | null, ...keys: string[]): string | null {
  if (!s) return null;
  for (const k of keys) {
    const v = s[k];
    if (typeof v === "string" && v.trim() !== "") return v.trim();
  }
  return null;
}

// Block link URLs come from creator-defined block settings, which we treat as
// untrusted: only allow http/https and tel/mailto/sms so a malicious entry
// can't fire `javascript:` or `intent:` schemes from a tap.
function isSafeUrl(u: string): boolean {
  try {
    const url = new URL(u);
    return ["http:", "https:", "tel:", "mailto:", "sms:"].includes(url.protocol);
  } catch {
    return false;
  }
}
function openSafe(u: string, router: ReturnType<typeof useRouter>) {
  if (!isSafeUrl(u)) return;
  // `tel:` links open the in-app dialer (and onward to the active-call
  // screen) instead of handing off to the device's native phone app —
  // see task #395. Other safe schemes keep their default OS behaviour.
  if (u.toLowerCase().startsWith("tel:")) {
    let raw = u.slice(4);
    try {
      raw = decodeURIComponent(raw);
    } catch {
      /* leave as-is — the dialer accepts any string */
    }
    const number = raw.trim();
    if (!number) return;
    router.push({
      pathname: "/dialer",
      params: { prefill: number, autoDial: "1" },
    });
    return;
  }
  void Linking.openURL(u);
}

function BlockView({ block, alias }: { block: BiolinkBlock; alias: string }) {
  const colors = useColors();
  const router = useRouter();
  const s = block.settings ?? {};
  const t = block.type;

  // Fire the analytics ping first (best-effort, non-blocking) so the tap
  // is counted even on slow networks where Linking.openURL hands off
  // immediately.
  const handleTap = (url: string) => {
    trackBiolinkBlockTap(alias, block.id, url);
    openSafe(url, router);
  };

  // Generic link/button block
  if (
    t === "link" ||
    t === "button" ||
    t === "url" ||
    t === "social" ||
    t === "cta"
  ) {
    const url = pickStr(s, "url", "link", "destination_url", "href");
    const label =
      pickStr(s, "title", "label", "text", "button_text", "name") ??
      url ??
      "Open";
    if (!url) return null;
    if (!isSafeUrl(url)) return null;
    return (
      <Pressable
        onPress={() => handleTap(url)}
        style={[
          styles.btn,
          { backgroundColor: colors.primary, borderColor: colors.primary },
        ]}
      >
        <Text style={[styles.btnLabel, { color: "#fff" }]} numberOfLines={2}>
          {label}
        </Text>
      </Pressable>
    );
  }

  if (t === "heading" || t === "title") {
    const text = pickStr(s, "text", "title", "heading");
    if (!text) return null;
    return (
      <Text style={[styles.heading, { color: colors.foreground }]}>{text}</Text>
    );
  }

  if (t === "text" || t === "paragraph" || t === "bio") {
    const text = pickStr(s, "text", "content", "body", "bio");
    if (!text) return null;
    return (
      <Text style={[styles.body, { color: colors.foreground }]}>{text}</Text>
    );
  }

  if (t === "image" || t === "photo" || t === "banner") {
    const url = pickStr(s, "image", "image_url", "url", "src");
    if (!url) return null;
    return (
      <Image source={{ uri: url }} style={styles.image} resizeMode="cover" />
    );
  }

  if (t === "spacer" || t === "divider") {
    return (
      <View
        style={{
          height: t === "spacer" ? 12 : 1,
          backgroundColor: colors.border,
          marginVertical: 6,
        }}
      />
    );
  }

  // Fallback: try to render a button if we recognise a URL-ish setting,
  // otherwise nothing — we never want to show raw JSON to the user.
  const fallbackUrl = pickStr(s, "url", "link", "href");
  if (fallbackUrl && isSafeUrl(fallbackUrl)) {
    return (
      <Pressable
        onPress={() => handleTap(fallbackUrl)}
        style={[styles.btn, { backgroundColor: colors.card, borderColor: colors.border }]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground }]}>
          {pickStr(s, "title", "label", "text") ?? fallbackUrl}
        </Text>
      </Pressable>
    );
  }
  return null;
}

export default function BiolinkViewer() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { handle } = useLocalSearchParams<{ handle: string }>();
  const alias = String(handle ?? "");
  const webTop = Platform.OS === "web" ? 67 : 0;

  const q = useQuery<BiolinkPayload>({
    queryKey: ["biolink", alias],
    queryFn: () => getBiolink(alias),
    enabled: !!alias,
  });

  // Fire one page-visit ping per successful biolink load, mirroring the
  // web's RedirectController::track() so in-app opens are counted in the
  // creator's visit analytics. Best-effort and non-blocking; we only ping
  // once per (alias, mount) pair to avoid double-counting on re-renders.
  const visitedRef = useRef<string | null>(null);
  useEffect(() => {
    if (!q.data || !alias) return;
    if (visitedRef.current === alias) return;
    visitedRef.current = alias;
    trackBiolinkVisit(alias);
  }, [q.data, alias]);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: false }} />
      <View
        style={[
          styles.topBar,
          { paddingTop: insets.top + 12 + webTop, paddingHorizontal: 20 },
        ]}
      >
        <Pressable onPress={() => router.back()} hitSlop={12}>
          <Feather name="x" size={26} color={colors.foreground} />
        </Pressable>
        <BrandWordmark size={22} />
        <View style={{ width: 26 }} />
      </View>

      {q.isLoading && (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      )}

      {q.error && (
        <View style={styles.center}>
          <Feather name="alert-circle" size={36} color={colors.mutedForeground} />
          <Text style={[styles.note, { color: colors.foreground }]}>
            We couldn&apos;t open this profile.
          </Text>
          <Text style={[styles.note, { color: colors.mutedForeground }]}>
            {(q.error as Error).message}
          </Text>
        </View>
      )}

      {q.data && (
        <ScrollView contentContainerStyle={styles.content}>
          {q.data.owner.avatar ? (
            <Image
              source={{ uri: q.data.owner.avatar }}
              style={[styles.avatar, { borderColor: colors.border, borderRadius: 999 }]}
            />
          ) : (
            <View
              style={[
                styles.avatar,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: 999,
                  alignItems: "center",
                  justifyContent: "center",
                },
              ]}
            >
              <Feather name="user" size={48} color={colors.mutedForeground} />
            </View>
          )}
          <Text style={[styles.handle, { color: colors.foreground }]}>
            {q.data.owner.name ?? `@${q.data.owner.handle ?? alias}`}
          </Text>
          {q.data.owner.handle && q.data.owner.name ? (
            <Text style={[styles.subhandle, { color: colors.mutedForeground }]}>
              @{q.data.owner.handle}
            </Text>
          ) : null}
          {q.data.owner.bio ? (
            <Text style={[styles.bio, { color: colors.foreground }]}>
              {q.data.owner.bio}
            </Text>
          ) : null}
          <View style={styles.blocks}>
            {q.data.blocks
              .filter((b) => !b.parent_id)
              .map((b) => (
                <BlockView key={b.id} block={b} alias={alias} />
              ))}
          </View>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  topBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    padding: 32,
  },
  content: {
    alignItems: "center",
    paddingHorizontal: 24,
    paddingBottom: 64,
    gap: 14,
  },
  avatar: {
    width: 112,
    height: 112,
    borderWidth: 1,
    marginTop: 12,
  },
  handle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
    textAlign: "center",
  },
  subhandle: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    marginTop: -8,
  },
  bio: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    textAlign: "center",
    lineHeight: 22,
    maxWidth: 360,
    marginBottom: 8,
  },
  note: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    textAlign: "center",
  },
  blocks: { width: "100%", maxWidth: 480, gap: 10, marginTop: 12 },
  btn: {
    width: "100%",
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: "center",
  },
  btnLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    textAlign: "center",
  },
  heading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
    textAlign: "center",
    marginTop: 8,
    width: "100%",
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
    width: "100%",
    textAlign: "center",
  },
  image: {
    width: "100%",
    aspectRatio: 16 / 9,
    borderRadius: 14,
  },
});
