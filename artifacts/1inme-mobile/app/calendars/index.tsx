import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, router } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  getMyCalendar,
  getTodayEvents,
  listCalendars,
  type CalendarEventItem,
  type CalendarSummary,
  type MyCalendarFilters,
} from "@/lib/api/calendars";
import { getProfile } from "@/lib/api/profile";
import { addEventWithFeedback } from "@/lib/deviceCalendar";
import { showUpgradePrompt } from "@/lib/upgradePrompt";

type Tab = "agenda" | "today" | "browse";

const TABS: { key: Tab; label: string; icon: keyof typeof Feather.glyphMap }[] = [
  { key: "agenda", label: "Agenda", icon: "list" },
  { key: "today", label: "Today", icon: "sun" },
  { key: "browse", label: "Calendars", icon: "calendar" },
];

const SOURCES: { key: NonNullable<MyCalendarFilters["source"]>; label: string }[] = [
  { key: "all", label: "All" },
  { key: "owned", label: "Mine" },
  { key: "followed", label: "Following" },
];

const RANGES: { key: "upcoming" | "7d" | "30d" | "past"; label: string }[] = [
  { key: "upcoming", label: "Upcoming" },
  { key: "7d", label: "Next 7d" },
  { key: "30d", label: "Next 30d" },
  { key: "past", label: "Past" },
];

type RangeKey = (typeof RANGES)[number]["key"];

function isoDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function rangeToFilter(range: RangeKey): Pick<MyCalendarFilters, "from" | "to" | "past"> {
  const now = new Date();
  if (range === "past") return { past: true, to: isoDate(now) };
  if (range === "7d") {
    const to = new Date(now);
    to.setDate(to.getDate() + 7);
    return { from: isoDate(now), to: isoDate(to) };
  }
  if (range === "30d") {
    const to = new Date(now);
    to.setDate(to.getDate() + 30);
    return { from: isoDate(now), to: isoDate(to) };
  }
  return {}; // upcoming = server default (>= today)
}

