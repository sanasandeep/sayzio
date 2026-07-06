import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { MapPickerModal, type PickedPoint } from "@/components/MapPickerModal";
import { TextField } from "@/components/TextField";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import {
  createCalendarEvent,
  deleteCalendarEvent,
  getCalendar,
  updateCalendarEvent,
  type CalendarEventInput,
  type CalendarEventItem,
} from "@/lib/api/calendars";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;

/**
 * Split an ISO datetime into "YYYY-MM-DD" + "HH:MM" wall-clock parts *in the
 * calendar's timezone*. The backend parses the values we send back as
 * wall-clock in that same timezone, so we must read them out in it too —
 * using the device-local clock here would silently shift the hour/date on
 * edit whenever the phone's timezone differs from the calendar's.
 */
function splitIso(iso: string | null, tz: string): { date: string; time: string } {
  if (!iso) return { date: "", time: "" };
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return { date: "", time: "" };
  try {
    const parts = new Intl.DateTimeFormat("en-CA", {
      timeZone: tz,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    }).formatToParts(d);
    const get = (t: string) => parts.find((p) => p.type === t)?.value ?? "";
    let hour = get("hour");
    if (hour === "24") hour = "00"; // some engines emit "24" for midnight
    return {
      date: `${get("year")}-${get("month")}-${get("day")}`,
      time: `${hour}:${get("minute")}`,
    };
  } catch {
    const pad = (n: number) => String(n).padStart(2, "0");
    return {
      date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
      time: `${pad(d.getHours())}:${pad(d.getMinutes())}`,
    };
  }
}

