import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery } from "@tanstack/react-query";
import * as Clipboard from "expo-clipboard";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  createCalendarEvent,
  extractEventFromUrl,
  listCalendars,
} from "@/lib/api/calendars";
import { createLink, type Link } from "@/lib/api/links";
import { createQrCode } from "@/lib/api/qr";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

// "Import from URL" shortcut screen (mirrors the browser extension's Quick
// QR / Add to Calendar / Shorten flows). Reached via deep link —
// sayzio://import-url?url=... or https://sayzio.app/import-url?url=... —
// e.g. from the iOS/Android share sheet through a Shortcuts automation, or
// with no params at all (the user can paste a URL). All three actions call
// the exact same API helpers the rest of the app (and the extension's
// endpoints) use: POST /qr-codes, POST /calendars/{id}/events, POST /links.

type Mode = "pick" | "qr" | "calendar" | "shorten";

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;

function hostOf(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, "");
  } catch {
    return "";
  }
}

function normalizeUrl(raw: string): string | null {
  const t = raw.trim();
  if (!t) return null;
  const withScheme = /^https?:\/\//i.test(t) ? t : `https://${t}`;
  try {
    const u = new URL(withScheme);
    if (!u.hostname.includes(".")) return null;
    return u.toString();
  } catch {
    return null;
  }
}

