import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
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
  detectSavedDeviceEvents,
  removeEventsWithFeedback,
  removeEventWithFeedback,
  requestCalendarAccessForDetection,
  subscribeToIcs,
  syncEventsWithFeedback,
} from "@/lib/deviceCalendar";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import { showAlert } from "@/lib/webAlert";

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
  const plan = usePlanFeatures();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const [showPast, setShowPast] = useState(false);
  const [addingId, setAddingId] = useState<number | null>(null);
  const [removingId, setRemovingId] = useState<number | null>(null);
  const [bulkAdding, setBulkAdding] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [bulkRemoving, setBulkRemoving] = useState(false);
  // Sayzio event ids already on the device calendar. `null` = not yet known /
  // can't be determined (web, no permission), in which case we default to Add.
  const [savedIds, setSavedIds] = useState<Set<string> | null>(null);
  // True when saved-state detection genuinely works on this device but calendar
  // access hasn't been granted — drives the inline "allow access" hint.
  const [savedDetectionBlocked, setSavedDetectionBlocked] = useState(false);
  const [hintDismissed, setHintDismissed] = useState(false);
  const [requestingAccess, setRequestingAccess] = useState(false);

  const q = useQuery({
    queryKey: ["calendar", id, showPast],
    queryFn: () => getCalendar(id, { past: showPast }),
    enabled: Number.isFinite(id),
  });

  const cal = q.data?.calendar;
  const events = q.data?.events ?? [];

  // Surface the per-plan event allowance (`max_calendar_events`) right here on
  // the detail screen so an owner sees how much room is left BEFORE tapping
  // "Add event" (Task #3730 only locked the form itself). The server counts
  // events per calendar, so we compare the cap against THIS calendar's
  // `events_count`. Everything fails OPEN: `events_count` defaults to 0 until
  // the calendar loads, `numericLimit` is null until plan data resolves, and
  // `isQuotaReached` returns false until then — so a fully-loaded finite cap is
  // the only thing that flips the UI to a locked state. Unlimited plans
  // (cap -1) or plans without the key show nothing.
  const isOwner = !!cal?.is_owner;
  const eventsUsed = cal?.events_count ?? 0;
  const eventCap = plan.numericLimit("max_calendar_events");
  const showEventQuota = isOwner && eventCap != null && eventCap >= 0;
  const eventQuotaReached =
    isOwner && plan.isQuotaReached("max_calendar_events", eventsUsed);
  const eventLockMessage =
    eventCap != null && eventCap >= 0
      ? `You've reached the ${eventCap}-event limit for this calendar on your current plan. Upgrade to add more events.`
      : "You've reached this calendar's event limit on your current plan. Upgrade to add more events.";

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
      showAlert(
        "Couldn't delete event",
        (e as { message?: string })?.message ?? "Please try again.",
      ),
  });

  const confirmDeleteEvent = (event: CalendarEventItem) => {
    showAlert("Delete event?", `"${event.title}" will be removed. This can't be undone.`, [
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

  // Every dated event on screen is a candidate for re-syncing; the sync itself
  // only touches copies the user already added (matched on the UID marker).
  const syncable = events.filter((e) => e.start_at);

  // Apply a detection result to local state: the resolved ids (or null when
  // unknown) plus whether the "unknown" is specifically a missing-permission
  // case worth hinting about.
  const applyDetection = (
    result: Awaited<ReturnType<typeof detectSavedDeviceEvents>>,
  ) => {
    if (result.status === "ready") {
      setSavedIds(result.savedIds);
      setSavedDetectionBlocked(false);
    } else if (result.status === "needs-permission") {
      setSavedIds(null);
      setSavedDetectionBlocked(true);
    } else {
      // Web / no expo-calendar — detection genuinely unavailable, no hint.
      setSavedIds(null);
      setSavedDetectionBlocked(false);
    }
  };

  // Re-read which events are already on the device calendar. Used on load and
  // after every add/remove/bulk action so each event shows the right action.
  const refreshSavedState = async () => {
    applyDetection(await detectSavedDeviceEvents(events));
  };

  // Detect saved state up front whenever the loaded event set changes (its ids
  // form a stable key so this doesn't re-run on every render).
  const eventKey = events.map((e) => e.id).join(",");
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      const result = await detectSavedDeviceEvents(events);
      if (!cancelled) applyDetection(result);
    })();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [eventKey]);

  // Ask for calendar access (or send the user to Settings if previously denied),
  // then re-run detection so already-saved events show as "Saved" right away.
  const enableSavedDetection = async () => {
    setRequestingAccess(true);
    try {
      const result = await requestCalendarAccessForDetection();
      if (result.status === "granted") {
        await refreshSavedState();
      } else if (result.status === "denied" && result.openedSettings) {
        showAlert(
          "Calendar access needed",
          "Enable calendar access for this app in Settings, then return here to see which events you've already saved.",
        );
      }
    } finally {
      setRequestingAccess(false);
    }
  };

  const addToCalendar = async (event: CalendarEventItem) => {
    setAddingId(event.id);
    try {
      await addEventWithFeedback(event);
    } finally {
      setAddingId(null);
      void refreshSavedState();
    }
  };

  const removeFromCalendar = async (event: CalendarEventItem) => {
    setRemovingId(event.id);
    try {
      await removeEventWithFeedback(event);
    } finally {
      setRemovingId(null);
      void refreshSavedState();
    }
  };

  const addAllUpcoming = async () => {
    if (upcomingAddable.length === 0) return;
    setBulkAdding(true);
    try {
      await addEventsWithFeedback(upcomingAddable);
    } finally {
      setBulkAdding(false);
      void refreshSavedState();
    }
  };

  const refreshAdded = async () => {
    if (syncable.length === 0) return;
    setSyncing(true);
    try {
      await syncEventsWithFeedback(syncable);
    } finally {
      setSyncing(false);
      void refreshSavedState();
    }
  };

  const removeAllAdded = async () => {
    if (syncable.length === 0) return;
    setBulkRemoving(true);
    try {
      await removeEventsWithFeedback(syncable);
    } finally {
      setBulkRemoving(false);
      void refreshSavedState();
    }
  };

  const confirmRemoveAll = () => {
    if (syncable.length === 0) return;
    showAlert(
      "Remove all from my calendar?",
      "This deletes every copy of this calendar's events that was added to your device calendar. Your followed calendar stays intact.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Remove all",
          style: "destructive",
          onPress: () => {
            void removeAllAdded();
          },
        },
      ],
    );
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

                {showEventQuota ? (
                  <View style={styles.quotaRow}>
                    <Feather
                      name={eventQuotaReached ? "alert-triangle" : "bar-chart-2"}
                      size={13}
                      color={eventQuotaReached ? colors.primary : colors.mutedForeground}
                    />
                    <Text
                      style={[
                        styles.quotaText,
                        {
                          color: eventQuotaReached
                            ? colors.primary
                            : colors.mutedForeground,
                        },
                      ]}
                    >
                      {eventsUsed} / {eventCap} event{eventCap === 1 ? "" : "s"} used
                    </Text>
                    {eventQuotaReached ? <UpgradeLockBadge /> : null}
                  </View>
                ) : null}

                {cal.is_owner ? (
                  <View style={styles.ownerActions}>
                    <Button
                      label={eventQuotaReached ? "Event limit reached" : "Add event"}
                      onPress={() =>
                        eventQuotaReached
                          ? showUpgradePrompt({ message: eventLockMessage })
                          : router.push({
                              pathname: "/calendars/event",
                              params: { calendar: String(id) },
                            })
                      }
                      style={{ flex: 1 }}
                      leading={
                        <Feather
                          name={eventQuotaReached ? "lock" : "plus"}
                          size={16}
                          color={colors.primaryForeground}
                        />
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

                {syncable.length > 0 ? (
                  <Pressable
                    onPress={refreshAdded}
                    disabled={syncing}
                    style={[styles.syncBtn, { borderColor: accent, borderRadius: colors.radius }]}
                  >
                    {syncing ? (
                      <ActivityIndicator size="small" color={accent} />
                    ) : (
                      <Feather name="refresh-cw" size={14} color={accent} />
                    )}
                    <Text style={[styles.syncBtnText, { color: accent }]}>
                      Refresh added events
                    </Text>
                  </Pressable>
                ) : null}

                {syncable.length > 0 ? (
                  <Pressable
                    onPress={confirmRemoveAll}
                    disabled={bulkRemoving}
                    style={[styles.syncBtn, { borderColor: colors.destructive, borderRadius: colors.radius }]}
                  >
                    {bulkRemoving ? (
                      <ActivityIndicator size="small" color={colors.destructive} />
                    ) : (
                      <Feather name="trash-2" size={14} color={colors.destructive} />
                    )}
                    <Text style={[styles.syncBtnText, { color: colors.destructive }]}>
                      Remove all from my calendar
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

              {savedDetectionBlocked && !hintDismissed && syncable.length > 0 ? (
                <View
                  style={[
                    styles.permHint,
                    { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                  ]}
                >
                  <Pressable
                    onPress={enableSavedDetection}
                    disabled={requestingAccess}
                    hitSlop={6}
                    style={styles.permHintMain}
                  >
                    {requestingAccess ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : (
                      <Feather name="info" size={16} color={colors.primary} />
                    )}
                    <Text style={[styles.permHintText, { color: colors.foreground }]}>
                      Allow calendar access to see which events you&apos;ve already saved.
                    </Text>
                  </Pressable>
                  <Pressable
                    onPress={() => setHintDismissed(true)}
                    hitSlop={10}
                    style={styles.permHintClose}
                  >
                    <Feather name="x" size={16} color={colors.mutedForeground} />
                  </Pressable>
                </View>
              ) : null}

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
                  <View style={styles.deviceCalRow}>
                    {savedIds?.has(String(item.id)) ? (
                      <>
                        <View
                          style={[
                            styles.savedBadge,
                            { backgroundColor: accent + "22", borderRadius: colors.radius },
                          ]}
                        >
                          <Feather name="check-circle" size={14} color={accent} />
                          <Text style={[styles.savedBadgeText, { color: accent }]}>
                            Saved to your calendar
                          </Text>
                        </View>
                        <Pressable
                          onPress={() => removeFromCalendar(item)}
                          disabled={addingId === item.id || removingId === item.id}
                          hitSlop={6}
                          style={[
                            styles.addBtn,
                            { borderColor: colors.border, borderRadius: colors.radius },
                          ]}
                        >
                          {removingId === item.id ? (
                            <ActivityIndicator size="small" color={colors.mutedForeground} />
                          ) : (
                            <Feather name="x-circle" size={14} color={colors.mutedForeground} />
                          )}
                          <Text style={[styles.addBtnText, { color: colors.mutedForeground }]}>
                            Remove
                          </Text>
                        </Pressable>
                      </>
                    ) : (
                      <Pressable
                        onPress={() => addToCalendar(item)}
                        disabled={addingId === item.id || removingId === item.id}
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
                    )}
                  </View>
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
  quotaRow: { flexDirection: "row", alignItems: "center", gap: 8, flexWrap: "wrap" },
  quotaText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
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
  syncBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
  },
  syncBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  pastToggle: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
  },
  pastToggleText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  permHint: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingLeft: 14,
    paddingRight: 8,
    paddingVertical: 10,
    borderWidth: 1,
  },
  permHintMain: { flex: 1, flexDirection: "row", alignItems: "center", gap: 10 },
  permHintText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13, flex: 1, lineHeight: 18 },
  permHintClose: { padding: 4 },
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
  deviceCalRow: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 8 },
  addBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    alignSelf: "flex-start",
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderWidth: 1,
  },
  addBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  savedBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  savedBadgeText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
});
