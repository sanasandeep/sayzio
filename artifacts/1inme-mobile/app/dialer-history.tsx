import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Linking,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";
import type { ApiError } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

type HistoryItem = {
  id: number;
  number_e164: string | null;
  contact_id: number | null;
  name: string;
  initials: string;
  outcome: string | null;
  note: string | null;
  tag: string | null;
  callback_at: string | null;
  at: string | null;
  at_human: string;
  at_date: string | null;
};

type HistoryResponse = {
  data: {
    items: HistoryItem[];
    total: number;
    page: number;
    per_page: number;
    has_more: boolean;
  };
};

const OUTCOMES = [
  { value: "", label: "All outcomes" },
  { value: "called", label: "Called" },
  { value: "messaged", label: "Messaged" },
  { value: "no_answer", label: "No answer" },
  { value: "voicemail", label: "Voicemail" },
  { value: "busy", label: "Busy" },
  { value: "wrong_number", label: "Wrong number" },
  { value: "completed", label: "Completed" },
];

const OUTCOME_COLORS: Record<string, { bg: string; fg: string }> = {
  called:       { bg: "#22c55e22", fg: "#22c55e" },
  messaged:     { bg: "#3b82f622", fg: "#60a5fa" },
  completed:    { bg: "#22c55e22", fg: "#22c55e" },
  no_answer:    { bg: "#fbbf2422", fg: "#fbbf24" },
  voicemail:    { bg: "#a855f722", fg: "#c084fc" },
  busy:         { bg: "#fbbf2422", fg: "#fbbf24" },
  wrong_number: { bg: "#ef444422", fg: "#ef4444" },
};

