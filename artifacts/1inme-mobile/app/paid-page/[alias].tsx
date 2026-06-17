import { Feather } from "@expo/vector-icons";
import {
  useInfiniteQuery,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { Image } from "expo-image";
import { LinearGradient } from "expo-linear-gradient";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { PostCard, type FeedTheme } from "@/components/CreatorFeed";
import { useColors } from "@/hooks/useColors";
import type { ApiError } from "@/lib/api";
import { paidPage } from "@/lib/api/paidPage";

export default function PaidPageScreen() {
  const params = useLocalSearchParams<{ alias: string }>();
  const alias = (Array.isArray(params.alias) ? params.alias[0] : params.alias ?? "")
    .replace(/^@/, "");
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();

  const pageQ = useQuery({
    queryKey: ["paid-page", alias],
    queryFn: () => paidPage.show(alias),
    enabled: !!alias,
    retry: false,
  });

  const handle = pageQ.data?.page.handle ?? null;

  const feedQ = useInfiniteQuery({
    queryKey: ["paid-page-feed", alias],
    queryFn: ({ pageParam = 1 }) => paidPage.feed(alias, pageParam),
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
    initialPageParam: 1,
    enabled: !!alias && !!pageQ.data,
  });

  // ── Loading ──────────────────────────────────────────────────────
  if (pageQ.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: false }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  // ── Error / gated ────────────────────────────────────────────────
  if (pageQ.isError || !pageQ.data) {
    const err = pageQ.error as unknown as ApiError | undefined;
    const status = err?.status ?? 0;
    const gated = status === 401 || status === 403;
    return (
      <View style={[styles.center, { backgroundColor: colors.background, padding: 28 }]}>
        <Stack.Screen options={{ headerShown: false }} />
        <Feather
          name={gated ? "lock" : "alert-circle"}
          size={36}
          color={colors.mutedForeground}
        />
        <Text
          style={{
            color: colors.foreground,
            marginTop: 12,
            fontSize: 16,
            fontWeight: "700",
            textAlign: "center",
          }}
        >
          {gated ? "This page is private" : "Page not available"}
        </Text>
        <Text
          style={{
            color: colors.mutedForeground,
            marginTop: 6,
            textAlign: "center",
          }}
        >
          {err?.message ||
            "We couldn't open this page. It may have been removed."}
        </Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 16 }}>
          <Text style={{ color: colors.primary, fontWeight: "700" }}>
            Go back
          </Text>
        </Pressable>
      </View>
    );
  }

  const { page, template, profile, reactions_catalog } = pageQ.data;
  const posts = feedQ.data?.pages.flatMap((p) => p.items) ?? [];

  // The chosen template drives every surface below — the page background
  // gradient, the hero, the cards, and the reactions accent — so the bold
  // per-link look matches the web `public/paid-page.blade.php` renderer.
  const feedTheme: FeedTheme = {
    cardBg: template.card_bg,
    cardText: template.card_text,
    mutedText: rgba(template.card_text, 0.6),
    accent: template.accent,
    border: rgba(template.card_text, 0.12),
    radius: template.radius,
    subtleBg: rgba(template.card_text, 0.05),
  };

  const initials = profile.name
    .trim()
    .split(/\s+/)
    .map((s) => s[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <View style={{ flex: 1, backgroundColor: template.page_colors[0] }}>
      <Stack.Screen options={{ headerShown: false }} />
      <LinearGradient
        colors={template.page_colors as [string, string, ...string[]]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={StyleSheet.absoluteFill}
      />

      {/* Floating close button over the themed background. */}
      <Pressable
        onPress={() => router.back()}
        hitSlop={12}
        style={[
          styles.closeBtn,
          { top: insets.top + 10, backgroundColor: rgba(template.text, 0.14) },
        ]}
        accessibilityLabel="Close"
      >
        <Feather name="x" size={22} color={template.text} />
      </Pressable>

      <FlatList
        data={posts}
        keyExtractor={(p) => String(p.id)}
        contentContainerStyle={{ paddingBottom: 48 }}
        refreshControl={
          <RefreshControl
            refreshing={pageQ.isFetching || feedQ.isFetching}
            onRefresh={() => {
              pageQ.refetch();
              feedQ.refetch();
            }}
            tintColor={template.text}
          />
        }
        onEndReachedThreshold={0.4}
        onEndReached={() => {
          if (feedQ.hasNextPage && !feedQ.isFetchingNextPage)
            feedQ.fetchNextPage();
        }}
        ListHeaderComponent={
          <View>
            {/* Hero */}
            <LinearGradient
              colors={template.hero_colors as [string, string, ...string[]]}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={[styles.hero, { paddingTop: insets.top + 64 }]}
            >
              <View
                style={[
                  styles.avatar,
                  {
                    borderColor: rgba(template.text, 0.5),
                    backgroundColor: template.accent,
                  },
                ]}
              >
                {profile.avatar ? (
                  <Image
                    source={{ uri: profile.avatar }}
                    style={StyleSheet.absoluteFill}
                    contentFit="cover"
                  />
                ) : (
                  <Text style={styles.avatarInitials}>{initials}</Text>
                )}
              </View>

              <Text style={[styles.title, { color: template.text }]}>
                {page.title}
              </Text>
              {profile.handle ? (
                <Text
                  style={{
                    color: rgba(template.text, 0.7),
                    marginTop: 2,
                    fontSize: 13,
                  }}
                >
                  @{profile.handle}
                </Text>
              ) : null}
              {page.description ? (
                <Text
                  style={[styles.desc, { color: rgba(template.text, 0.82) }]}
                >
                  {page.description}
                </Text>
              ) : null}

              <View style={styles.statRow}>
                <Stat
                  label="Followers"
                  value={profile.followers_count}
                  color={template.text}
                  muted={rgba(template.text, 0.6)}
                />
                <View
                  style={[
                    styles.statSep,
                    { backgroundColor: rgba(template.text, 0.25) },
                  ]}
                />
                <Stat
                  label="Posts"
                  value={profile.posts_count}
                  color={template.text}
                  muted={rgba(template.text, 0.6)}
                />
              </View>

              {page.is_owner ? (
                <View
                  style={[
                    styles.ownerPill,
                    { backgroundColor: rgba(template.text, 0.16) },
                  ]}
                >
                  <Feather name="eye" size={12} color={template.text} />
                  <Text
                    style={{ color: template.text, fontSize: 12, fontWeight: "700" }}
                  >
                    Owner preview · {template.name}
                  </Text>
                </View>
              ) : null}
            </LinearGradient>
          </View>
        }
        renderItem={({ item }) => (
          <PostCard
            post={item}
            handle={handle ?? ""}
            reactionsCatalog={reactions_catalog}
            feedQueryKey={["paid-page-feed", alias]}
            theme={feedTheme}
          />
        )}
        ListEmptyComponent={
          !feedQ.isLoading ? (
            <View style={{ paddingHorizontal: 16, paddingTop: 16 }}>
              <Text
                style={{ color: rgba(template.text, 0.7), textAlign: "center" }}
              >
                No posts yet.
              </Text>
            </View>
          ) : null
        }
        ListFooterComponent={
          feedQ.isFetchingNextPage ? (
            <ActivityIndicator
              color={template.text}
              style={{ marginVertical: 18 }}
            />
          ) : null
        }
      />
    </View>
  );
}

function Stat({
  label,
  value,
  color,
  muted,
}: {
  label: string;
  value: number;
  color: string;
  muted: string;
}) {
  return (
    <View style={{ alignItems: "center", paddingHorizontal: 16 }}>
      <Text style={{ color, fontSize: 18, fontWeight: "800" }}>
        {Intl.NumberFormat().format(value)}
      </Text>
      <Text style={{ color: muted, fontSize: 11, marginTop: 2 }}>{label}</Text>
    </View>
  );
}

/**
 * Build an `rgba()` string from a hex colour (#rgb / #rrggbb) at the given
 * alpha. If the input isn't a parseable hex (e.g. already an rgba string),
 * it's returned unchanged so template tokens that already carry alpha keep
 * working.
 */
function rgba(hex: string, alpha: number): string {
  const m = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.exec(hex.trim());
  if (!m) return hex;
  let h = m[1];
  if (h.length === 3) {
    h = h
      .split("")
      .map((ch) => ch + ch)
      .join("");
  }
  const r = parseInt(h.slice(0, 2), 16);
  const g = parseInt(h.slice(2, 4), 16);
  const b = parseInt(h.slice(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  closeBtn: {
    position: "absolute",
    right: 16,
    zIndex: 10,
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: "center",
    justifyContent: "center",
  },
  hero: {
    paddingHorizontal: 20,
    paddingBottom: 28,
    alignItems: "center",
  },
  avatar: {
    width: 88,
    height: 88,
    borderRadius: 44,
    borderWidth: 3,
    overflow: "hidden",
    alignItems: "center",
    justifyContent: "center",
  },
  avatarInitials: { color: "#fff", fontSize: 30, fontWeight: "800" },
  title: {
    marginTop: 14,
    fontSize: 24,
    fontFamily: "SpaceGrotesk_700Bold",
    textAlign: "center",
  },
  desc: {
    marginTop: 10,
    fontSize: 14,
    textAlign: "center",
    lineHeight: 20,
    maxWidth: 320,
  },
  statRow: {
    marginTop: 18,
    flexDirection: "row",
    alignItems: "center",
  },
  statSep: { width: 1, height: 26 },
  ownerPill: {
    marginTop: 16,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
  },
});
