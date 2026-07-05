import { Feather } from "@expo/vector-icons";
import { router } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { MapPickerModal, type PickedPoint } from "@/components/MapPickerModal";
import { useColors } from "@/hooks/useColors";
import {
  type EventInterestStatus,
  type EventItem,
  listEvents,
  setEventInterest,
} from "@/lib/api/events";
import {
  onEventsLocationChange,
  resolveEventsLocation,
  setSavedEventsLocation,
  type SavedEventsLocation,
} from "@/lib/eventsLocation";

type DateFilter = "any" | "today" | "week" | "month";

const DATE_FILTERS: { key: DateFilter; label: string }[] = [
  { key: "any", label: "Any time" },
  { key: "today", label: "Today" },
  { key: "week", label: "This week" },
  { key: "month", label: "This month" },
];

// The events API has no date-range query param, so the directory fetches
// the normal upcoming-first page and this narrows it client-side — no
// backend change needed for a "when" filter.
function matchesDateFilter(event: EventItem, filter: DateFilter): boolean {
  if (filter === "any" || !event.start_date) return true;
  const start = new Date(event.start_date).getTime();
  if (Number.isNaN(start)) return true;
  const now = Date.now();
  const dayMs = 24 * 60 * 60 * 1000;
  if (filter === "today") {
    const startOfDay = new Date().setHours(0, 0, 0, 0);
    return start >= startOfDay && start < startOfDay + dayMs;
  }
  if (filter === "week") return start >= now && start < now + 7 * dayMs;
  if (filter === "month") return start >= now && start < now + 31 * dayMs;
  return true;
}

/**
 * Events directory, anchored to the dialer's saved "my location" (the same
 * pin used across contacts/caller-ID) rather than a one-off GPS read per
 * screen — see `lib/eventsLocation.ts`.
 */