function dayLabel(iso: string | null): string {
  if (!iso) return "Unknown date";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const day = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diff = Math.round((today.getTime() - day.getTime()) / 86400000);
  if (diff === 0) return "Today";
  if (diff === 1) return "Yesterday";
  if (diff < 7)
    return d.toLocaleDateString(undefined, { weekday: "long" });
  return d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function groupByDay(items: HistoryItem[]): { date: string; label: string; items: HistoryItem[] }[] {
  const groups: Record<string, { date: string; label: string; items: HistoryItem[] }> = {};
  for (const item of items) {
    const d = item.at_date ?? "unknown";
    if (!groups[d]) {
      groups[d] = { date: d, label: dayLabel(item.at), items: [] };
    }
    groups[d].items.push(item);
  }
  return Object.values(groups);
}

export default function DialerHistoryScreen() {
  const colors = useColors();
  const router = useRouter();
  const { token } = useAuth();

  const [items, setItems] = useState<HistoryItem[]>([]);
  const [total, setTotal] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [page, setPage] = useState(0);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [filterOutcome, setFilterOutcome] = useState("");
  const [filterQ, setFilterQ] = useState("");
  const [showOutcomePicker, setShowOutcomePicker] = useState(false);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const buildParams = useCallback(
    (pg: number) => {
      const p: Record<string, string> = { full: "1", page: String(pg) };
      if (filterOutcome) p.outcome = filterOutcome;
      if (filterQ.trim()) p.q = filterQ.trim();
      return new URLSearchParams(p).toString();
    },
    [filterOutcome, filterQ],
  );

  const load = useCallback(
    async (pg: number, append: boolean) => {
      setLoading(true);
      setError(null);
      try {
        const res = await apiFetch<HistoryResponse>(
          `/dialer/history?${buildParams(pg)}`,
        );
        const data = (res as unknown as HistoryResponse).data;
        setTotal(data.total ?? 0);
        setHasMore(data.has_more ?? false);
        setPage(pg);
        if (append) {
          setItems((prev) => [...prev, ...(data.items ?? [])]);
        } else {
          setItems(data.items ?? []);
        }
      } catch (err) {
        const e = err as ApiError;
        setError(e.message ?? "Could not load history");
      } finally {
        setLoading(false);
      }
    },
    [buildParams],
  );

  useEffect(() => {
    setItems([]);
    setPage(0);
    load(0, false);
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    setItems([]);
    await load(0, false);
    setRefreshing(false);
  }, [load]);

  const onLoadMore = useCallback(() => {
    if (!hasMore || loading) return;
    load(page + 1, true);
  }, [hasMore, loading, page, load]);

  const onQChange = useCallback(
    (text: string) => {
      setFilterQ(text);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        setItems([]);
        setPage(0);
      }, 400);
    },
    [],
  );

  const onOutcomeChange = useCallback((v: string) => {
    setFilterOutcome(v);
    setShowOutcomePicker(false);
    setItems([]);
    setPage(0);
  }, []);

  const deleteEntry = useCallback(
    async (item: HistoryItem) => {
      showAlert(
        "Delete entry",
        `Remove this log entry for "${item.name}"?`,
        [
          { text: "Cancel", style: "cancel" },
          {
            text: "Delete",
            style: "destructive",
            onPress: async () => {
              try {
                await apiFetch(`/dialer/history/${item.id}`, { method: "DELETE" });
                setItems((prev) => prev.filter((i) => i.id !== item.id));
                setTotal((t) => Math.max(0, t - 1));
              } catch {
                showAlert("Error", "Could not delete entry.");
              }
            },
          },
        ],
      );
    },
    [],
  );

  const clearHistory = useCallback(async () => {
    const msg = filterOutcome
      ? `Clear all "${filterOutcome.replace(/_/g, " ")}" entries?`
      : "Clear your entire call history? This cannot be undone.";
    showAlert("Clear history", msg, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Clear",
        style: "destructive",
        onPress: async () => {
          try {
            const params = filterOutcome
              ? `?outcome=${encodeURIComponent(filterOutcome)}`
              : "";
            await apiFetch(`/dialer/history${params}`, { method: "DELETE" });
            setItems([]);
            setTotal(0);
            setHasMore(false);
          } catch {
            showAlert("Error", "Could not clear history.");
          }
        },
      },
    ]);
  }, [filterOutcome]);

  const openProfile = useCallback(
    (item: HistoryItem) => {
      const params = new URLSearchParams();
      if (item.number_e164) params.set("number", item.number_e164);
      if (item.contact_id) params.set("contact", String(item.contact_id));
      router.push(`/dialer-profile?${params.toString()}` as never);
    },
    [router],
  );

  const callBack = useCallback((item: HistoryItem) => {
    if (!item.number_e164) return;
    Linking.openURL(`tel:${item.number_e164}`).catch(() => {});
  }, []);

  const groups = groupByDay(items);

  const renderGroup = ({ item: group }: { item: (typeof groups)[0] }) => (
    <View style={styles.group}>
      <Text style={[styles.dayLabel, { color: colors.mutedForeground }]}>
        {group.label}
      </Text>
      {group.items.map((entry) => renderEntry(entry))}
    </View>
  );

  const renderEntry = (item: HistoryItem) => {
    const oc = item.outcome ? OUTCOME_COLORS[item.outcome] : null;
    return (
      <Pressable
        key={item.id}
        style={[
          styles.row,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
        onPress={() => openProfile(item)}
        accessibilityRole="button"
        accessibilityLabel={`Open profile for ${item.name}`}
      >
        <View style={styles.rowInner}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{item.initials}</Text>
          </View>
          <View style={styles.rowContent}>
            <View style={styles.rowTop}>
              <Text
                style={[styles.name, { color: colors.foreground }]}
                numberOfLines={1}
              >
                {item.name}
              </Text>
              {oc && (
                <View
                  style={[
                    styles.outcomeBadge,
                    { backgroundColor: oc.bg },
                  ]}
                >
                  <Text style={[styles.outcomeBadgeText, { color: oc.fg }]}>
                    {item.outcome!.replace(/_/g, " ")}
                  </Text>
                </View>
              )}
            </View>
            {(item.number_e164 || item.tag || item.note) ? (
              <Text
                style={[styles.meta, { color: colors.mutedForeground }]}
                numberOfLines={1}
              >
                {[item.number_e164, item.tag ? `#${item.tag}` : null, item.note]
                  .filter(Boolean)
                  .join(" · ")}
              </Text>
            ) : null}
            <Text style={[styles.when, { color: colors.mutedForeground }]}>
              {item.at_human}
            </Text>
          </View>
          <View style={styles.rowActions}>
            {item.number_e164 ? (
              <Pressable
                onPress={() => callBack(item)}
                style={({ pressed }) => [
                  styles.actionBtn,
                  { backgroundColor: "#22c55e22", opacity: pressed ? 0.7 : 1 },
                ]}
                hitSlop={6}
                accessibilityLabel="Call back"
              >
                <Feather name="phone" size={13} color="#22c55e" />
              </Pressable>
            ) : null}
            <Pressable
              onPress={() => deleteEntry(item)}
              style={({ pressed }) => [
                styles.actionBtn,
                {
                  backgroundColor: colors.destructive + "22",
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
              hitSlop={6}
              accessibilityLabel="Delete entry"
            >
              <Feather name="trash-2" size={13} color={colors.destructive} />
            </Pressable>
          </View>
        </View>
      </Pressable>
    );
  };

  const outcomeLabel = OUTCOMES.find((o) => o.value === filterOutcome)?.label ?? "All outcomes";

  return (
    <>
      <Stack.Screen
        options={{
          title: "Call history",
          headerRight: () =>
            items.length > 0 ? (
              <Pressable
                onPress={clearHistory}
                hitSlop={8}
                style={styles.clearBtn}
                accessibilityLabel="Clear history"
              >
                <Feather name="trash" size={16} color={colors.destructive} />
              </Pressable>
            ) : null,
        }}
      />
      <View style={[styles.container, { backgroundColor: colors.background }]}>
        {/* Filter bar */}
        <View
          style={[
            styles.filterBar,
            { backgroundColor: colors.card, borderBottomColor: colors.border },
          ]}
        >
          <View
            style={[
              styles.searchWrap,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            <Feather
              name="search"
              size={13}
              color={colors.mutedForeground}
              style={styles.searchIcon}
            />
            <TextInput
              style={[styles.searchInput, { color: colors.foreground }]}
              placeholder="Name, number, note…"
              placeholderTextColor={colors.mutedForeground}
              value={filterQ}
              onChangeText={onQChange}
              autoCorrect={false}
              autoCapitalize="none"
            />
          </View>
          <Pressable
            onPress={() => setShowOutcomePicker((v) => !v)}
            style={[
              styles.outcomeChip,
              {
                backgroundColor: filterOutcome
                  ? colors.primary + "22"
                  : colors.background,
                borderColor: filterOutcome ? colors.primary : colors.border,
              },
            ]}
          >
            <Text
              style={[
                styles.outcomeChipText,
                { color: filterOutcome ? colors.primary : colors.mutedForeground },
              ]}
              numberOfLines={1}
            >
              {outcomeLabel}
            </Text>
            <Feather
              name="chevron-down"
              size={11}
              color={filterOutcome ? colors.primary : colors.mutedForeground}
            />
          </Pressable>
        </View>

        {/* Outcome picker dropdown */}
        {showOutcomePicker && (
          <View
            style={[
              styles.picker,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <ScrollView>
              {OUTCOMES.map((o) => (
                <Pressable
                  key={o.value}
                  onPress={() => onOutcomeChange(o.value)}
                  style={({ pressed }) => [
                    styles.pickerItem,
                    pressed && { backgroundColor: colors.background },
                    filterOutcome === o.value && {
                      backgroundColor: colors.primary + "15",
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.pickerItemText,
                      {
                        color:
                          filterOutcome === o.value
                            ? colors.primary
                            : colors.foreground,
                        fontWeight:
                          filterOutcome === o.value ? "600" : "400",
                      },
                    ]}
                  >
                    {o.label}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
          </View>
        )}

        {loading && items.length === 0 ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : error ? (
          <View style={styles.center}>
            <Text style={{ color: colors.mutedForeground }}>{error}</Text>
            <Pressable onPress={() => load(0, false)} style={styles.retry} hitSlop={8}>
              <Text style={{ color: colors.primary }}>Try again</Text>
            </Pressable>
          </View>
        ) : (
          <FlatList
            data={groups}
            keyExtractor={(g) => g.date}
            renderItem={renderGroup}
            contentContainerStyle={styles.listContent}
            refreshControl={
              <RefreshControl
                refreshing={refreshing}
                onRefresh={onRefresh}
                tintColor={colors.primary}
              />
            }
            onEndReached={onLoadMore}
            onEndReachedThreshold={0.3}
            ListEmptyComponent={
              <View style={styles.emptyWrap}>
                <Feather
                  name="clock"
                  size={32}
                  color={colors.mutedForeground}
                  style={{ marginBottom: 12 }}
                />
                <Text style={[styles.emptyTitle, { color: colors.foreground }]}>
                  No history yet
                </Text>
                <Text
                  style={[styles.emptyMsg, { color: colors.mutedForeground }]}
                >
                  {filterOutcome || filterQ.trim()
                    ? "No entries match your filters."
                    : "Lookups and calls will appear here once you start using the dialer."}
                </Text>
              </View>
            }
            ListFooterComponent={
              hasMore ? (
                <View style={styles.footerLoad}>
                  <ActivityIndicator color={colors.primary} size="small" />
                </View>
              ) : total > 0 ? (
                <Text
                  style={[styles.footerCount, { color: colors.mutedForeground }]}
                >
                  {total} {total === 1 ? "entry" : "entries"} total
                </Text>
              ) : null
            }
          />
        )}
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 12,
  },
  retry: { padding: 8 },
  filterBar: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderBottomWidth: 1,
  },
  searchWrap: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 7,
    gap: 6,
  },
  searchIcon: {},
  searchInput: { flex: 1, fontSize: 13 },
  outcomeChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 7,
    borderWidth: 1,
    borderRadius: 10,
    maxWidth: 130,
  },
  outcomeChipText: { fontSize: 12, fontWeight: "500", flexShrink: 1 },
  picker: {
    position: "absolute",
    top: 64,
    right: 16,
    width: 200,
    maxHeight: 280,
    borderWidth: 1,
    borderRadius: 12,
    zIndex: 100,
    elevation: 8,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
  },
  pickerItem: { paddingHorizontal: 16, paddingVertical: 12 },
  pickerItemText: { fontSize: 14 },
  listContent: { padding: 16, gap: 16, paddingBottom: 32 },
  group: { gap: 8 },
  dayLabel: {
    fontSize: 11,
    fontWeight: "600",
    textTransform: "uppercase",
    letterSpacing: 0.8,
    marginBottom: 2,
  },
  row: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 12,
  },
  rowInner: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
  },
  avatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: "#3d6bff",
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  avatarText: { color: "#fff", fontSize: 12, fontWeight: "700" },
  rowContent: { flex: 1, minWidth: 0 },
  rowTop: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    flexWrap: "wrap",
  },
  name: { fontSize: 14, fontWeight: "600", flexShrink: 1 },
  outcomeBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
  },
  outcomeBadgeText: { fontSize: 10, fontWeight: "600" },
  meta: { fontSize: 12, marginTop: 2 },
  when: { fontSize: 11, marginTop: 2 },
  rowActions: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    flexShrink: 0,
  },
  actionBtn: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: "center",
    justifyContent: "center",
  },
  emptyWrap: {
    alignItems: "center",
    justifyContent: "center",
    paddingTop: 60,
  },
  emptyTitle: { fontSize: 16, fontWeight: "600", marginBottom: 6 },
  emptyMsg: { fontSize: 13, textAlign: "center", lineHeight: 18, paddingHorizontal: 32 },
  footerLoad: { paddingVertical: 16, alignItems: "center" },
  footerCount: {
    textAlign: "center",
    fontSize: 12,
    paddingVertical: 16,
  },
  clearBtn: { marginRight: 8, padding: 4 },
});
