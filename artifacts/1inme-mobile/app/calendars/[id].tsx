import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  deleteCalendarEvent,
  getCalendar,
  toggleCalendarFollow,
  type CalendarDetail,
  type CalendarEventItem,
} from "@/lib/api/calendars";
import {
  addEventsWithFeedback,
  addEventWithFeedback,
  subscribeToIcs,
} from "@/lib/deviceCalendar";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

function formatEventTime(event: CalendarEventItem): string {
  if (!event.start_at) return "";
  const start = new Date(event.start_at);
  const dateStr = start.toLocaleDateString(undefined, {
    weekday: "short",
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

export default function CalendarDetailScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const [showPast, setShowPast] = useState(false);
  const [addingId, setAddingId] = useState<number | null>(null);
  const [bulkAdding, setBulkAdding] = useState(false);

  const q = useQuery({
    queryKey: ["calendar", id, showPast],
    queryFn: () => getCalendar(id, { past: showPast }),
    enabled: Number.isFinite(id),
  });

  const cal = q.data?.calendar;
  const events = q.data?.events ?? [];

  const follow = useMutation({
    mutationFn: () => toggleCalendarFollow(id),
    onSuccess: (res) => {
      // Patch the cached detail so the toggle + counts update instantly.
      qc.setQueryData<{ calendar: CalendarDetail; events: CalendarEventItem[] }>(
        ["calendar", id, showPast],
        (prev) =>
          prev
            ? {
                ...prev,
                calendar: {
                  ...prev.calendar,
                  is_following: res.following,
                  followers_count: res.followers_count,
                },
              }
            : prev,
      );
      qc.invalidateQueries({ queryKey: ["calendars"] });
      qc.invalidateQueries({ queryKey: ["my-calendar"] });
      qc.invalidateQueries({ queryKey: ["my-calendar-today"] });
    },
    onError: (e) => {
      handlePlanLockedError(e, "Couldn't update follow.");
    },
  });

  const removeEvent = useMutation({
    mutationFn: (eventId: number) => deleteCalendarEvent(id, eventId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["calendar", id] });
      qc.invalidateQueries({ queryKey: ["calendars"] });
      qc.invalidateQueries({ queryKey: ["my-calendar"] });
      qc.invalidateQueries({ queryKey: ["my-calendar-today"] });
    },
    onError: (e) =>
      Alert.alert(
        "Couldn't delete event",
        (e as { message?: string })?.message ?? "Please try again.",
      ),
  });

  const confirmDeleteEvent = (event: CalendarEventItem) => {
    Alert.alert("Delete event?", `"${event.title}" will be removed. This can't be undone.`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => removeEvent.mutate(event.id),
      },
    ]);
  };

  const accent = cal?.accent_color || colors.primary;

  // Events still in the future — the "add all" action only ever syncs upcoming
  // ones, even when the past toggle is on.
  const now = Date.now();
  const upcomingAddable = events.filter(
    (e) => e.start_at && new Date(e.start_at).getTime() >= now,
  );

  const addToCalendar = async (event: CalendarEventItem) => {
    setAddingId(event.id);
    try {
      await addEventWithFeedback(event);
    } finally {
      setAddingId(null);
    }
  };

  const addAllUpcoming = async () => {
    if (upcomingAddable.length === 0) return;
    setBulkAdding(true);
    try {
      await addEventsWithFeedback(upcomingAddable);
    } finally {
      setBulkAdding(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: cal?.title || "Calendar", headerBackTitle: "Back" }} />

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError || !cal ? (
        <EmptyState
          icon="alert-circle"
          title="Calendar unavailable"
          body="This calendar may be private or no longer exists."
        />
      ) : (
        <FlatList<CalendarEventItem>
          data={events}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 40 }}
          ListHeaderComponent={
            <View style={{ gap: 14, marginBottom: 6 }}>
              <View
                style={[
                  styles.header,
                  { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                ]}
              >
                <View style={styles.headerTop}>
                  <View style={[styles.calIcon, { backgroundColor: accent + "22" }]}>
                    <Feather name="calendar" size={20} color={accent} />
                  </View>
                  <View style={{ flex: 1, gap: 3 }}>
                    <Text style={[styles.title, { color: colors.foreground }]}>{cal.title}</Text>
                    <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                      {cal.followers_count} follower{cal.followers_count === 1 ? "" : "s"} ·{" "}
                      {cal.events_count} event{cal.events_count === 1 ? "" : "s"}
                    </Text>
                  </View>
                </View>

                {cal.description ? (
                  <Text style={[styles.desc, { color: colors.mutedForeground }]}>
                    {cal.description}
                  </Text>
                ) : null}

                {cal.is_owner ? (
                  <View style={styles.ownerActions}>
                    <Button
                      label="Add event"
                      onPress={() =>
                        router.push({
                          pathname: "/calendars/event",
                          params: { calendar: String(id) },
                        })
                      }
                      style={{ flex: 1 }}
                      leading={
                        <Feather name="plus" size={16} color={colors.primaryForeground} />
                      }
                    />
                    <Button
                      label="Edit"
                      variant="outline"
                      onPress={() =>
                        router.push({
                          pathname: "/calendars/edit",
                          params: { id: String(id) },
                        })
                      }
                      style={{ flex: 1 }}
                      leading={
                        <Feather name="settings" size={16} color={colors.foreground} />
                      }
                    />
                  </View>
                ) : (
                  <Button
                    label={cal.is_following ? "Following" : "Follow"}
                    variant={cal.is_following ? "outline" : "primary"}
                    loading={follow.isPending}
                    onPress={() => follow.mutate()}
                    leading={
                      <Feather
                        name={cal.is_following ? "check" : "plus"}
                        size={16}
                        color={cal.is_following ? colors.foreground : colors.primaryForeground}
                      />
                    }
                  />
                )}

                {upcomingAddable.length > 0 ? (
                  <Pressable
                    onPress={addAllUpcoming}
                    disabled={bulkAdding}
                    style={[styles.bulkBtn, { backgroundColor: accent, borderRadius: colors.radius }]}
                  >
                    {bulkAdding ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Feather name="calendar" size={15} color="#fff" />
                    )}
                    <Text style={styles.bulkBtnText}>
                      Add all {upcomingAddable.length} upcoming event
                      {upcomingAddable.length === 1 ? "" : "s"}
                    </Text>
                  </Pressable>
                ) : null}

                {cal.ics_url ? (
                  <Pressable
                    onPress={() => subscribeToIcs(cal.ics_url)}
                    style={[styles.subscribeRow, { borderColor: colors.border, borderRadius: colors.radius }]}
                  >
                    <Feather name="rss" size={14} color={colors.mutedForeground} />
                    <Text style={[styles.subscribeText, { color: colors.mutedForeground }]} numberOfLines={1}>
                      Subscribe — keep this calendar in sync
                    </Text>
                    <Feather name="external-link" size={14} color={colors.mutedForeground} />
                  </Pressable>
                ) : null}
              </View>

              <Pressable
                onPress={() => setShowPast((v) => !v)}
                style={[styles.pastToggle, { borderColor: colors.border, borderRadius: colors.radius }]}
              >
                <Feather
                  name={showPast ? "clock" : "calendar"}
                  size={14}
                  color={colors.mutedForeground}
                />
                <Text style={[styles.pastToggleText, { color: colors.mutedForeground }]}>
                  {showPast ? "Showing past & upcoming" : "Upcoming only — tap to include past"}
                </Text>
              </Pressable>
            </View>
          }
          renderItem={({ item }) => (
            <View
              style={[
                styles.eventCard,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.accentBar, { backgroundColor: accent }]} />
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={[styles.eventTime, { color: accent }]}>{formatEventTime(item)}</Text>
                <Text style={[styles.eventTitle, { color: colors.foreground }]} numberOfLines={2}>
                  {item.title}
                </Text>
                {item.description ? (
                  <Text
                    style={[styles.eventDesc, { color: colors.mutedForeground }]}
                    numberOfLines={3}
                  >
                    {item.description}
                  </Text>
                ) : null}
                {item.location ? (
                  <View style={styles.metaRow}>
                    <Feather name="map-pin" size={12} color={colors.mutedForeground} />
                    <Text style={[styles.eventMeta, { color: colors.mutedForeground }]} numberOfLines={1}>
                      {item.location}
                    </Text>
                  </View>
                ) : null}
                {item.hashtags?.length ? (
                  <View style={styles.tagWrap}>
                    {item.hashtags.slice(0, 5).map((h) => (
                      <View key={h} style={[styles.eventTag, { backgroundColor: colors.muted }]}>
                        <Text style={[styles.eventTagText, { color: colors.mutedForeground }]}>
                          #{h}
                        </Text>
                      </View>
                    ))}
                  </View>
                ) : null}
                {item.start_at ? (
                  <Pressable
                    onPress={() => addToCalendar(item)}
                    disabled={addingId === item.id}
                    hitSlop={6}
                    style={[
                      styles.addBtn,
                      { borderColor: accent, borderRadius: colors.radius },
                    ]}
                  >
                    {addingId === item.id ? (
                      <ActivityIndicator size="small" color={accent} />
                    ) : (
                      <Feather name="calendar" size={14} color={accent} />
                    )}
                    <Text style={[styles.addBtnText, { color: accent }]}>
                      Add to my calendar
                    </Text>
                  </Pressable>
                ) : null}

                {cal.is_owner ? (
                  <View style={[styles.eventOwnerRow, { borderTopColor: colors.border }]}>
                    <Pressable
                      onPress={() =>
                        router.push({
                          pathname: "/calendars/event",
                          params: { calendar: String(id), id: String(item.id) },
                        })
                      }
                      hitSlop={8}
                      style={styles.eventOwnerBtn}
                    >
                      <Feather name="edit-2" size={14} color={colors.mutedForeground} />
                      <Text style={[styles.eventOwnerBtnText, { color: colors.mutedForeground }]}>
                        Edit
                      </Text>
                    </Pressable>
                    <Pressable
                      onPress={() => confirmDeleteEvent(item)}
                      hitSlop={8}
                      style={styles.eventOwnerBtn}
                    >
                      <Feather name="trash-2" size={14} color={colors.destructive} />
                      <Text style={[styles.eventOwnerBtnText, { color: colors.destructive }]}>
                        Delete
                      </Text>
                    </Pressable>
                  </View>
                ) : null}
              </View>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="calendar"
              title={showPast ? "No events" : "No upcoming events"}
              body={
                showPast
                  ? "This calendar has no events yet."
                  : "Nothing coming up. Tap above to include past events."
              }
            />
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

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  header: { padding: 16, borderWidth: 1, gap: 12 },
  headerTop: { flexDirection: "row", alignItems: "center", gap: 12 },
  calIcon: {
    width: 44,
    height: 44,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  meta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  desc: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  ownerActions: { flexDirection: "row", gap: 10 },
  eventOwnerRow: {
    flexDirection: "row",
    gap: 18,
    marginTop: 8,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  ownerNoteText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, flex: 1 },
  subscribeRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
  },
  subscribeText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, flex: 1 },
  eventOwnerBtn: { flexDirection: "row", alignItems: "center", gap: 5 },
  eventOwnerBtnText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  bulkBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 11,
  },
  bulkBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, color: "#fff" },
  pastToggle: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
  },
  pastToggleText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  eventCard: { flexDirection: "row", gap: 12, padding: 14, borderWidth: 1, overflow: "hidden" },
  accentBar: { width: 4, borderRadius: 999 },
  eventTime: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  eventTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  eventDesc: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 18 },
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
});