function todayDate(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Split an ISO datetime into local YYYY-MM-DD + HH:MM parts. */
function isoToLocalParts(iso: string): { date: string; time: string } | null {
  const d = new Date(iso);
  if (isNaN(d.getTime())) return null;
  const pad = (n: number) => String(n).padStart(2, "0");
  return {
    date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
    time: `${pad(d.getHours())}:${pad(d.getMinutes())}`,
  };
}

export default function ImportUrlScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ url?: string; title?: string }>();
  const sharedUrl = useMemo(
    () => normalizeUrl(typeof params.url === "string" ? params.url : ""),
    [params.url],
  );
  const sharedTitle = typeof params.title === "string" ? params.title : "";

  const [manualUrl, setManualUrl] = useState("");
  const [mode, setMode] = useState<Mode>("pick");

  // --- Clipboard suggestion ---
  // When opened without a url param, offer a one-tap "Use copied link"
  // chip if the clipboard already holds a valid URL. Best-effort: any
  // platform permission gate or failure silently skips the suggestion.
  const [clipboardUrl, setClipboardUrl] = useState<string | null>(null);
  const [clipboardDismissed, setClipboardDismissed] = useState(false);
  useEffect(() => {
    if (sharedUrl) return;
    let cancelled = false;
    (async () => {
      try {
        const has = await Clipboard.hasStringAsync();
        if (!has || cancelled) return;
        const text = await Clipboard.getStringAsync();
        if (cancelled) return;
        const normalized = normalizeUrl(text);
        if (normalized) setClipboardUrl(normalized);
      } catch {
        // Clipboard read denied/unavailable — manual entry still works.
      }
    })();
    return () => {
      cancelled = true;
    };
    // Run once on mount for the manual-entry path.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const url = sharedUrl ?? normalizeUrl(manualUrl);
  const host = url ? hostOf(url) : "";
  const defaultName = sharedTitle || host;

  // --- Quick QR state ---
  const [qrName, setQrName] = useState<string | null>(null);

  const qrMutation = useMutation({
    mutationFn: () =>
      createQrCode({
        name: (qrName ?? defaultName) || "Shared link",
        type: "url",
        payload: { url: url! },
      }),
    onSuccess: () => {
      Alert.alert("QR code created", "Find it in your QR Codes.", [
        { text: "View QR codes", onPress: () => router.replace("/qr") },
        { text: "Done", onPress: () => router.back() },
      ]);
    },
    onError: (e) => {
      if (!handlePlanLockedError(e)) {
        Alert.alert("Couldn't create QR code", messageOf(e));
      }
    },
  });

  // --- Add to calendar state ---
  const calendarsQ = useQuery({
    queryKey: ["calendars"],
    queryFn: listCalendars,
    enabled: mode === "calendar",
  });
  const ownedCalendars = (calendarsQ.data ?? []).filter((c) => c.is_owner);
  const [calendarId, setCalendarId] = useState<number | null>(null);
  const [evTitle, setEvTitle] = useState<string | null>(null);
  const [evDate, setEvDate] = useState(todayDate());
  const [evTime, setEvTime] = useState("09:00");
  const [evLocation, setEvLocation] = useState<string | null>(null);
  const [evErrors, setEvErrors] = useState<Record<string, string>>({});

  // Detect event details from the shared page (title/date/location), the
  // same way the browser extension scrapes JSON-LD/microdata/OG before
  // opening Add to Calendar — just via a server-side fetch. Best-effort:
  // any failure silently leaves the manual fields as they are.
  const extractQ = useQuery({
    queryKey: ["event-extract", url],
    queryFn: () => extractEventFromUrl(url!),
    enabled: mode === "calendar" && !!url,
    staleTime: Infinity,
    retry: false,
  });
  const prefilledRef = useRef(false);
  const prefilledUrlRef = useRef<string | null>(null);
  useEffect(() => {
    // A different URL (manual edits) may extract different details —
    // allow one fresh prefill pass for it.
    if (prefilledUrlRef.current !== null && prefilledUrlRef.current !== url) {
      prefilledRef.current = false;
    }
  }, [url]);
  useEffect(() => {
    const ev = extractQ.data;
    if (!ev || prefilledRef.current) return;
    prefilledRef.current = true;
    // Only fill fields the user hasn't already touched.
    if (ev.title && evTitle === null) setEvTitle(ev.title);
    if (ev.location && evLocation === null) setEvLocation(ev.location);
    if (ev.start_at) {
      const parts = isoToLocalParts(ev.start_at);
      if (parts) {
        if (evDate === todayDate()) setEvDate(parts.date);
        if (evTime === "09:00") setEvTime(parts.time);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [extractQ.data]);

  const selectedCalendarId =
    calendarId ?? (ownedCalendars.length === 1 ? ownedCalendars[0]!.id : null);

  const eventMutation = useMutation({
    mutationFn: () =>
      createCalendarEvent(selectedCalendarId!, {
        title: (evTitle ?? sharedTitle) || host || "Shared link",
        // Keep the source URL with the event — the API has no dedicated
        // URL field, so it travels in the description like the web flow.
        description: url!,
        start_at: `${evDate}T${evTime}`,
        location: evLocation?.trim() || null,
      }),
    onSuccess: (ev) => {
      Alert.alert("Event added", `"${ev.title}" was added to your calendar.`, [
        {
          text: "View calendar",
          onPress: () => router.replace(`/calendars/${ev.calendar_id}`),
        },
        { text: "Done", onPress: () => router.back() },
      ]);
    },
    onError: (e) => {
      if (!handlePlanLockedError(e)) {
        Alert.alert("Couldn't add event", messageOf(e));
      }
    },
  });

  const submitEvent = () => {
    const errs: Record<string, string> = {};
    if (!selectedCalendarId) errs.calendar = "Pick a calendar";
    if (!DATE_RE.test(evDate)) errs.date = "Use YYYY-MM-DD";
    if (!TIME_RE.test(evTime)) errs.time = "Use HH:MM (24h)";
    setEvErrors(errs);
    if (Object.keys(errs).length === 0) eventMutation.mutate();
  };

  // --- Shorten state ---
  const [shortened, setShortened] = useState<Link | null>(null);
  const [copied, setCopied] = useState(false);

  const shortenMutation = useMutation({
    mutationFn: () =>
      createLink({
        type: "short",
        long_url: url!,
        title: sharedTitle || null,
      }),
    onSuccess: (link) => setShortened(link),
    onError: (e) => {
      if (!handlePlanLockedError(e)) {
        Alert.alert("Couldn't shorten link", messageOf(e));
      }
    },
  });

  const copyShort = async () => {
    if (!shortened) return;
    await Clipboard.setStringAsync(shortened.short_url);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const actions: Array<{
    key: Mode;
    icon: keyof typeof Feather.glyphMap;
    label: string;
    hint: string;
  }> = [
    { key: "qr", icon: "grid", label: "Create QR", hint: "Turn this URL into a QR code" },
    { key: "calendar", icon: "calendar", label: "Add to calendar", hint: "Save it as a calendar event" },
    { key: "shorten", icon: "link", label: "Shorten link", hint: "Get a short, trackable link" },
  ];

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={styles.container}
      keyboardShouldPersistTaps="handled"
    >
      <Stack.Screen options={{ title: "Import from URL", headerBackTitle: "Back" }} />

      {!sharedUrl ? (
        <TextField
          label="URL"
          placeholder="https://example.com/page"
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="url"
          value={manualUrl}
          onChangeText={(t) => {
            setManualUrl(t);
            setShortened(null);
          }}
          error={
            manualUrl.trim() && !url ? "Enter a valid web address" : undefined
          }
        />
      ) : null}

      {!sharedUrl &&
      clipboardUrl &&
      !clipboardDismissed &&
      !manualUrl.trim() ? (
        <View
          style={[
            styles.clipChip,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <Pressable
            onPress={() => {
              setManualUrl(clipboardUrl);
              setShortened(null);
              setClipboardDismissed(true);
            }}
            style={styles.clipChipMain}
            accessibilityRole="button"
            accessibilityLabel={`Use copied link ${hostOf(clipboardUrl)}`}
          >
            <Feather name="clipboard" size={15} color={colors.primary} />
            <Text
              style={[styles.clipChipText, { color: colors.text }]}
              numberOfLines={1}
            >
              Use copied link: {hostOf(clipboardUrl)}
            </Text>
          </Pressable>
          <Pressable
            onPress={() => setClipboardDismissed(true)}
            hitSlop={8}
            accessibilityRole="button"
            accessibilityLabel="Dismiss copied link suggestion"
          >
            <Feather name="x" size={16} color={colors.muted} />
          </Pressable>
        </View>
      ) : null}

      {sharedUrl ? (
        <View
          style={[
            styles.urlCard,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          {sharedTitle ? (
            <Text style={[styles.urlTitle, { color: colors.text }]} numberOfLines={2}>
              {sharedTitle}
            </Text>
          ) : null}
          <Text style={[styles.urlText, { color: colors.muted }]} numberOfLines={3}>
            {sharedUrl}
          </Text>
        </View>
      ) : null}

      <Text style={[styles.sectionLabel, { color: colors.muted }]}>
        What do you want to do with it?
      </Text>

      {actions.map((a) => {
        const active = mode === a.key;
        return (
          <Pressable
            key={a.key}
            disabled={!url}
            onPress={() => setMode(active ? "pick" : a.key)}
            style={[
              styles.action,
              {
                backgroundColor: colors.card,
                borderColor: active ? colors.primary : colors.border,
                opacity: url ? 1 : 0.5,
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel={a.label}
          >
            <View
              style={[styles.actionIcon, { backgroundColor: colors.primary + "22" }]}
            >
              <Feather name={a.icon} size={18} color={colors.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={[styles.actionLabel, { color: colors.text }]}>{a.label}</Text>
              <Text style={[styles.actionHint, { color: colors.muted }]}>{a.hint}</Text>
            </View>
            <Feather
              name={active ? "chevron-up" : "chevron-right"}
              size={18}
              color={colors.muted}
            />
          </Pressable>
        );
      })}

      {mode === "qr" && url ? (
        <View style={styles.panel}>
          <TextField
            label="QR code name"
            value={qrName ?? defaultName}
            onChangeText={setQrName}
            placeholder="My QR code"
          />
          <Button
            label={qrMutation.isPending ? "Creating…" : "Create QR code"}
            onPress={() => qrMutation.mutate()}
            disabled={qrMutation.isPending}
          />
        </View>
      ) : null}

      {mode === "calendar" && url ? (
        <View style={styles.panel}>
          {calendarsQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : ownedCalendars.length === 0 ? (
            <View>
              <Text style={{ color: colors.muted, marginBottom: 12 }}>
                You don't have a calendar yet. Create one first, then share
                the URL again.
              </Text>
              <Button
                label="Open Calendars"
                variant="secondary"
                onPress={() => router.push("/calendars")}
              />
            </View>
          ) : (
            <>
              {extractQ.isFetching ? (
                <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 10 }}>
                  <ActivityIndicator size="small" color={colors.primary} />
                  <Text style={{ color: colors.muted, fontSize: 12 }}>
                    Detecting event details…
                  </Text>
                </View>
              ) : extractQ.data && extractQ.data.source !== "title" ? (
                <Text style={{ color: colors.muted, fontSize: 12, marginBottom: 10 }}>
                  Event details detected from the page — review and adjust below.
                </Text>
              ) : null}
              {ownedCalendars.length > 1 ? (
                <View style={{ marginBottom: 8 }}>
                  <Text style={[styles.fieldLabel, { color: colors.text }]}>
                    Calendar
                  </Text>
                  {ownedCalendars.map((c) => {
                    const sel = selectedCalendarId === c.id;
                    return (
                      <Pressable
                        key={c.id}
                        onPress={() => setCalendarId(c.id)}
                        style={[
                          styles.calRow,
                          {
                            backgroundColor: colors.card,
                            borderColor: sel ? colors.primary : colors.border,
                          },
                        ]}
                      >
                        <Feather
                          name={sel ? "check-circle" : "circle"}
                          size={16}
                          color={sel ? colors.primary : colors.muted}
                        />
                        <Text style={{ color: colors.text, marginLeft: 8, flex: 1 }}>
                          {c.title}
                        </Text>
                      </Pressable>
                    );
                  })}
                  {evErrors.calendar ? (
                    <Text style={{ color: colors.destructive, fontSize: 12 }}>
                      {evErrors.calendar}
                    </Text>
                  ) : null}
                </View>
              ) : null}
              <TextField
                label="Event title"
                value={evTitle ?? (sharedTitle || host)}
                onChangeText={setEvTitle}
                placeholder="Event title"
              />
              <TextField
                label="Date"
                value={evDate}
                onChangeText={setEvDate}
                placeholder="YYYY-MM-DD"
                autoCapitalize="none"
                error={evErrors.date}
              />
              <TextField
                label="Time"
                value={evTime}
                onChangeText={setEvTime}
                placeholder="HH:MM"
                autoCapitalize="none"
                error={evErrors.time}
              />
              <TextField
                label="Location (optional)"
                value={evLocation ?? ""}
                onChangeText={setEvLocation}
                placeholder="Address or venue name"
              />
              <Text style={{ color: colors.muted, fontSize: 12, marginBottom: 12 }}>
                The shared URL is saved in the event description.
              </Text>
              <Button
                label={eventMutation.isPending ? "Adding…" : "Add event"}
                onPress={submitEvent}
                disabled={eventMutation.isPending}
              />
            </>
          )}
        </View>
      ) : null}

      {mode === "shorten" && url ? (
        <View style={styles.panel}>
          {shortened ? (
            <View
              style={[
                styles.urlCard,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              <Text style={[styles.urlTitle, { color: colors.text }]}>
                {shortened.short_url}
              </Text>
              <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                <Button
                  label={copied ? "Copied!" : "Copy"}
                  variant="secondary"
                  onPress={copyShort}
                />
                <Button
                  label="View link"
                  variant="secondary"
                  onPress={() => router.replace(`/links/${shortened.id}` as any)}
                />
              </View>
            </View>
          ) : (
            <Button
              label={shortenMutation.isPending ? "Shortening…" : "Shorten link"}
              onPress={() => shortenMutation.mutate()}
              disabled={shortenMutation.isPending}
            />
          )}
        </View>
      ) : null}
    </ScrollView>
  );
}

function messageOf(e: unknown): string {
  return e instanceof Error && e.message ? e.message : "Something went wrong.";
}

const styles = StyleSheet.create({
  container: { padding: 16, paddingBottom: 48 },
  urlCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
  },
  urlTitle: { fontSize: 15, fontWeight: "600" },
  clipChip: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 999,
    paddingVertical: 8,
    paddingHorizontal: 12,
    marginBottom: 8,
    gap: 8,
  },
  clipChipMain: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flex: 1,
  },
  clipChipText: { fontSize: 13, fontWeight: "600", flexShrink: 1 },
  urlText: { fontSize: 13, marginTop: 4 },
  sectionLabel: {
    fontSize: 13,
    fontWeight: "600",
    marginTop: 16,
    marginBottom: 8,
  },
  action: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    gap: 12,
  },
  actionIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  actionLabel: { fontSize: 15, fontWeight: "600" },
  actionHint: { fontSize: 12, marginTop: 2 },
  panel: { marginTop: 4, marginBottom: 16 },
  fieldLabel: { fontSize: 13, fontWeight: "600", marginBottom: 6 },
  calRow: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
    marginBottom: 6,
  },
});
