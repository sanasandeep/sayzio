import { Feather } from "@expo/vector-icons";
import { router } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
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
  getMyEvents,
  listEvents,
  type MyEventsItem,
  setEventInterest,
} from "@/lib/api/events";
import {
  onEventsLocationChange,
  resolveEventsLocation,
  setSavedEventsLocation,
  type SavedEventsLocation,
} from "@/lib/eventsLocation";

type DateFilter = "any" | "today" | "week" | "month";

// "My events" agenda views (Task #5508): list stays the default; day /
// week / month are pure client-side projections of the same personal
// events payload — no extra API calls.
type MyView = "list" | "day" | "week" | "month";

const MY_VIEWS: { key: MyView; label: string }[] = [
  { key: "list", label: "List" },
  { key: "day", label: "Day" },
  { key: "week", label: "Week" },
  { key: "month", label: "Month" },
];

const DAY_MS = 24 * 60 * 60 * 1000;

function startOfDay(d: Date): Date {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
}

/** Monday-anchored start of week. */
function startOfWeek(d: Date): Date {
  const x = startOfDay(d);
  const dow = (x.getDay() + 6) % 7;
  x.setDate(x.getDate() - dow);
  return x;
}

function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function dayKey(d: Date): string {
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

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
  const [segment, setSegment] = useState<"discover" | "mine">("discover");
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
  const [myItems, setMyItems] = useState<MyEventsItem[] | null>(null);
  const [myLoading, setMyLoading] = useState(false);
  const [myRefreshing, setMyRefreshing] = useState(false);
  const [myView, setMyView] = useState<MyView>("list");
  const [agendaDate, setAgendaDate] = useState<Date>(() => startOfDay(new Date()));

  const loadMine = useCallback(async () => {
    try {
      const items = await getMyEvents();
      setMyItems(items);
    } catch {
      setMyItems((prev) => prev ?? []);
    }
  }, []);

  useEffect(() => {
    if (segment === "mine" && myItems === null) {
      setMyLoading(true);
      loadMine().finally(() => setMyLoading(false));
    }
  }, [segment, myItems, loadMine]);

  const { upcomingMine, pastMine } = useMemo(() => {
    const items = myItems ?? [];
    const now = Date.now();
    const isPast = (i: MyEventsItem) => {
      const raw = i.event.end_date || i.event.start_date;
      if (!raw) return false;
      const t = new Date(raw).getTime();
      return !Number.isNaN(t) && t < now;
    };
    const upcoming = items.filter((i) => !isPast(i));
    const past = items.filter(isPast);
    // Upcoming soonest-first; past most-recent-first (server sends desc).
    upcoming.sort((a, b) =>
      (a.event.start_date ?? "9999").localeCompare(b.event.start_date ?? "9999"),
    );
    return { upcomingMine: upcoming, pastMine: past };
  }, [myItems]);

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

  // Map of dayKey → items whose event starts that day, for agenda views.
  const itemsByDay = useMemo(() => {
    const map = new Map<string, MyEventsItem[]>();
    for (const it of myItems ?? []) {
      if (!it.event.start_date) continue;
      const d = new Date(it.event.start_date);
      if (Number.isNaN(d.getTime())) continue;
      const key = dayKey(d);
      const arr = map.get(key);
      if (arr) arr.push(it);
      else map.set(key, [it]);
    }
    for (const arr of map.values()) {
      arr.sort((a, b) =>
        (a.event.start_date ?? "").localeCompare(b.event.start_date ?? ""),
      );
    }
    return map;
  }, [myItems]);

  const renderMyEventCard = (it: MyEventsItem, opts?: { timeOnly?: boolean }) => {
    const past =
      !!(it.event.end_date || it.event.start_date) &&
      new Date(it.event.end_date || it.event.start_date || "").getTime() < Date.now();
    return (
      <Pressable
        key={`${it.kind}:${it.event.id}:${it.ticket_code ?? ""}`}
        onPress={() => router.push(`/events/${it.event.alias}`)}
        style={[
          styles.card,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            opacity: past ? 0.65 : 1,
          },
        ]}
      >
        <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
          <Feather
            name={it.kind === "ticket" ? "check-circle" : "star"}
            size={14}
            color={it.kind === "ticket" ? colors.primary : colors.mutedForeground}
          />
          <Text style={{ color: colors.mutedForeground, fontSize: 12, fontWeight: "600" }}>
            {it.kind === "ticket"
              ? `Attending${(it.quantity ?? 1) > 1 ? ` · ${it.quantity} tickets` : ""}`
              : "Interested"}
          </Text>
        </View>
        <Text style={[styles.cardTitle, { color: colors.foreground }]}>
          {it.event.title ?? "Event"}
        </Text>
        {it.event.start_date ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
            {opts?.timeOnly
              ? new Date(it.event.start_date).toLocaleTimeString([], {
                  hour: "2-digit",
                  minute: "2-digit",
                })
              : new Date(it.event.start_date).toLocaleString()}
          </Text>
        ) : null}
        {it.event.location ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
            📍 {it.event.location}
          </Text>
        ) : null}
      </Pressable>
    );
  };

  const renderDayList = (day: Date) => {
    const items = itemsByDay.get(dayKey(day)) ?? [];
    if (items.length === 0) {
      return (
        <Text
          style={{
            color: colors.mutedForeground,
            fontSize: 13,
            textAlign: "center",
            paddingVertical: 24,
          }}
        >
          Nothing scheduled this day.
        </Text>
      );
    }
    return <View style={{ gap: 10 }}>{items.map((it) => renderMyEventCard(it, { timeOnly: true }))}</View>;
  };

  const renderAgendaNav = (label: string, onPrev: () => void, onNext: () => void) => (
    <View style={styles.agendaNav}>
      <Pressable hitSlop={8} onPress={onPrev} style={[styles.agendaNavBtn, { borderColor: colors.border }]}>
        <Feather name="chevron-left" size={18} color={colors.foreground} />
      </Pressable>
      <Pressable onPress={() => setAgendaDate(startOfDay(new Date()))}>
        <Text style={{ color: colors.foreground, fontSize: 15, fontWeight: "700" }}>{label}</Text>
      </Pressable>
      <Pressable hitSlop={8} onPress={onNext} style={[styles.agendaNavBtn, { borderColor: colors.border }]}>
        <Feather name="chevron-right" size={18} color={colors.foreground} />
      </Pressable>
    </View>
  );

  const renderDayView = () => (
    <View style={{ padding: 16, gap: 12 }}>
      {renderAgendaNav(
        agendaDate.toLocaleDateString(undefined, {
          weekday: "long",
          month: "long",
          day: "numeric",
        }),
        () => setAgendaDate(new Date(agendaDate.getTime() - DAY_MS)),
        () => setAgendaDate(new Date(agendaDate.getTime() + DAY_MS)),
      )}
      {renderDayList(agendaDate)}
    </View>
  );

  const renderWeekView = () => {
    const weekStart = startOfWeek(agendaDate);
    const days = Array.from({ length: 7 }, (_, i) => new Date(weekStart.getTime() + i * DAY_MS));
    const weekEnd = new Date(weekStart.getTime() + 6 * DAY_MS);
    const label = `${weekStart.toLocaleDateString(undefined, { month: "short", day: "numeric" })} – ${weekEnd.toLocaleDateString(undefined, { month: "short", day: "numeric" })}`;
    return (
      <View style={{ padding: 16, gap: 12 }}>
        {renderAgendaNav(
          label,
          () => setAgendaDate(new Date(agendaDate.getTime() - 7 * DAY_MS)),
          () => setAgendaDate(new Date(agendaDate.getTime() + 7 * DAY_MS)),
        )}
        <View style={styles.weekStrip}>
          {days.map((d) => {
            const selected = sameDay(d, agendaDate);
            const hasEvents = (itemsByDay.get(dayKey(d)) ?? []).length > 0;
            const today = sameDay(d, new Date());
            return (
              <Pressable
                key={dayKey(d)}
                onPress={() => setAgendaDate(d)}
                style={[
                  styles.weekDay,
                  {
                    backgroundColor: selected ? colors.primary : "transparent",
                    borderColor: today && !selected ? colors.primary : colors.border,
                  },
                ]}
              >
                <Text
                  style={{
                    color: selected ? colors.primaryForeground : colors.mutedForeground,
                    fontSize: 11,
                    fontWeight: "600",
                  }}
                >
                  {d.toLocaleDateString(undefined, { weekday: "narrow" })}
                </Text>
                <Text
                  style={{
                    color: selected ? colors.primaryForeground : colors.foreground,
                    fontSize: 15,
                    fontWeight: "700",
                  }}
                >
                  {d.getDate()}
                </Text>
                <View
                  style={[
                    styles.dot,
                    {
                      backgroundColor: hasEvents
                        ? selected
                          ? colors.primaryForeground
                          : colors.primary
                        : "transparent",
                    },
                  ]}
                />
              </Pressable>
            );
          })}
        </View>
        {renderDayList(agendaDate)}
      </View>
    );
  };

  const renderMonthView = () => {
    const monthStart = new Date(agendaDate.getFullYear(), agendaDate.getMonth(), 1);
    const gridStart = startOfWeek(monthStart);
    const cells = Array.from({ length: 42 }, (_, i) => new Date(gridStart.getTime() + i * DAY_MS));
    return (
      <View style={{ padding: 16, gap: 12 }}>
        {renderAgendaNav(
          monthStart.toLocaleDateString(undefined, { month: "long", year: "numeric" }),
          () => setAgendaDate(new Date(agendaDate.getFullYear(), agendaDate.getMonth() - 1, 1)),
          () => setAgendaDate(new Date(agendaDate.getFullYear(), agendaDate.getMonth() + 1, 1)),
        )}
        <View style={styles.monthGrid}>
          {["M", "T", "W", "T", "F", "S", "S"].map((w, i) => (
            <View key={`w${i}`} style={styles.monthCell}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11, fontWeight: "700" }}>{w}</Text>
            </View>
          ))}
          {cells.map((d) => {
            const inMonth = d.getMonth() === monthStart.getMonth();
            const selected = sameDay(d, agendaDate);
            const today = sameDay(d, new Date());
            const hasEvents = (itemsByDay.get(dayKey(d)) ?? []).length > 0;
            return (
              <Pressable
                key={dayKey(d)}
                onPress={() => setAgendaDate(startOfDay(d))}
                style={[
                  styles.monthCell,
                  selected && { backgroundColor: colors.primary, borderRadius: 10 },
                ]}
              >
                <Text
                  style={{
                    color: selected
                      ? colors.primaryForeground
                      : today
                        ? colors.primary
                        : inMonth
                          ? colors.foreground
                          : colors.mutedForeground,
                    fontSize: 13,
                    fontWeight: today || selected ? "700" : "400",
                    opacity: inMonth ? 1 : 0.45,
                  }}
                >
                  {d.getDate()}
                </Text>
                <View
                  style={[
                    styles.dot,
                    {
                      backgroundColor: hasEvents
                        ? selected
                          ? colors.primaryForeground
                          : colors.primary
                        : "transparent",
                    },
                  ]}
                />
              </Pressable>
            );
          })}
        </View>
        {renderDayList(agendaDate)}
      </View>
    );
  };

  const renderMyEvents = () => {
    if (myLoading) {
      return <ActivityIndicator style={{ marginTop: 40 }} color={colors.primary} />;
    }

    const viewChips = (
      <View style={styles.filterRow}>
        {MY_VIEWS.map((v) => (
          <Pressable
            key={v.key}
            onPress={() => setMyView(v.key)}
            style={[
              styles.chip,
              {
                borderColor: myView === v.key ? colors.primary : colors.border,
                backgroundColor: myView === v.key ? colors.primary : "transparent",
              },
            ]}
          >
            <Text
              style={{
                color: myView === v.key ? colors.primaryForeground : colors.mutedForeground,
                fontSize: 13,
                fontWeight: "600",
              }}
            >
              {v.label}
            </Text>
          </Pressable>
        ))}
      </View>
    );

    if (myView !== "list") {
      return (
        <ScrollView
          refreshControl={
            <RefreshControl
              refreshing={myRefreshing}
              onRefresh={async () => {
                setMyRefreshing(true);
                try {
                  await loadMine();
                } finally {
                  setMyRefreshing(false);
                }
              }}
            />
          }
          contentContainerStyle={{ paddingBottom: 32 }}
        >
          {viewChips}
          {myView === "day"
            ? renderDayView()
            : myView === "week"
              ? renderWeekView()
              : renderMonthView()}
        </ScrollView>
      );
    }

    const sections: { header: string; data: MyEventsItem[] }[] = [];
    if (upcomingMine.length > 0) sections.push({ header: "Upcoming", data: upcomingMine });
    if (pastMine.length > 0) sections.push({ header: "Past", data: pastMine });
    if (sections.length === 0) {
      return (
        <EmptyState
          icon="calendar"
          title="No events yet"
          body="Events you get tickets for or mark as Interested will show up here — upcoming and past."
        />
      );
    }
    const flat: ({ type: "header"; key: string; label: string } | { type: "item"; key: string; item: MyEventsItem })[] = [];
    for (const s of sections) {
      flat.push({ type: "header", key: `h:${s.header}`, label: s.header });
      for (const it of s.data) {
        flat.push({ type: "item", key: `${it.kind}:${it.event.id}:${it.ticket_code ?? ""}`, item: it });
      }
    }
    return (
      <FlatList
        data={flat}
        keyExtractor={(r) => r.key}
        contentContainerStyle={{ padding: 16, gap: 10 }}
        refreshControl={
          <RefreshControl
            refreshing={myRefreshing}
            onRefresh={async () => {
              setMyRefreshing(true);
              try {
                await loadMine();
              } finally {
                setMyRefreshing(false);
              }
            }}
          />
        }
        renderItem={({ item: row }) => {
          if (row.type === "header") {
            return (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  fontWeight: "700",
                  textTransform: "uppercase",
                  letterSpacing: 0.6,
                  marginTop: 6,
                }}
              >
                {row.label}
              </Text>
            );
          }
          const it = row.item;
          const past =
            !!(it.event.end_date || it.event.start_date) &&
            new Date(it.event.end_date || it.event.start_date || "").getTime() < Date.now();
          return (
            <Pressable
              onPress={() => router.push(`/events/${it.event.alias}`)}
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  opacity: past ? 0.65 : 1,
                },
              ]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Feather
                  name={it.kind === "ticket" ? "check-circle" : "star"}
                  size={14}
                  color={it.kind === "ticket" ? colors.primary : colors.mutedForeground}
                />
                <Text style={{ color: colors.mutedForeground, fontSize: 12, fontWeight: "600" }}>
                  {it.kind === "ticket"
                    ? `Attending${(it.quantity ?? 1) > 1 ? ` · ${it.quantity} tickets` : ""}`
                    : "Interested"}
                </Text>
              </View>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                {it.event.title ?? "Event"}
              </Text>
              {it.event.start_date ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  {new Date(it.event.start_date).toLocaleString()}
                </Text>
              ) : null}
              {it.event.location ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  📍 {it.event.location}
                </Text>
              ) : null}
            </Pressable>
          );
        }}
      />
    );
  };

  return (
    <View style={[styles.wrap, { backgroundColor: colors.background }]}>
      <View style={[styles.filterRow, { paddingTop: 12 }]}>
        {(
          [
            { key: "discover", label: "Discover" },
            { key: "mine", label: "My events" },
          ] as const
        ).map((s) => (
          <Pressable
            key={s.key}
            onPress={() => setSegment(s.key)}
            style={[
              styles.chip,
              {
                borderColor: segment === s.key ? colors.primary : colors.border,
                backgroundColor: segment === s.key ? colors.primary : "transparent",
              },
            ]}
          >
            <Text
              style={{
                color: segment === s.key ? colors.primaryForeground : colors.mutedForeground,
                fontSize: 13,
                fontWeight: "600",
              }}
            >
              {s.label}
            </Text>
          </Pressable>
        ))}
      </View>

      {segment === "mine" ? (
        renderMyEvents()
      ) : (
        <>
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
        </>
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
  agendaNav: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  agendaNavBtn: {
    width: 34,
    height: 34,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  weekStrip: { flexDirection: "row", gap: 6 },
  weekDay: {
    flex: 1,
    alignItems: "center",
    gap: 2,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 8,
  },
  dot: { width: 5, height: 5, borderRadius: 3, marginTop: 2 },
  monthGrid: { flexDirection: "row", flexWrap: "wrap" },
  monthCell: {
    width: `${100 / 7}%`,
    alignItems: "center",
    paddingVertical: 6,
  },
  cardFooterRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginTop: 4,
  },
});
