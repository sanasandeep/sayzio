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

  // When a long-press opened the sheet, React Native still fires onPress on
  // pointer release — suppress that trailing tap so a long-press never ALSO
  // runs the one-tap quick-shorten (which would create an unintended link
  // behind the sheet).
  const longPressedRef = useRef(false);

  const onQuickShorten = async () => {
    if (longPressedRef.current) {
      longPressedRef.current = false;
      return;
    }
    if (shortening) return;
    setShortening(true);
    try {
      const raw = ((await Clipboard.getStringAsync()) ?? "").trim();
      if (!raw) {
        showAlert(
          "Clipboard is empty",
          "Copy a web URL, email address, phone number or any text first, then tap the bolt.",
        );
        return;
      }
      const result = await quickShorten(raw);
      await Clipboard.setStringAsync(result.short_url);
      showToast(
        result.kind === "text"
          ? `Text page link created and copied: ${result.short_url}`
          : `Short link created and copied: ${result.short_url}`,
      );
      query.refetch();
    } catch (e) {
      showAlert(
        "Couldn't shorten that",
        e instanceof Error && e.message
          ? e.message
          : "Copy a web URL, email address, phone number or some text and try again.",
      );
    } finally {
      setShortening(false);
    }
  };

  // ── Quick-shorten customization sheet ──────────────────────────
  // Long-press on the bolt opens a small sheet where the user can pick a
  // custom back-half (alias) and which domain the short link lives on —
  // full parity with the web header popover and the create-link screen.
  // The alias field runs the live availability check (same GET
  // /links/check-alias the edit screen uses) and any server-side 422
  // (taken/banned/format/length) surfaces inline.
  const [sheetOpen, setSheetOpen] = useState(false);
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
    if (shortening || sheetBusy) return;
    const raw = ((await Clipboard.getStringAsync()) ?? "").trim();
    setSheetDest(raw);
    setSheetAlias("");
    setAliasCheck(null);
    setSheetError(null);
    setSheetOpen(true);
  };

  const closeSheet = () => {
    if (sheetBusy) return;
    setSheetOpen(false);
  };

  // Block submit while the live alias check says the typed back-half is
  // taken/invalid/banned/too short. Blank alias (auto-generate) never blocks.
  const aliasBlocked =
    sheetAlias.trim() !== "" && aliasCheck?.available === false;

  const onSheetShorten = async () => {
    if (sheetBusy) return;
    const dest = sheetDest.trim();
    if (!dest) {
      setSheetError(
        "Paste or type a web URL, email address, phone number or any text.",
      );
      return;
    }
    if (aliasBlocked) {
      setSheetError(
        aliasCheck?.message || "That back-half isn't available. Pick another.",
      );
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
      showToast(
        result.kind === "text"
          ? `Text page link created and copied: ${result.short_url}`
          : `Short link created and copied: ${result.short_url}`,
      );
      query.refetch();
    } catch (e) {
      // Surface a 422 (alias taken/banned/format/length or bad
      // destination) inline in the sheet instead of a blocking alert.
      const err = e as { errors?: Record<string, string[]>; message?: string };
      const fieldErrors = err?.errors
        ? [...(err.errors.alias ?? []), ...(err.errors.destination ?? [])]
        : [];
      setSheetError(
        fieldErrors[0] ||
          (typeof err?.message === "string" && err.message
            ? err.message
            : "Couldn't shorten that. Check the destination and try again."),
      );
    } finally {
      setSheetBusy(false);
    }
  };

  const aliasStatusText = aliasChecking
    ? "Checking availability…"
    : aliasCheck
      ? `${aliasCheck.available ? "✓" : "✕"} ${aliasCheck.message}`
      : sheetAlias.trim() === ""
        ? "Leave blank to auto-generate a back-half."
        : "";
  const aliasStatusColor = aliasChecking
    ? colors.mutedForeground
    : aliasCheck
      ? aliasCheck.available
        ? colors.success
        : colors.destructive
      : colors.mutedForeground;

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
              delayLongPress={400}
              hitSlop={8}
              accessibilityLabel="Quick-shorten from clipboard"
              accessibilityHint="Long press to customize the back-half and domain"
              testID="quick-shorten-bolt"
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
        onRequestClose={closeSheet}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.sheetBackdrop}
        >
          <Pressable
            style={StyleSheet.absoluteFill}
            onPress={closeSheet}
            accessibilityLabel="Close quick-shorten sheet"
          />
          <View
            testID="quick-shorten-sheet"
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
              testID="quick-shorten-destination"
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
              testID="quick-shorten-alias-input"
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
                  borderColor:
                    aliasCheck && aliasCheck.available === false
                      ? colors.destructive
                      : colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            {aliasStatusText ? (
              <Text
                testID="quick-shorten-alias-status"
                style={[styles.sheetAliasStatus, { color: aliasStatusColor }]}
              >
                {aliasStatusText}
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
              <Text
                testID="quick-shorten-error"
                style={[styles.sheetAliasStatus, { color: colors.destructive }]}
              >
                {sheetError}
              </Text>
            ) : null}

            <Pressable
              testID="quick-shorten-create"
              onPress={onSheetShorten}
              disabled={sheetBusy || aliasBlocked}
              accessibilityRole="button"
              accessibilityLabel="Create short link"
              accessibilityState={{ disabled: sheetBusy || aliasBlocked }}
              style={[
                styles.sheetBtn,
                {
                  backgroundColor: colors.primary,
                  borderRadius: colors.radius,
                  opacity: sheetBusy || aliasBlocked ? 0.6 : 1,
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