export default function EventsDirectoryScreen() {
  const colors = useColors();
  const [q, setQ] = useState("");
  const [tag, setTag] = useState<string | null>(null);
  const [nearMe, setNearMe] = useState(true);
  const [dateFilter, setDateFilter] = useState<DateFilter>("any");
  const [location, setLocation] = useState<SavedEventsLocation | null>(null);
  const [events, setEvents] = useState<EventItem[]>([]);
  const [interestBusyId, setInterestBusyId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);

  const visibleEvents = useMemo(
    () => events.filter((e) => matchesDateFilter(e, dateFilter)),
    [events, dateFilter],
  );

  const load = useCallback(
    async (opts: { near?: boolean; query?: string; tag?: string | null } = {}) => {
      const near = opts.near ?? nearMe;
      const query = opts.query ?? q;
      const activeTag = opts.tag !== undefined ? opts.tag : tag;
      let loc: SavedEventsLocation | null = null;
      if (near) {
        loc = await resolveEventsLocation();
        setLocation(loc);
      }
      const res = await listEvents({
        q: query || undefined,
        tag: activeTag || undefined,
        lat: loc?.lat,
        lng: loc?.lng,
      });
      setEvents(res.items);
    },
    [nearMe, q, tag],
  );

  useEffect(() => {
    load().finally(() => setLoading(false));
    return onEventsLocationChange((loc) => {
      setLocation(loc);
      if (nearMe) void load({ near: true });
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await load();
    } finally {
      setRefreshing(false);
    }
  }, [load]);

  const toggleNearMe = useCallback(async () => {
    const next = !nearMe;
    setNearMe(next);
    setLoading(true);
    try {
      await load({ near: next });
    } finally {
      setLoading(false);
    }
  }, [nearMe, load]);

  const runSearch = useCallback(async () => {
    setLoading(true);
    try {
      await load({ query: q });
    } finally {
      setLoading(false);
    }
  }, [load, q]);

  const clearTag = useCallback(async () => {
    setTag(null);
    setLoading(true);
    try {
      await load({ tag: null });
    } finally {
      setLoading(false);
    }
  }, [load]);

  const onToggleInterest = useCallback(
    async (event: EventItem, status: EventInterestStatus) => {
      setInterestBusyId(event.id);
      try {
        const res = await setEventInterest(event.alias, status);
        setEvents((prev) =>
          prev.map((e) =>
            e.id === event.id
              ? {
                  ...e,
                  interested_count: res.counts.interested,
                  not_interested_count: res.counts.not_interested,
                }
              : e,
          ),
        );
      } catch {
        // best-effort — the card just keeps its previous counts on failure
      } finally {
        setInterestBusyId(null);
      }
    },
    [],
  );

  const onPickLocation = useCallback(
    async (point: PickedPoint) => {
      const loc: SavedEventsLocation = {
        lat: point.lat,
        lng: point.lng,
        label: point.address || null,
      };
      await setSavedEventsLocation(loc);
      setLocation(loc);
      setNearMe(true);
      setLoading(true);
      try {
        await load({ near: true });
      } finally {
        setLoading(false);
      }
    },
    [load],
  );

  return (
    <View style={[styles.wrap, { backgroundColor: colors.background }]}>
      <View style={styles.searchRow}>
        <View
          style={[
            styles.searchBox,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <Feather name="search" size={16} color={colors.mutedForeground} />
          <TextInput
            value={q}
            onChangeText={setQ}
            onSubmitEditing={runSearch}
            placeholder="Search events, venues..."
            placeholderTextColor={colors.mutedForeground}
            style={[styles.searchInput, { color: colors.foreground }]}
            returnKeyType="search"
          />
        </View>
        <Pressable
          onPress={() => setPickerOpen(true)}
          style={[
            styles.nearBtn,
            {
              backgroundColor: nearMe ? colors.primary : colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Feather
            name="map-pin"
            size={16}
            color={nearMe ? colors.primaryForeground : colors.foreground}
          />
        </Pressable>
      </View>

      <MapPickerModal
        visible={pickerOpen}
        initialLat={location?.lat}
        initialLng={location?.lng}
        onClose={() => setPickerOpen(false)}
        onPick={onPickLocation}
      />

      <View style={styles.filterRow}>
        <Pressable
          onPress={toggleNearMe}
          style={[
            styles.chip,
            {
              borderColor: nearMe ? colors.primary : colors.border,
              backgroundColor: nearMe ? colors.primary : "transparent",
            },
          ]}
        >
          <Text
            style={{
              color: nearMe ? colors.primaryForeground : colors.mutedForeground,
              fontSize: 13,
              fontWeight: "600",
            }}
          >
            {nearMe ? (location?.label ?? "Near me") : "Near me"}
          </Text>
        </Pressable>
        {tag ? (
          <Pressable
            onPress={clearTag}
            style={[styles.chip, { borderColor: colors.primary, backgroundColor: colors.primary }]}
          >
            <Text style={{ color: colors.primaryForeground, fontSize: 13, fontWeight: "600" }}>
              #{tag} ✕
            </Text>
          </Pressable>
        ) : null}
      </View>

      <View style={styles.filterRow}>
        {DATE_FILTERS.map((f) => (
          <Pressable
            key={f.key}
            onPress={() => setDateFilter(f.key)}
            style={[
              styles.chip,
              {
                borderColor: dateFilter === f.key ? colors.primary : colors.border,
                backgroundColor: dateFilter === f.key ? colors.primary : "transparent",
              },
            ]}
          >
            <Text
              style={{
                color: dateFilter === f.key ? colors.primaryForeground : colors.mutedForeground,
                fontSize: 13,
                fontWeight: "600",
              }}
            >
              {f.label}
            </Text>
          </Pressable>
        ))}
      </View>

      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={colors.primary} />
      ) : visibleEvents.length === 0 ? (
        <EmptyState
          icon="calendar"
          title="No events found"
          body={
            nearMe
              ? "Try a different search, a different tag, a different date range, or turn off the near-me filter."
              : "Try a different search, a different date range, or turn on the near-me filter."
          }
        />
      ) : (
        <FlatList
          data={visibleEvents}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/events/${item.alias}`)}
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              {item.cover_image_url ? (
                <Image source={{ uri: item.cover_image_url }} style={styles.cover} />
              ) : null}
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                {item.title}
              </Text>
              {item.start_date ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  {new Date(item.start_date).toLocaleString()}
                </Text>
              ) : null}
              {item.location ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  📍 {item.location}
                </Text>
              ) : null}
              {item.hashtags.length > 0 ? (
                <View style={styles.tagRow}>
                  {item.hashtags.slice(0, 4).map((t) => (
                    <Pressable
                      key={t}
                      onPress={() => {
                        setTag(t);
                        setLoading(true);
                        load({ tag: t }).finally(() => setLoading(false));
                      }}
                      style={[styles.tagChip, { borderColor: colors.border }]}
                    >
                      <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>#{t}</Text>
                    </Pressable>
                  ))}
                </View>
              ) : null}
              <View style={styles.cardFooterRow}>
                {item.ticketing_enabled && item.tiers.length > 0 ? (
                  <Text style={{ color: colors.primary, fontWeight: "600" }}>
                    From {item.tiers[0]?.price_label}
                  </Text>
                ) : (
                  <Text style={{ color: colors.primary, fontWeight: "600" }}>Free RSVP</Text>
                )}
                {item.interested_count > 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {item.interested_count} interested
                  </Text>
                ) : null}
              </View>
              <View style={styles.cardInterestRow}>
                <Pressable
                  disabled={interestBusyId === item.id}
                  onPress={() => onToggleInterest(item, "interested")}
                  style={[styles.cardInterestBtn, { borderColor: colors.border }]}
                >
                  <Feather name="star" size={13} color={colors.foreground} />
                  <Text style={{ color: colors.foreground, fontSize: 12, fontWeight: "600" }}>
                    Interested
                  </Text>
                </Pressable>
                <Pressable
                  disabled={interestBusyId === item.id}
                  onPress={() => onToggleInterest(item, "not_interested")}
                  style={[styles.cardInterestBtn, { borderColor: colors.border }]}
                >
                  <Feather name="x-circle" size={13} color={colors.mutedForeground} />
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, fontWeight: "600" }}>
                    Not interested
                  </Text>
                </Pressable>
              </View>
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  searchRow: {
    flexDirection: "row",
    gap: 8,
    padding: 16,
    paddingBottom: 8,
  },
  searchBox: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    height: 44,
  },
  searchInput: { flex: 1, fontSize: 15 },
  nearBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  filterRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    paddingHorizontal: 16,
    paddingBottom: 8,
  },
  chip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 4,
    overflow: "hidden",
  },
  cover: {
    width: "100%",
    height: 120,
    borderRadius: 10,
    marginBottom: 6,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: "700",
  },
  cardInterestRow: { flexDirection: "row", gap: 8, marginTop: 8 },
  cardInterestBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 4 },
  tagChip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  cardFooterRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginTop: 4,
  },
});