export default function CalendarsScreen() {
  const colors = useColors();
  const [tab, setTab] = useState<Tab>("agenda");

  // Agenda filters
  const [source, setSource] = useState<NonNullable<MyCalendarFilters["source"]>>("all");
  const [range, setRange] = useState<RangeKey>("upcoming");
  const [search, setSearch] = useState("");
  const [tag, setTag] = useState<string | null>(null);

  const profileQ = useQuery({ queryKey: ["profile"], queryFn: getProfile });
  const caps = profileQ.data?.capabilities;

  const filters: MyCalendarFilters = useMemo(
    () => ({
      source,
      ...rangeToFilter(range),
      q: search.trim() || null,
      tag,
      perPage: 100,
    }),
    [source, range, search, tag],
  );

  const agendaQ = useQuery({
    queryKey: ["my-calendar", filters],
    queryFn: () => getMyCalendar(filters),
    enabled: tab === "agenda",
  });

  const todayQ = useQuery({
    queryKey: ["my-calendar-today"],
    queryFn: getTodayEvents,
    enabled: tab === "today",
  });

  const calendarsQ = useQuery({
    queryKey: ["calendars"],
    queryFn: listCalendars,
    enabled: tab === "browse",
  });

  const ownedCount = (calendarsQ.data ?? []).filter((c) => c.is_owner).length;

  const onCreatePress = () => {
    if (caps && caps.module_calendar === false) {
      showUpgradePrompt({
        title: "Calendars are a paid feature",
        message:
          "Publishing your own followable calendar isn't available on your current plan.",
        hint: { feature: "module_calendar" },
      });
      return;
    }
    // -1 (or a missing cap) means unlimited; any other finite value is a plan
    // cap on how many calendars this user can own. We can only count owned
    // calendars from the Browse list, so gate the create prompt against that.
    const maxCalendars = caps?.max_calendars;
    if (
      typeof maxCalendars === "number" &&
      maxCalendars >= 0 &&
      ownedCount >= maxCalendars
    ) {
      showUpgradePrompt({
        title: "Calendar limit reached",
        message: `Your plan includes ${maxCalendars} calendar${
          maxCalendars === 1 ? "" : "s"
        }. Upgrade to publish more followable calendars.`,
        hint: { feature: "max_calendars" },
      });
      return;
    }
    showUpgradePrompt({
      title: "Create a calendar on the web",
      message:
        "Building and publishing a followable calendar lives in the web app. You can browse and follow calendars right here on your phone.",
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "My Calendar", headerBackTitle: "Back" }} />

      {/* Tab switcher */}
      <View style={[styles.tabBar, { borderBottomColor: colors.border }]}>
        {TABS.map((t) => {
          const active = t.key === tab;
          return (
            <Pressable
              key={t.key}
              onPress={() => setTab(t.key)}
              style={[styles.tab, active && { borderBottomColor: colors.primary }]}
            >
              <Feather
                name={t.icon}
                size={15}
                color={active ? colors.primary : colors.mutedForeground}
              />
              <Text
                style={[
                  styles.tabLabel,
                  { color: active ? colors.primary : colors.mutedForeground },
                ]}
              >
                {t.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {tab === "agenda" && (
        <AgendaTab
          q={agendaQ}
          colors={colors}
          source={source}
          setSource={setSource}
          range={range}
          setRange={setRange}
          search={search}
          setSearch={setSearch}
          tag={tag}
          setTag={setTag}
        />
      )}

      {tab === "today" && <TodayTab q={todayQ} colors={colors} />}

      {tab === "browse" && (
        <BrowseTab q={calendarsQ} colors={colors} onCreatePress={onCreatePress} />
      )}
    </View>
  );
}

// ── Agenda tab ────────────────────────────────────────────────────
function AgendaTab({
  q,
  colors,
  source,
  setSource,
  range,
  setRange,
  search,
  setSearch,
  tag,
  setTag,
}: {
  q: ReturnType<typeof useQuery<{ items: CalendarEventItem[]; meta: unknown }>>;
  colors: ReturnType<typeof useColors>;
  source: NonNullable<MyCalendarFilters["source"]>;
  setSource: (s: NonNullable<MyCalendarFilters["source"]>) => void;
  range: RangeKey;
  setRange: (r: RangeKey) => void;
  search: string;
  setSearch: (s: string) => void;
  tag: string | null;
  setTag: (t: string | null) => void;
}) {
  const items = q.data?.items ?? [];

  return (
    <View style={{ flex: 1 }}>
      {/* Filters */}
      <View style={{ paddingTop: 12, gap: 10 }}>
        <View style={[styles.searchRow, { marginHorizontal: 16 }]}>
          <View
            style={[
              styles.searchBox,
              { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Feather name="search" size={16} color={colors.mutedForeground} />
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Search events"
              placeholderTextColor={colors.mutedForeground}
              style={[styles.searchInput, { color: colors.foreground }]}
              returnKeyType="search"
            />
            {search ? (
              <Pressable onPress={() => setSearch("")} hitSlop={8}>
                <Feather name="x" size={16} color={colors.mutedForeground} />
              </Pressable>
            ) : null}
          </View>
        </View>

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipScroll}
        >
          {SOURCES.map((s) => (
            <Chip
              key={s.key}
              label={s.label}
              active={s.key === source}
              colors={colors}
              onPress={() => setSource(s.key)}
            />
          ))}
        </ScrollView>

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipScroll}
        >
          {RANGES.map((r) => (
            <Chip
              key={r.key}
              label={r.label}
              active={r.key === range}
              colors={colors}
              onPress={() => setRange(r.key)}
            />
          ))}
        </ScrollView>

        {tag ? (
          <View style={{ marginHorizontal: 16, flexDirection: "row" }}>
            <Pressable
              onPress={() => setTag(null)}
              style={[styles.tagPill, { backgroundColor: colors.primary + "1c" }]}
            >
              <Feather name="hash" size={12} color={colors.primary} />
              <Text style={[styles.tagPillText, { color: colors.primary }]}>{tag}</Text>
              <Feather name="x" size={12} color={colors.primary} />
            </Pressable>
          </View>
        ) : null}
      </View>

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<CalendarEventItem>
          data={items}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 40 }}
          renderItem={({ item }) => (
            <EventCard event={item} colors={colors} onTagPress={setTag} />
          )}
          ListEmptyComponent={
            q.isError ? (
              <EmptyState
                icon="alert-circle"
                title="Couldn't load your agenda"
                body="Pull down to try again."
              />
            ) : (
              <EmptyState
                icon="calendar"
                title="No events match"
                body="Follow a calendar or adjust your filters to see events here."
              />
            )
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}
    </View>
  );
}

// ── Today tab ─────────────────────────────────────────────────────
function TodayTab({
  q,
  colors,
}: {
  q: ReturnType<typeof useQuery<{ date: string; items: CalendarEventItem[] }>>;
  colors: ReturnType<typeof useColors>;
}) {
  const items = q.data?.items ?? [];
  const dateLabel = q.data?.date
    ? new Date(q.data.date + "T00:00:00").toLocaleDateString(undefined, {
        weekday: "long",
        month: "long",
        day: "numeric",
      })
    : "";

  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <FlatList<CalendarEventItem>
      data={items}
      keyExtractor={(e) => String(e.id)}
      contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 40 }}
      ListHeaderComponent={
        dateLabel ? (
          <Text style={[styles.todayHeader, { color: colors.mutedForeground }]}>
            {dateLabel}
          </Text>
        ) : null
      }
      renderItem={({ item }) => <EventCard event={item} colors={colors} />}
      ListEmptyComponent={
        q.isError ? (
          <EmptyState
            icon="alert-circle"
            title="Couldn't load today's events"
            body="Pull down to try again."
          />
        ) : (
          <EmptyState
            icon="sun"
            title="Nothing on today"
            body="Events from calendars you own or follow will appear here on the day they happen."
          />
        )
      }
      refreshControl={
        <RefreshControl
          refreshing={q.isFetching && !q.isLoading}
          onRefresh={() => q.refetch()}
          tintColor={colors.primary}
        />
      }
    />
  );
}

// ── Browse tab ────────────────────────────────────────────────────
function BrowseTab({
  q,
  colors,
  onCreatePress,
}: {
  q: ReturnType<typeof useQuery<CalendarSummary[]>>;
  colors: ReturnType<typeof useColors>;
  onCreatePress: () => void;
}) {
  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <FlatList<CalendarSummary>
      data={q.data ?? []}
      keyExtractor={(c) => String(c.id)}
      contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 40 }}
      renderItem={({ item }) => <CalendarCard calendar={item} colors={colors} />}
      ListEmptyComponent={
        q.isError ? (
          <EmptyState
            icon="alert-circle"
            title="Couldn't load calendars"
            body="Pull down to try again."
          />
        ) : (
          <EmptyState
            icon="calendar"
            title="No calendars yet"
            body="Calendars you create or follow will show up here."
            action={
              <Pressable
                onPress={onCreatePress}
                style={[styles.createBtn, { borderColor: colors.border, borderRadius: colors.radius }]}
              >
                <Feather name="plus" size={16} color={colors.primary} />
                <Text style={[styles.createBtnText, { color: colors.primary }]}>
                  Create a calendar
                </Text>
              </Pressable>
            }
          />
        )
      }
      refreshControl={
        <RefreshControl
          refreshing={q.isFetching && !q.isLoading}
          onRefresh={() => q.refetch()}
          tintColor={colors.primary}
        />
      }
    />
  );
}

