import { Feather } from "@expo/vector-icons";
import { type ReactNode, useEffect, useState } from "react";
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";
import * as WebBrowser from "expo-web-browser";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import {
  type EventInput,
  type OwnerEvent,
} from "@/lib/api/events";

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;

/**
 * Split an ISO datetime into "YYYY-MM-DD" + "HH:MM" wall-clock parts in the
 * event's timezone, so editing keeps the displayed time stable regardless of
 * the phone's own zone (mirrors calendars/event.tsx).
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
    if (hour === "24") hour = "00";
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

export type EventFormProps = {
  /** Existing event when editing; omit for the create flow. */
  initial?: OwnerEvent | null;
  saving?: boolean;
  submitLabel: string;
  onSubmit: (payload: EventInput) => void;
  /**
   * Optional extra content rendered at the bottom of the form's scroll view
   * (below the advanced summary) — used by the edit screen for the cancel /
   * reactivate danger section.
   */
  footer?: ReactNode;
};

/**
 * Shared create/edit form for an organizer's event (the `ics` link
 * essentials). Advanced settings (recurrence, RSVP questions, calendar sync)
 * are web-only — when editing we render a read-only summary of them below the
 * form with an "edit on the web" affordance.
 */
export function EventForm({ initial, saving, submitLabel, onSubmit, footer }: EventFormProps) {
  const colors = useColors();

  const tz = initial?.timezone || "UTC";
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [location, setLocation] = useState("");
  const [startDate, setStartDate] = useState("");
  const [startTime, setStartTime] = useState("09:00");
  const [endDate, setEndDate] = useState("");
  const [endTime, setEndTime] = useState("10:00");
  const [timezone, setTimezone] = useState("UTC");
  const [rsvpEnabled, setRsvpEnabled] = useState(true);
  const [capacity, setCapacity] = useState("");
  const [seeded, setSeeded] = useState(false);

  useEffect(() => {
    if (!initial || seeded) return;
    const s = splitIso(initial.start_date, tz);
    const e = splitIso(initial.end_date, tz);
    setTitle(initial.title ?? "");
    setDescription(initial.description ?? "");
    setLocation(initial.location ?? "");
    setStartDate(s.date);
    setStartTime(s.time || "09:00");
    setEndDate(e.date);
    setEndTime(e.time || "10:00");
    setTimezone(initial.timezone || "UTC");
    setRsvpEnabled(initial.rsvp_enabled);
    setCapacity(initial.capacity != null ? String(initial.capacity) : "");
    setSeeded(true);
  }, [initial, seeded, tz]);

  const startDateError = startDate.trim() ? !DATE_RE.test(startDate.trim()) : false;
  const startTimeError = startTime.trim() ? !TIME_RE.test(startTime.trim()) : false;
  const endDateError = endDate.trim() ? !DATE_RE.test(endDate.trim()) : false;
  const endTimeError = endTime.trim() ? !TIME_RE.test(endTime.trim()) : false;
  const capacityError = capacity.trim() ? !/^\d+$/.test(capacity.trim()) : false;

  const canSave =
    title.trim().length > 0 &&
    DATE_RE.test(startDate.trim()) &&
    TIME_RE.test(startTime.trim()) &&
    DATE_RE.test(endDate.trim() || startDate.trim()) &&
    TIME_RE.test(endTime.trim()) &&
    timezone.trim().length > 0 &&
    !capacityError;

  const submit = () => {
    const ed = endDate.trim() || startDate.trim();
    onSubmit({
      title: title.trim(),
      description: description.trim() || null,
      location: location.trim() || null,
      start_date: `${startDate.trim()}T${startTime.trim()}`,
      end_date: `${ed}T${endTime.trim()}`,
      timezone: timezone.trim(),
      capacity: capacity.trim() ? Number(capacity.trim()) : null,
      rsvp_enabled: rsvpEnabled,
    });
  };

  const openWeb = () => {
    const url = initial?.web_edit_url || `${getBaseUrl()}/user/links`;
    if (Platform.OS === "web") {
      window.location.href = url;
      return;
    }
    WebBrowser.openBrowserAsync(url, {
      toolbarColor: colors.background,
      controlsColor: colors.primary,
    }).catch(() => {});
  };

  const adv = initial?.advanced;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ padding: 16, gap: 18, paddingBottom: 60 }}
      keyboardShouldPersistTaps="handled"
    >
      <TextField
        label="Title"
        value={title}
        onChangeText={setTitle}
        placeholder="Event name"
        maxLength={255}
      />

      <TextField
        label="Description"
        value={description}
        onChangeText={setDescription}
        placeholder="Details (optional)"
        multiline
        numberOfLines={3}
        maxLength={2000}
        style={{ minHeight: 90, paddingTop: 14, textAlignVertical: "top" }}
      />

      <TextField
        label="Location"
        value={location}
        onChangeText={setLocation}
        placeholder="Venue or address (optional)"
        maxLength={500}
      />

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
            error={startDateError ? "Use YYYY-MM-DD" : undefined}
          />
        </View>
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
      </View>

      <View style={styles.fieldRow}>
        <View style={{ flex: 1.4 }}>
          <TextField
            label="End date"
            value={endDate}
            onChangeText={setEndDate}
            placeholder="Same as start"
            autoCapitalize="none"
            autoCorrect={false}
            maxLength={10}
            error={endDateError ? "Use YYYY-MM-DD" : undefined}
          />
        </View>
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
      </View>

      <TextField
        label="Timezone"
        value={timezone}
        onChangeText={setTimezone}
        placeholder="UTC"
        autoCapitalize="none"
        autoCorrect={false}
        maxLength={100}
        hint="IANA name, e.g. America/New_York or Europe/London."
      />

      <View
        style={[
          styles.toggleRow,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <View style={{ flex: 1 }}>
          <Text style={[styles.toggleTitle, { color: colors.foreground }]}>
            Accept RSVPs
          </Text>
          <Text style={[styles.toggleHint, { color: colors.mutedForeground }]}>
            Let guests RSVP to this free event.
          </Text>
        </View>
        <Switch
          value={rsvpEnabled}
          onValueChange={setRsvpEnabled}
          trackColor={{ true: colors.primary }}
        />
      </View>

      {rsvpEnabled ? (
        <TextField
          label="Capacity"
          value={capacity}
          onChangeText={setCapacity}
          placeholder="Unlimited"
          keyboardType="number-pad"
          maxLength={7}
          error={capacityError ? "Numbers only" : undefined}
          hint="Maximum number of RSVPs. Leave blank for unlimited."
        />
      ) : null}

      <Button
        label={submitLabel}
        onPress={submit}
        loading={!!saving}
        disabled={!canSave}
      />

      {adv ? (
        <View
          style={[
            styles.advCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <Text style={[styles.advTitle, { color: colors.foreground }]}>
            Advanced settings
          </Text>
          <AdvRow label="Recurrence" value={adv.recurrence} colors={colors} />
          <AdvRow
            label="RSVP questions"
            value={
              adv.rsvp_question_count > 0
                ? `${adv.rsvp_question_count} custom question${adv.rsvp_question_count === 1 ? "" : "s"}`
                : "None"
            }
            colors={colors}
          />
          <AdvRow
            label="Calendar sync"
            value={
              adv.calendar_sync_mode === "off" || !adv.calendar_sync_mode
                ? "Off"
                : adv.calendar_sync_mode === "keep_in_sync"
                  ? "Keep in sync"
                  : "One-time"
            }
            colors={colors}
          />
          <Text style={[styles.advNote, { color: colors.mutedForeground }]}>
            Edit advanced settings on the web.
          </Text>
          <Pressable onPress={openWeb} style={styles.advLink} hitSlop={8}>
            <Feather name="external-link" size={14} color={colors.primary} />
            <Text style={[styles.advLinkText, { color: colors.primary }]}>
              Open on the web
            </Text>
          </Pressable>
        </View>
      ) : null}

      {footer}
    </ScrollView>
  );
}

function AdvRow({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.advRow}>
      <Text style={[styles.advRowLabel, { color: colors.mutedForeground }]}>
        {label}
      </Text>
      <Text style={[styles.advRowValue, { color: colors.foreground }]}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fieldRow: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 14,
    padding: 16,
    borderWidth: 1,
  },
  toggleTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  toggleHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 2,
  },
  advCard: { gap: 10, padding: 16, borderWidth: 1, marginTop: 4 },
  advTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  advRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    gap: 12,
  },
  advRowLabel: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  advRowValue: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    flexShrink: 1,
    textAlign: "right",
  },
  advNote: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
    marginTop: 2,
  },
  advLink: { flexDirection: "row", alignItems: "center", gap: 6 },
  advLinkText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
});
