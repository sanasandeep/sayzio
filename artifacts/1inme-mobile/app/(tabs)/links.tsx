import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { useFocusEffect, useRouter } from "expo-router";
import { useCallback, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
import { useDrawer } from "@/contexts/DrawerContext";
import { useTabBar, useTabBarBottomInset } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";
import type { VoiceClientAction } from "@/lib/api/voice";
import { exportLinksCsv, listLinks } from "@/lib/api/links";
import { LINK_KINDS } from "@/lib/linkKinds";

const FILTERS: { key: string; label: string }[] = [
  { key: "", label: "All" },
  ...LINK_KINDS.map((k) => ({ key: k.apiType, label: k.label })),
];

export default function LinksTab() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { openDrawer } = useDrawer();
  const { reportScroll } = useTabBar();
  const tabBarBottomInset = useTabBarBottomInset();
  const [type, setType] = useState<string>("");
  const [q, setQ] = useState<string>("");
  const webTop = Platform.OS === "web" ? 67 : 0;

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
      Alert.alert(
        "Export failed",
        e instanceof Error ? e.message : "Could not export your links.",
      );
    } finally {
      setExporting(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View
        style={{
          paddingTop: insets.top + 12 + webTop,
          paddingHorizontal: 20,
          paddingBottom: 12,
          gap: 12,
        }}
      >
        <View style={styles.headerRow}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
            <Pressable onPress={openDrawer} hitSlop={8} accessibilityLabel="Open menu">
              <Feather name="menu" size={22} color={colors.foreground} />
            </Pressable>
            <Text style={[styles.title, { color: colors.foreground }]}>
              Links
            </Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
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
          renderItem={({ item }) => <LinkRow link={item} />}
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
});
