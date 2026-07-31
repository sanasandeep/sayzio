import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import * as Clipboard from "expo-clipboard";
import { useFocusEffect, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  FlatList,
  KeyboardAvoidingView,
  Modal,
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
import { DomainPicker } from "@/components/DomainPicker";
import { EmptyState } from "@/components/EmptyState";
import { LinkRow } from "@/components/LinkRow";
import { onVoiceAction, setVoiceSurface } from "@/components/VoiceAssistant";
import { TOP_BAR_H, useTabBar, useTabBarBottomInset } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";
import type { VoiceClientAction } from "@/lib/api/voice";
import { errorStatus } from "@/lib/api";
import { listAvailableDomains } from "@/lib/api/domains";
import {
  checkAlias,
  exportLinksCsv,
  listLinks,
  quickShorten,
  type AliasCheck,
} from "@/lib/api/links";
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

  // ── Quick-shorten customization sheet ──────────────────────────
  // Long-press on the bolt opens a small sheet where the user can pick a
  // custom back-half (alias) and which domain the short link lives on —
  // full parity with the web header popover and the create-link screen.
  const [sheetOpen, setSheetOpen] = useState(false);
  const longPressedRef = useRef(false);
  const [sheetDest, setSheetDest] = useState("");
  const [sheetAlias, setSheetAlias] = useState("");
  const [sheetBusy, setSheetBusy] = useState(false);
  const [sheetError, setSheetError] = useState<string | null>(null);
  const [domainId, setDomainId] = useState<number | null>(null);
  const [domainTouched, setDomainTouched] = useState(false);

  // Lazy — only fetched once the sheet has been opened.
  const domainsQ = useQuery({
    queryKey: ["domains-available"],
    queryFn: listAvailableDomains,
    enabled: sheetOpen,
  });

  // Pre-select the admin-chosen primary global domain once it loads,
  // unless the user has already picked one (matches the create screen).
  useEffect(() => {
    if (domainTouched) return;
    const primary = domainsQ.data?.primary_domain_id ?? null;
    if (primary !== null) setDomainId(primary);
  }, [domainsQ.data?.primary_domain_id, domainTouched]);

  // Debounced alias availability check, scoped to the chosen domain
  // (uniqueness is per-domain, so switching hosts re-checks).
  const [aliasCheck, setAliasCheck] = useState<AliasCheck | null>(null);
  const [aliasChecking, setAliasChecking] = useState(false);
  useEffect(() => {
    if (!sheetOpen) return;
    const trimmed = sheetAlias.trim();
    if (trimmed === "") {
      setAliasCheck(null);
      setAliasChecking(false);
      return;
    }
    setAliasChecking(true);
    let cancelled = false;
    const t = setTimeout(async () => {
      try {
        const res = await checkAlias(trimmed, undefined, domainId);
        if (!cancelled) setAliasCheck(res);
      } catch {
        if (!cancelled) setAliasCheck(null);
      } finally {
        if (!cancelled) setAliasChecking(false);
      }
    }, 450);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [sheetAlias, domainId, sheetOpen]);

  const onOpenSheet = async () => {
    const raw = ((await Clipboard.getStringAsync()) ?? "").trim();
    setSheetDest(raw);
    setSheetAlias("");
    setAliasCheck(null);
    setSheetError(null);
    setSheetOpen(true);
  };

  const onSheetShorten = async () => {
    if (sheetBusy) return;
    const dest = sheetDest.trim();
    if (!dest) {
      setSheetError("Paste or type a web URL, email address or phone number.");
      return;
    }
    setSheetBusy(true);
    setSheetError(null);
    try {
      const result = await quickShorten(dest, {
        alias: sheetAlias.trim() || undefined,
        domain_id: domainId,
      });
      await Clipboard.setStringAsync(result.short_url);
      setSheetOpen(false);
      showToast(`Short link created and copied: ${result.short_url}`);
      query.refetch();
    } catch (e) {
      setSheetError(
        e instanceof Error && e.message
          ? e.message
          : "Couldn't shorten that. Check the destination and try again.",
      );
    } finally {
      setSheetBusy(false);
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
              onPress={() => {
                // Guard: on some platforms (notably react-native-web) onPress
                // can still fire on release after a long-press — a tap after
                // the sheet opened must never also create a link.
                if (longPressedRef.current) {
                  longPressedRef.current = false;
                  return;
                }
                onQuickShorten();
              }}
              onLongPress={() => {
                longPressedRef.current = true;
                onOpenSheet();
              }}
              hitSlop={8}
              accessibilityLabel="Quick-shorten from clipboard"
              accessibilityHint="Long press to customize the back-half and domain"
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

      <Modal
        visible={sheetOpen}
        transparent
        animationType="fade"
        onRequestClose={() => setSheetOpen(false)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.sheetBackdrop}
        >
          <Pressable
            style={StyleSheet.absoluteFill}
            onPress={() => setSheetOpen(false)}
            accessibilityLabel="Close quick-shorten sheet"
          />
          <View
            style={[
              styles.sheet,
              {
                backgroundColor: colors.background,
                borderColor: colors.border,
                paddingBottom: insets.bottom + 16,
              },
            ]}
          >
            <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
              Quick shorten
            </Text>
            <Text style={[styles.sheetLabel, { color: colors.mutedForeground }]}>
              Destination
            </Text>
            <TextInput
              value={sheetDest}
              onChangeText={setSheetDest}
              placeholder="https://example.com/very/long/path"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="url"
              style={[
                styles.sheetInput,
                {
                  color: colors.foreground,
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            <Text style={[styles.sheetLabel, { color: colors.mutedForeground }]}>
              Custom back-half
            </Text>
            <TextInput
              value={sheetAlias}
              onChangeText={setSheetAlias}
              placeholder="leave blank to auto-generate"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              autoCorrect={false}
              style={[
                styles.sheetInput,
                {
                  color: colors.foreground,
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            {sheetAlias.trim() !== "" ? (
              <Text
                style={[
                  styles.sheetAliasStatus,
                  {
                    color: aliasChecking
                      ? colors.mutedForeground
                      : aliasCheck?.available
                        ? colors.success
                        : aliasCheck
                          ? colors.destructive
                          : colors.mutedForeground,
                  },
                ]}
              >
                {aliasChecking
                  ? "Checking availability…"
                  : aliasCheck
                    ? `${aliasCheck.available ? "✓" : "✕"} ${aliasCheck.message}`
                    : ""}
              </Text>
            ) : null}

            <DomainPicker
              value={domainId}
              onChange={(id) => {
                setDomainTouched(true);
                setDomainId(id);
              }}
              data={domainsQ.data}
              loading={domainsQ.isLoading}
            />

            {sheetError ? (
              <Text style={[styles.sheetAliasStatus, { color: colors.destructive }]}>
                {sheetError}
              </Text>
            ) : null}

            <Pressable
              onPress={onSheetShorten}
              disabled={sheetBusy}
              accessibilityRole="button"
              accessibilityLabel="Create short link"
              style={[
                styles.sheetBtn,
                {
                  backgroundColor: colors.primary,
                  borderRadius: colors.radius,
                  opacity: sheetBusy ? 0.6 : 1,
                },
              ]}
            >
              {sheetBusy ? (
                <ActivityIndicator size="small" color={colors.primaryForeground} />
              ) : (
                <Feather name="zap" size={15} color={colors.primaryForeground} />
              )}
              <Text
                style={[styles.sheetBtnText, { color: colors.primaryForeground }]}
              >
                Shorten
              </Text>
            </Pressable>
          </View>
        </KeyboardAvoidingView>
      </Modal>

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
  sheetBackdrop: {
    flex: 1,
    justifyContent: "flex-end",
    backgroundColor: "rgba(0,0,0,0.5)",
  },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    paddingHorizontal: 20,
    paddingTop: 20,
    gap: 10,
  },
  sheetTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
    marginBottom: 4,
  },
  sheetLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  sheetInput: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  sheetAliasStatus: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  sheetBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 12,
    marginTop: 6,
  },
  sheetBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