// ── Shared pieces ─────────────────────────────────────────────────
function Chip({
  label,
  active,
  colors,
  onPress,
}: {
  label: string;
  active: boolean;
  colors: ReturnType<typeof useColors>;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.chip,
        {
          backgroundColor: active ? colors.primary : colors.card,
          borderColor: active ? colors.primary : colors.border,
        },
      ]}
    >
      <Text
        style={[
          styles.chipText,
          { color: active ? colors.primaryForeground : colors.mutedForeground },
        ]}
      >
        {label}
      </Text>
    </Pressable>
  );
}

function formatEventTime(event: CalendarEventItem): string {
  if (!event.start_at) return "";
  const start = new Date(event.start_at);
  const dateStr = start.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
  });
  if (event.all_day) return `${dateStr} · All day`;
  const timeStr = start.toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });
  return `${dateStr} · ${timeStr}`;
}

function EventCard({
  event,
  colors,
  onTagPress,
}: {
  event: CalendarEventItem;
  colors: ReturnType<typeof useColors>;
  onTagPress?: (tag: string) => void;
}) {
  const accent = event.calendar?.accent_color || colors.primary;
  const [adding, setAdding] = useState(false);
  const addToCalendar = async () => {
    setAdding(true);
    try {
      await addEventWithFeedback(event);
    } finally {
      setAdding(false);
    }
  };
  return (
    <Pressable
      onPress={() =>
        router.push({ pathname: "/calendars/[id]", params: { id: String(event.calendar_id) } })
      }
      style={[
        styles.eventCard,
        { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <View style={[styles.accentBar, { backgroundColor: accent }]} />
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={[styles.eventTime, { color: accent }]}>{formatEventTime(event)}</Text>
        <Text style={[styles.eventTitle, { color: colors.foreground }]} numberOfLines={2}>
          {event.title}
        </Text>
        {event.calendar?.title ? (
          <Text style={[styles.eventMeta, { color: colors.mutedForeground }]} numberOfLines={1}>
            {event.calendar.title}
          </Text>
        ) : null}
        {event.location ? (
          <View style={styles.metaRow}>
            <Feather name="map-pin" size={12} color={colors.mutedForeground} />
            <Text style={[styles.eventMeta, { color: colors.mutedForeground }]} numberOfLines={1}>
              {event.location}
            </Text>
          </View>
        ) : null}
        {event.hashtags?.length ? (
          <View style={styles.tagWrap}>
            {event.hashtags.slice(0, 4).map((h) => (
              <Pressable
                key={h}
                onPress={onTagPress ? () => onTagPress(h) : undefined}
                style={[styles.eventTag, { backgroundColor: colors.muted }]}
              >
                <Text style={[styles.eventTagText, { color: colors.mutedForeground }]}>#{h}</Text>
              </Pressable>
            ))}
          </View>
        ) : null}
        {event.start_at ? (
          <Pressable
            onPress={addToCalendar}
            disabled={adding}
            hitSlop={6}
            style={[styles.addBtn, { borderColor: accent, borderRadius: colors.radius }]}
          >
            {adding ? (
              <ActivityIndicator size="small" color={accent} />
            ) : (
              <Feather name="calendar" size={14} color={accent} />
            )}
            <Text style={[styles.addBtnText, { color: accent }]}>Add to my calendar</Text>
          </Pressable>
        ) : null}
      </View>
    </Pressable>
  );
}

function CalendarCard({
  calendar,
  colors,
}: {
  calendar: CalendarSummary;
  colors: ReturnType<typeof useColors>;
}) {
  const accent = calendar.accent_color || colors.primary;
  return (
    <Pressable
      onPress={() =>
        router.push({ pathname: "/calendars/[id]", params: { id: String(calendar.id) } })
      }
      style={[
        styles.calCard,
        { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <View style={[styles.calIcon, { backgroundColor: accent + "22" }]}>
        <Feather name="calendar" size={18} color={accent} />
      </View>
      <View style={{ flex: 1, gap: 3 }}>
        <Text style={[styles.calTitle, { color: colors.foreground }]} numberOfLines={1}>
          {calendar.title}
        </Text>
        <Text style={[styles.calMeta, { color: colors.mutedForeground }]} numberOfLines={1}>
          {calendar.events_count} event{calendar.events_count === 1 ? "" : "s"} ·{" "}
          {calendar.followers_count} follower{calendar.followers_count === 1 ? "" : "s"}
        </Text>
      </View>
      <View style={styles.badgeCol}>
        {calendar.is_owner ? (
          <View style={[styles.badge, { backgroundColor: colors.muted }]}>
            <Text style={[styles.badgeText, { color: colors.mutedForeground }]}>Owner</Text>
          </View>
        ) : calendar.is_following ? (
          <View style={[styles.badge, { backgroundColor: colors.primary + "1c" }]}>
            <Text style={[styles.badgeText, { color: colors.primary }]}>Following</Text>
          </View>
        ) : null}
      </View>
      <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", paddingVertical: 60 },
  tabBar: { flexDirection: "row", borderBottomWidth: 1 },
  tab: {
    flex: 1,
    flexDirection: "row",
    gap: 6,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 13,
    borderBottomWidth: 2,
    borderBottomColor: "transparent",
  },
  tabLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  searchRow: { flexDirection: "row" },
  searchBox: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    height: 44,
    borderWidth: 1,
  },
  searchInput: { flex: 1, fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, padding: 0 },
  chipScroll: { paddingHorizontal: 16, gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  tagPill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
  },
  tagPillText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  todayHeader: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginBottom: 4,
  },
  eventCard: { flexDirection: "row", gap: 12, padding: 14, borderWidth: 1, overflow: "hidden" },
  accentBar: { width: 4, borderRadius: 999 },
  eventTime: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  eventTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  eventMeta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, flexShrink: 1 },
  metaRow: { flexDirection: "row", alignItems: "center", gap: 5 },
  tagWrap: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 2 },
  eventTag: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  eventTagText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  addBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    marginTop: 8,
    alignSelf: "flex-start",
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderWidth: 1,
  },
  addBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  calCard: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  calIcon: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  calTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  calMeta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  badgeCol: { alignItems: "flex-end" },
  badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  badgeText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  createBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderWidth: 1,
  },
  createBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