export default function CalendarEventScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const plan = usePlanFeatures();
  const params = useLocalSearchParams<{ calendar?: string; id?: string }>();
  const calendarId = params.calendar ? Number(params.calendar) : NaN;
  const eventId = params.id ? Number(params.id) : null;
  const isEdit = eventId != null && Number.isFinite(eventId);

  // We need the calendar (for its timezone + to find the event when editing).
  const calQ = useQuery({
    queryKey: ["calendar", calendarId, true],
    queryFn: () => getCalendar(calendarId, { past: true }),
    enabled: Number.isFinite(calendarId),
  });

  // Proactively lock NEW event creation when this calendar has exhausted the
  // current plan's per-calendar event allowance (`max_calendar_events`), so an
  // owner sees an upgrade affordance instead of filling in the whole form and
  // only getting bounced by the server 402. The server counts events per
  // calendar (`$cal->events()->count() >= $cap`), so we compare the cap against
  // THIS calendar's `events_count`. Editing an existing event is never gated
  // (it adds nothing), and everything fails OPEN — the count defaults to 0
  // until the calendar loads and `isQuotaReached` returns false until plan data
  // resolves, so we never block an owner who still has room.
  const eventsUsed = calQ.data?.calendar.events_count ?? 0;
  const createLocked =
    !isEdit && plan.isQuotaReached("max_calendar_events", eventsUsed);
  const eventCap = plan.numericLimit("max_calendar_events");

  const existing: CalendarEventItem | undefined = useMemo(
    () =>
      isEdit
        ? calQ.data?.events.find((e) => e.id === eventId)
        : undefined,
    [isEdit, calQ.data, eventId],
  );

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [startDate, setStartDate] = useState("");
  const [startTime, setStartTime] = useState("09:00");
  const [endDate, setEndDate] = useState("");
  const [endTime, setEndTime] = useState("");
  const [allDay, setAllDay] = useState(false);
  const [location, setLocation] = useState("");
  const [lat, setLat] = useState<number | null>(null);
  const [lng, setLng] = useState<number | null>(null);
  const [hashtags, setHashtags] = useState("");
  const [paymentUrl, setPaymentUrl] = useState("");
  const [mapOpen, setMapOpen] = useState(false);
  const [seeded, setSeeded] = useState(false);

  useEffect(() => {
    if (!isEdit || seeded || !existing) return;
    // Use the event's own timezone, falling back to the calendar's, so the
    // wall-clock we show matches what the backend will re-parse on save.
    const tz =
      existing.timezone || calQ.data?.calendar.timezone || "UTC";
    const s = splitIso(existing.start_at, tz);
    const e = splitIso(existing.end_at, tz);
    setTitle(existing.title ?? "");
    setDescription(existing.description ?? "");
    setStartDate(s.date);
    setStartTime(s.time || "09:00");
    setEndDate(e.date);
    setEndTime(e.time);
    setAllDay(!!existing.all_day);
    setLocation(existing.location ?? "");
    setLat(existing.lat);
    setLng(existing.lng);
    setHashtags((existing.hashtags ?? []).join(" "));
    setPaymentUrl(existing.payment_url ?? "");
    setSeeded(true);
  }, [isEdit, seeded, existing]);

  const dateError = startDate.trim() && !DATE_RE.test(startDate.trim());
  const startTimeError = !allDay && startTime.trim() && !TIME_RE.test(startTime.trim());
  const endDateError = endDate.trim() && !DATE_RE.test(endDate.trim());
  const endTimeError = !allDay && endTime.trim() && !TIME_RE.test(endTime.trim());

  const canSave =
    title.trim().length > 0 &&
    DATE_RE.test(startDate.trim()) &&
    (allDay || TIME_RE.test(startTime.trim())) &&
    !endDateError &&
    !endTimeError;

  function buildPayload(): CalendarEventInput {
    const start = allDay
      ? `${startDate.trim()}T00:00`
      : `${startDate.trim()}T${startTime.trim()}`;
    let end: string | null = null;
    const ed = endDate.trim() || startDate.trim();
    if (allDay && endDate.trim()) {
      end = `${ed}T23:59`;
    } else if (!allDay && endTime.trim() && TIME_RE.test(endTime.trim())) {
      end = `${ed}T${endTime.trim()}`;
    }
    return {
      title: title.trim(),
      description: description.trim() || null,
      start_at: start,
      end_at: end,
      all_day: allDay,
      // Send the same timezone the wall-clock fields were shown in, so editing
      // an event keeps its time stable (the backend re-parses in this zone).
      timezone:
        (isEdit ? existing?.timezone : null) ||
        calQ.data?.calendar.timezone ||
        null,
      location: location.trim() || null,
      lat,
      lng,
      hashtags: hashtags.trim() || null,
      payment_url: paymentUrl.trim() || null,
    };
  }

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["calendar", calendarId] });
    qc.invalidateQueries({ queryKey: ["calendars"] });
    qc.invalidateQueries({ queryKey: ["my-calendar"] });
    qc.invalidateQueries({ queryKey: ["my-calendar-today"] });
  };

  const save = useMutation({
    mutationFn: () =>
      isEdit
        ? updateCalendarEvent(calendarId, eventId as number, buildPayload())
        : createCalendarEvent(calendarId, buildPayload()),
    onSuccess: () => {
      invalidate();
      router.back();
    },
    onError: (e) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert(
        "Couldn't save event",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    },
  });

  const remove = useMutation({
    mutationFn: () => deleteCalendarEvent(calendarId, eventId as number),
    onSuccess: () => {
      invalidate();
      router.back();
    },
    onError: (e) =>
      Alert.alert(
        "Couldn't delete event",
        (e as { message?: string })?.message ?? "Please try again.",
      ),
  });

  const confirmDelete = () => {
    Alert.alert("Delete event?", "This can't be undone.", [
      { text: "Cancel", style: "cancel" },
      { text: "Delete", style: "destructive", onPress: () => remove.mutate() },
    ]);
  };

  const onPick = (p: PickedPoint) => {
    setLat(p.lat);
    setLng(p.lng);
    if (p.address && !location.trim()) setLocation(p.address);
    setMapOpen(false);
  };

  if (isEdit && calQ.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit event" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  // Edit target resolved but the event isn't in the calendar (deleted, wrong
  // id, or a calendar the user can no longer see) — show an explicit error
  // instead of a confusing blank form.
  if (isEdit && (calQ.isError || !existing)) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "Edit event", headerBackTitle: "Back" }} />
        <View style={[styles.center, { paddingHorizontal: 24, gap: 16 }]}>
          <Feather name="alert-circle" size={32} color={colors.mutedForeground} />
          <Text style={[styles.notFoundText, { color: colors.mutedForeground }]}>
            This event couldn't be found. It may have been deleted.
          </Text>
          <Button label="Go back" variant="outline" onPress={() => router.back()} />
        </View>
      </View>
    );
  }

  if (createLocked) {
    const message =
      eventCap != null && eventCap >= 0
        ? `You've reached the ${eventCap}-event limit for this calendar on your current plan. Upgrade to add more events.`
        : "You've reached this calendar's event limit on your current plan. Upgrade to add more events.";
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "New event", headerBackTitle: "Back" }} />
        <ScrollView
          contentContainerStyle={{ padding: 20, gap: 18 }}
          keyboardShouldPersistTaps="handled"
        >
          <View
            style={[
              styles.lockCard,
              {
                backgroundColor: colors.primary + "12",
                borderColor: colors.primary + "44",
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={styles.lockHead}>
              <View
                style={[styles.lockIcon, { backgroundColor: colors.primary + "26" }]}
              >
                <Feather name="calendar" size={20} color={colors.primary} />
              </View>
              <UpgradeLockBadge />
            </View>
            <Text style={[styles.lockTitle, { color: colors.foreground }]}>
              Event limit reached
            </Text>
            <Text style={[styles.lockBody, { color: colors.mutedForeground }]}>
              {message}
            </Text>
            <Button label="See plans" onPress={() => showUpgradePrompt({ message })} />
          </View>
        </ScrollView>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: isEdit ? "Edit event" : "New event", headerBackTitle: "Back" }}
      />
      <ScrollView
        contentContainerStyle={{ padding: 16, gap: 18, paddingBottom: 48 }}
        keyboardShouldPersistTaps="handled"
      >
        <TextField
          label="Title"
          value={title}
          onChangeText={setTitle}
          placeholder="Event name"
          maxLength={160}
        />

        <TextField
          label="Description"
          value={description}
          onChangeText={setDescription}
          placeholder="Details (optional)"
          multiline
          numberOfLines={3}
          maxLength={5000}
          style={{ minHeight: 90, paddingTop: 14, textAlignVertical: "top" }}
        />

        <View
          style={[
            styles.toggleRow,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <Text style={[styles.toggleTitle, { color: colors.foreground }]}>All-day event</Text>
          <Switch value={allDay} onValueChange={setAllDay} trackColor={{ true: colors.primary }} />
        </View>

        <View style={styles.fieldRow}>
          <View style={{ flex: 1.4 }}>
            <TextField
              label="Start date"
              value={startDate}
              onChangeText={setStartDate}
              placeholder="YYYY-MM-DD"
              autoCapitalize="none"
              autoCorrect={false}
              maxLength={10}
              error={dateError ? "Use YYYY-MM-DD" : undefined}
            />
          </View>
          {!allDay ? (
            <View style={{ flex: 1 }}>
              <TextField
                label="Time"
                value={startTime}
                onChangeText={setStartTime}
                placeholder="HH:MM"
                autoCapitalize="none"
                autoCorrect={false}
                maxLength={5}
                error={startTimeError ? "24h HH:MM" : undefined}
              />
            </View>
          ) : null}
        </View>

        <View style={styles.fieldRow}>
          <View style={{ flex: 1.4 }}>
            <TextField
              label="End date"
              value={endDate}
              onChangeText={setEndDate}
              placeholder="Optional"
              autoCapitalize="none"
              autoCorrect={false}
              maxLength={10}
              error={endDateError ? "Use YYYY-MM-DD" : undefined}
            />
          </View>
          {!allDay ? (
            <View style={{ flex: 1 }}>
              <TextField
                label="End time"
                value={endTime}
                onChangeText={setEndTime}
                placeholder="HH:MM"
                autoCapitalize="none"
                autoCorrect={false}
                maxLength={5}
                error={endTimeError ? "24h HH:MM" : undefined}
              />
            </View>
          ) : null}
        </View>

        <View style={{ gap: 8 }}>
          <TextField
            label="Location"
            value={location}
            onChangeText={setLocation}
            placeholder="Venue or address (optional)"
          />
          <Pressable
            onPress={() => setMapOpen(true)}
            style={[styles.mapBtn, { borderColor: colors.border, borderRadius: colors.radius }]}
          >
            <Feather name="map-pin" size={15} color={colors.primary} />
            <Text style={[styles.mapBtnText, { color: colors.primary }]}>
              {lat != null && lng != null ? "Change point on map" : "Pick a point on the map"}
            </Text>
          </Pressable>
          {lat != null && lng != null ? (
            <Pressable
              onPress={() => {
                setLat(null);
                setLng(null);
              }}
              style={styles.clearPin}
            >
              <Feather name="x" size={12} color={colors.mutedForeground} />
              <Text style={[styles.clearPinText, { color: colors.mutedForeground }]}>
                Remove map point ({lat.toFixed(4)}, {lng.toFixed(4)})
              </Text>
            </Pressable>
          ) : null}
        </View>

        <TextField
          label="Hashtags"
          value={hashtags}
          onChangeText={setHashtags}
          placeholder="music live 2026"
          autoCapitalize="none"
          autoCorrect={false}
          hint="Space or comma separated. Used for filtering in the agenda."
        />

        <TextField
          label="Payment / RSVP link"
          value={paymentUrl}
          onChangeText={setPaymentUrl}
          placeholder="https:// (optional)"
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="url"
        />

        <Button
          label={isEdit ? "Save event" : "Add event"}
          onPress={() => save.mutate()}
          loading={save.isPending}
          disabled={!canSave}
        />

        {isEdit ? (
          <Button
            label="Delete event"
            variant="outline"
            onPress={confirmDelete}
            loading={remove.isPending}
            leading={<Feather name="trash-2" size={16} color={colors.destructive} />}
          />
        ) : null}
      </ScrollView>

      <MapPickerModal
        visible={mapOpen}
        initialLat={lat}
        initialLng={lng}
        onClose={() => setMapOpen(false)}
        onPick={onPick}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  notFoundText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    textAlign: "center",
    lineHeight: 21,
  },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 14,
    padding: 16,
    borderWidth: 1,
  },
  toggleTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  fieldRow: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  mapBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderWidth: 1,
  },
  mapBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  clearPin: { flexDirection: "row", alignItems: "center", gap: 6, paddingHorizontal: 2 },
  clearPinText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  lockCard: { gap: 12, padding: 18, borderWidth: 1 },
  lockHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  lockIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  lockTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 18 },
  lockBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
});
