import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import * as Clipboard from "expo-clipboard";
import { useFocusEffect, useRouter } from "expo-router";
import { useCallback, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { DictationMic } from "@/components/DictationMic";
import { EmptyState } from "@/components/EmptyState";
import { LinkRow } from "@/components/LinkRow";
import { onVoiceAction, setVoiceSurface } from "@/components/VoiceAssistant";
import { TOP_BAR_H, useTabBar, useTabBarBottomInset } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";
import type { VoiceClientAction } from "@/lib/api/voice";
import { errorStatus } from "@/lib/api";
import { exportLinksCsv, listLinks, quickShorten } from "@/lib/api/links";
import { LINK_KINDS } from "@/lib/linkKinds";
import { showAlert } from "@/lib/webAlert";

const FILTERS: { key: string; label: string }[] = [
  { key: "", label: "All" },
  ...LINK_KINDS.map((k) => ({ key: k.apiType, label: k.label })),
];

export default function LinksTab() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { reportScroll } = useTabBar();
  const tabBarBottomInset = useTabBarBottomInset();
  const [type, setType] = useState<string>("");
  const [q, setQ] = useState<string>("");

  // ── Voice control ──────────────────────────────────────────────
  // Spoken "find my … link" runs the search_app tool, which returns a
  // search intent we drop straight into the query box. The mic in the
  // search bar dictates a query directly (STT-only, metered like a turn).
  const voiceHandlerRef = useRef<(a: VoiceClientAction) => void>(() => {});
  voiceHandlerRef.current = (a: VoiceClientAction) => {
    if (a.type === "search" && "query" in a) {
      setQ(String((a as { query: unknown }).query ?? ""));
    }
  };
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("app");
      const off = onVoiceAction((a) => voiceHandlerRef.current(a));
      return () => {
        off();
        setVoiceSurface(null);
      };
    }, []),
  );
  const query = useQuery({
    queryKey: ["links", { type, q }],
    queryFn: () => listLinks({ type: type || undefined, q: q || undefined, per_page: 100 }),
  });

  const refreshing = query.isFetching && !query.isLoading;

  // Export the current (filtered) link list to CSV — web parity for the
  // "Export CSV" action on /user/links. Not plan-gated.
  const [exporting, setExporting] = useState(false);
  const onExport = async () => {
    if (exporting) return;
    setExporting(true);
    try {
      await exportLinksCsv({ type: type || undefined, q: q || undefined });
    } catch (e) {
      showAlert(
        "Export failed",
        e instanceof Error ? e.message : "Could not export your links.",
      );
    } finally {
      setExporting(false);
    }
  };

  // ── Clipboard quick-shorten ────────────────────────────────────
  // Mobile parity for the web header bolt button: read the clipboard,
  // let the server classify/normalize it (URL / email / phone / bare
  // domain), create a short link in one tap, copy the short URL back
  // to the clipboard and confirm with a toast.
  const [shortening, setShortening] = useState(false);
  const [toast, setToast] = useState<string | null>(null);
  const toastOpacity = useRef(new Animated.Value(0)).current;
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const showToast = useCallback(
    (message: string) => {
      if (toastTimer.current) clearTimeout(toastTimer.current);
      setToast(message);
      Animated.timing(toastOpacity, {
        toValue: 1,
        duration: 180,
        useNativeDriver: true,
      }).start();
      toastTimer.current = setTimeout(() => {
        Animated.timing(toastOpacity, {
          toValue: 0,
          duration: 240,
          useNativeDriver: true,
        }).start(() => setToast(null));
      }, 2600);
    },
    [toastOpacity],
  );

  const onQuickShorten = async () => {
    if (shortening) return;
    setShortening(true);
    try {
      const raw = ((await Clipboard.getStringAsync()) ?? "").trim();
      if (!raw) {
        showAlert(
          "Clipboard is empty",
          "Copy a web URL, email address or phone number first, then tap the bolt.",
        );
        return;
      }
      const result = await quickShorten(raw);
      await Clipboard.setStringAsync(result.short_url);
      showToast(`Short link created and copied: ${result.short_url}`);
      query.refetch();
    } catch (e) {
      showAlert(
        "Couldn't shorten that",
        e instanceof Error && e.message
          ? e.message
          : "Copy a web URL, email address or phone number and try again.",
      );
    } finally {
      setShortening(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View
        style={{
          paddingTop: insets.top + TOP_BAR_H + 12,
          paddingHorizontal: 20,
          paddingBottom: 12,
          gap: 12,
        }}
      >
        <View style={styles.headerRow}>
          <Text style={[styles.title, { color: colors.foreground }]}>
            Links
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <Pressable
              onPress={onQuickShorten}
              hitSlop={8}
              accessibilityLabel="Quick-shorten from clipboard"
              disabled={shortening}
              style={[
                styles.healthBtn,
                {
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                  opacity: shortening ? 0.6 : 1,
                },
              ]}
            >
              {shortening ? (
                <ActivityIndicator size="small" color={colors.foreground} />
              ) : (
                <Feather name="zap" size={16} color={colors.foreground} />
              )}
            </Pressable>
            <Pressable
              onPress={onExport}
              hitSlop={8}
              accessibilityLabel="Export CSV"
              disabled={exporting}
              style={[
                styles.healthBtn,
                {
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                  opacity: exporting ? 0.6 : 1,
                },
              ]}
            >
              {exporting ? (
                <ActivityIndicator size="small" color={colors.foreground} />
              ) : (
                <Feather name="download" size={16} color={colors.foreground} />
              )}
            </Pressable>
            <Pressable
              onPress={() => router.push("/links/insurance" as any)}
              hitSlop={8}
              accessibilityLabel="Link Health"
              style={[
                styles.healthBtn,
                { borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Feather name="shield" size={16} color={colors.foreground} />
            </Pressable>
            <Pressable
              onPress={() => router.push("/(tabs)/create")}
              hitSlop={8}
              style={[
                styles.newBtn,
                { backgroundColor: colors.primary, borderRadius: colors.radius },
              ]}
            >
              <Feather name="plus" size={16} color={colors.primaryForeground} />
              <Text
                style={[styles.newBtnText, { color: colors.primaryForeground }]}
              >
                New
              </Text>
            </Pressable>
          </View>
        </View>

        <View
          style={[
            styles.search,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <Feather name="search" size={16} color={colors.mutedForeground} />
          <TextInput
            value={q}
            onChangeText={setQ}
            placeholder="Search by title, alias, or URL"
            placeholderTextColor={colors.mutedForeground}
            style={[styles.searchInput, { color: colors.foreground }]}
            returnKeyType="search"
          />
          {q ? (
            <Pressable onPress={() => setQ("")} hitSlop={8}>
              <Feather name="x" size={16} color={colors.mutedForeground} />
            </Pressable>
          ) : null}
          <DictationMic
            size={16}
            onText={(t) => setQ(t)}
            style={{ marginLeft: 6 }}
          />
        </View>

        <View style={styles.filterRow}>
          <FlatList
            data={FILTERS}
            horizontal
            keyExtractor={(f) => f.key || "all"}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 8 }}
            renderItem={({ item }) => {
              const active = item.key === type;
              return (
                <Pressable
                  onPress={() => setType(item.key)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? colors.primary : colors.card,
                      borderColor: active ? colors.primary : colors.border,
                      borderRadius: 999,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.chipText,
                      {
                        color: active
                          ? colors.primaryForeground
                          : colors.mutedForeground,
                      },
                    ]}
                  >
                    {item.label}
                  </Text>
                </Pressable>
              );
            }}
          />
        </View>
      </View>

      {query.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : query.error ? (
        <View
          style={{
            flex: 1,
            alignItems: "center",
            justifyContent: "center",
            paddingHorizontal: 32,
            gap: 12,
          }}
        >
          <Text style={{ color: colors.destructive, textAlign: "center" }}>
            {errorStatus(query.error) === 401
              ? "Your session has expired. Please sign in again."
              : `Couldn't load your links${
                  (query.error as { message?: string })?.message
                    ? `: ${(query.error as { message?: string }).message}`
                    : "."
                }`}
          </Text>
          <Pressable
            onPress={() => query.refetch()}
            accessibilityRole="button"
            accessibilityLabel="Retry loading links"
            style={{
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: 10,
              paddingHorizontal: 14,
              paddingVertical: 8,
            }}
          >
            <Text style={{ color: colors.foreground, fontWeight: "600" }}>
              Retry
            </Text>
          </Pressable>
        </View>
      ) : (
        <FlatList
          data={query.data?.items ?? []}
          keyExtractor={(l) => String(l.id)}
          contentContainerStyle={{
            paddingHorizontal: 20,
            paddingBottom: tabBarBottomInset,
            gap: 10,
          }}
          onScroll={(e) => reportScroll(e.nativeEvent.contentOffset.y)}
          scrollEventThrottle={16}
          ItemSeparatorComponent={() => <View style={{ height: 4 }} />}
          renderItem={({ item }) => <LinkRow link={item} showNfcButton />}
          ListEmptyComponent={
            <EmptyState
              icon="link"
              title={q || type ? "No links match your filters" : "No links yet"}
              body={
                q || type
                  ? "Try clearing the search or filter."
                  : "Tap Create to make your first link."
              }
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => query.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}

      {toast ? (
        <Animated.View
          pointerEvents="none"
          style={[
            styles.toast,
            {
              backgroundColor: colors.primary,
              bottom: tabBarBottomInset + 12,
              opacity: toastOpacity,
            },
          ]}
        >
          <Text
            style={[styles.toastText, { color: colors.primaryForeground }]}
            numberOfLines={2}
          >
            {toast}
          </Text>
        </Animated.View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  headerRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 28 },
  newBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  newBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  healthBtn: {
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  search: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
  },
  searchInput: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    padding: 0,
  },
  filterRow: {},
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  toast: {
    position: "absolute",
    left: 20,
    right: 20,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  toastText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
});
