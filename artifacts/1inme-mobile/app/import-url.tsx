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
  Share,
  StyleSheet,
  Switch,
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
import { createLink, listLinks, type Link } from "@/lib/api/links";
import { createQrCode } from "@/lib/api/qr";
import {
  getAutoShortenEnabled,
  setAutoShortenEnabled,
} from "@/lib/secure";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

// "Import from URL" shortcut screen (mirrors the browser extension's Quick
// QR / Add to Calendar / Shorten flows). Reached via deep link —
// sayzio://import-url?url=... or https://sayzio.app/import-url?url=... —
// e.g. from the iOS/Android share sheet through a Shortcuts automation, or
// with no params at all (the user can paste a URL). All three actions call
// the exact same API helpers the rest of the app (and the extension's
// endpoints) use: POST /qr-codes, POST /calendars/{id}/events, POST /links.
//
// Share-sheet path: when a URL arrives via the share intent the screen
// auto-shortens it immediately (preference: on by default), shows the result
// with Copy / native Share / View-link, and surfaces a duplicate warning if
// the same destination was shortened recently.

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

  // --- Clipboard suggestion (manual-entry path only) ---
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const url = sharedUrl ?? normalizeUrl(manualUrl);
  const host = url ? hostOf(url) : "";
  const defaultName = sharedTitle || host;

  // ───────────────────────────────────────────────────────────────────
  // Auto-shorten preference (share-sheet path only)
  // ───────────────────────────────────────────────────────────────────
  // null = still loading from SecureStore; true/false = resolved.
  const [autoShortenPref, setAutoShortenPref] = useState<boolean | null>(null);
  useEffect(() => {
    if (!sharedUrl) return;
    getAutoShortenEnabled().then(setAutoShortenPref);
  }, [sharedUrl]);

  const toggleAutoShorten = async (next: boolean) => {
    setAutoShortenPref(next);
    await setAutoShortenEnabled(next);
  };

  // ───────────────────────────────────────────────────────────────────
  // Duplicate detection (share-sheet path only)
  // Search for short links whose long_url exactly matches the shared URL.
  // ───────────────────────────────────────────────────────────────────
  const duplicateQ = useQuery({
    queryKey: ["link-duplicate", sharedUrl],
    queryFn: async () => {
      const list = await listLinks({ type: "short", q: sharedUrl!, per_page: 10 });
      return list.items.find((l) => l.long_url === sharedUrl) ?? null;
    },
    enabled: !!sharedUrl,
    staleTime: 30_000,
    retry: false,
  });

  // ───────────────────────────────────────────────────────────────────
  // QR state (shared between both paths)
  // ───────────────────────────────────────────────────────────────────
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

  // ───────────────────────────────────────────────────────────────────
  // Calendar state (shared between both paths)
  // ───────────────────────────────────────────────────────────────────
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
    if (prefilledUrlRef.current !== null && prefilledUrlRef.current !== url) {
      prefilledRef.current = false;
    }
  }, [url]);
  useEffect(() => {
    const ev = extractQ.data;
    if (!ev || prefilledRef.current) return;
    prefilledRef.current = true;
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

  // ───────────────────────────────────────────────────────────────────
  // Shorten state
  // ───────────────────────────────────────────────────────────────────
  const [shortened, setShortened] = useState<Link | null>(null);
  const [copied, setCopied] = useState(false);
  // Track whether we've already fired the auto-shorten for the current
  // sharedUrl to avoid double-firing on re-renders.
  const autoTriggeredRef = useRef(false);
  // Whether the user chose to use an existing duplicate link.
  const [reusingDuplicate, setReusingDuplicate] = useState(false);

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
        // Error is surfaced inline in the shared-URL path; no Alert needed.
        // (Manual path still sees the panel's error state.)
        if (!sharedUrl) {
          Alert.alert("Couldn't shorten link", messageOf(e));
        }
      }
    },
  });

  // Auto-shorten trigger: fires once per sharedUrl when the preference is
  // loaded, the duplicate check has settled, and no duplicate was found.
  useEffect(() => {
    if (!sharedUrl) return;
    if (autoShortenPref === null) return; // preference still loading
    if (!duplicateQ.isFetched) return; // duplicate check still in flight
    if (autoTriggeredRef.current) return; // already triggered
    if (duplicateQ.data) return; // existing link found — don't auto-shorten
    if (!autoShortenPref) return; // user opted out
    autoTriggeredRef.current = true;
    shortenMutation.mutate();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sharedUrl, autoShortenPref, duplicateQ.isFetched, duplicateQ.data]);

  const copyShort = async (link: Link) => {
    await Clipboard.setStringAsync(link.short_url);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const shareShort = async (link: Link) => {
    try {
      await Share.share({ message: link.short_url });
    } catch {
      // User cancelled or share sheet unavailable — no-op.
    }
  };

  // ───────────────────────────────────────────────────────────────────
  // Other-options disclosure (used in the share-sheet path to keep QR
  // and calendar reachable without cluttering the primary result view)
  // ───────────────────────────────────────────────────────────────────
  const [showOtherOptions, setShowOtherOptions] = useState(false);

  // Canonical action list — all three flows. Used directly on the manual-entry
  // path; the share-sheet path shows only `otherActions` (shorten is handled
  // automatically so it doesn't appear in the secondary disclosure).
  // NOTE: this array name and shape are covered by the test:import-url suite —
  // do not rename without updating that test.
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

  // Share-sheet "Other options" shows only QR and calendar (shorten is automatic).
  const otherActions = actions.filter((a) => a.key !== "shorten");

  // ───────────────────────────────────────────────────────────────────
  // Render helpers
  // ───────────────────────────────────────────────────────────────────

  /** The link to show in the result card: either a newly created one or a
   *  duplicate that the user chose to reuse. */
  const resultLink = shortened ?? (reusingDuplicate ? (duplicateQ.data ?? null) : null);

  /** True while we're waiting for either the pref load, the duplicate check,
   *  or the actual shorten call (only in auto-shorten mode). */
  const isAutoWorking =
    !!sharedUrl &&
    autoShortenPref !== false &&
    !shortenMutation.isError &&
    !resultLink &&
    !duplicateQ.data &&
    !reusingDuplicate &&
    (autoShortenPref === null ||
      !duplicateQ.isFetched ||
      shortenMutation.isPending);

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={styles.container}
      keyboardShouldPersistTaps="handled"
    >
      <Stack.Screen options={{ title: "Import from URL", headerBackTitle: "Back" }} />

      {/* ── Manual URL entry (no share intent) ─────────────────────── */}
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

      {/* ── Shared URL card ─────────────────────────────────────────── */}
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

      {/* ── Auto-shorten path (share-sheet arrivals) ─────────────────── */}
      {sharedUrl ? (
        <>
          {/* Working spinner */}
          {isAutoWorking ? (
            <View style={styles.autoPanel}>
              <ActivityIndicator color={colors.primary} />
              <Text style={[styles.autoPanelHint, { color: colors.muted }]}>
                Shortening…
              </Text>
            </View>
          ) : null}

          {/* Result card */}
          {resultLink && !isAutoWorking ? (
            <View
              style={[
                styles.resultCard,
                { backgroundColor: colors.card, borderColor: colors.primary },
              ]}
            >
              {reusingDuplicate && !shortened ? (
                <View style={styles.reuseTag}>
                  <Feather name="check-circle" size={13} color={colors.primary} />
                  <Text style={[styles.reuseTagText, { color: colors.primary }]}>
                    Reusing existing short link
                  </Text>
                </View>
              ) : null}
              <Text
                style={[styles.resultUrl, { color: colors.text }]}
                numberOfLines={2}
                selectable
              >
                {resultLink.short_url}
              </Text>
              {resultLink.title ? (
                <Text style={[styles.resultTitle, { color: colors.muted }]} numberOfLines={1}>
                  {resultLink.title}
                </Text>
              ) : null}
              <View style={styles.resultActions}>
                <Button
                  label={copied ? "Copied!" : "Copy"}
                  onPress={() => copyShort(resultLink)}
                  style={{ flex: 1 }}
                />
                <Button
                  label="Share"
                  variant="secondary"
                  onPress={() => shareShort(resultLink)}
                  style={{ flex: 1 }}
                />
              </View>
              <Pressable
                onPress={() => router.replace(`/links/${resultLink.id}` as any)}
                style={styles.viewLinkRow}
                accessibilityRole="button"
              >
                <Text style={[styles.viewLinkText, { color: colors.primary }]}>
                  View link details
                </Text>
                <Feather name="arrow-right" size={14} color={colors.primary} />
              </Pressable>
            </View>
          ) : null}

          {/* Duplicate notice (auto-shorten enabled but duplicate exists) */}
          {duplicateQ.data && !reusingDuplicate && !shortened && !isAutoWorking ? (
            <View
              style={[
                styles.duplicateCard,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              <View style={styles.duplicateHeader}>
                <Feather name="info" size={16} color={colors.primary} />
                <Text style={[styles.duplicateTitle, { color: colors.text }]}>
                  Already shortened
                </Text>
              </View>
              <Text style={[styles.duplicateHint, { color: colors.muted }]}>
                You have a short link for this URL:{" "}
                <Text style={{ fontWeight: "600" }}>
                  {duplicateQ.data.short_url}
                </Text>
              </Text>
              <View style={styles.duplicateActions}>
                <Button
                  label="Use existing"
                  onPress={() => setReusingDuplicate(true)}
                  style={{ flex: 1 }}
                />
                <Button
                  label="Create new"
                  variant="secondary"
                  onPress={() => {
                    autoTriggeredRef.current = true;
                    shortenMutation.mutate();
                  }}
                  disabled={shortenMutation.isPending}
                  style={{ flex: 1 }}
                />
              </View>
            </View>
          ) : null}

          {/* Auto-shorten off: manual trigger button */}
          {autoShortenPref === false &&
          !shortened &&
          !reusingDuplicate &&
          !duplicateQ.data &&
          duplicateQ.isFetched ? (
            <View style={styles.panel}>
              <Button
                label={shortenMutation.isPending ? "Shortening…" : "Shorten this link"}
                onPress={() => {
                  autoTriggeredRef.current = true;
                  shortenMutation.mutate();
                }}
                disabled={shortenMutation.isPending}
              />
            </View>
          ) : null}

          {/* Error fallback */}
          {shortenMutation.isError && !shortened ? (
            <View
              style={[
                styles.errorCard,
                { backgroundColor: colors.destructive + "18", borderColor: colors.destructive + "44" },
              ]}
            >
              <Text style={[styles.errorTitle, { color: colors.destructive }]}>
                Couldn't shorten link
              </Text>
              <Text style={[styles.errorHint, { color: colors.muted }]}>
                {messageOf(shortenMutation.error)}
              </Text>
              <Button
                label="Try again"
                variant="secondary"
                onPress={() => {
                  autoTriggeredRef.current = true;
                  shortenMutation.mutate();
                }}
                style={{ marginTop: 10 }}
              />
            </View>
          ) : null}

          {/* Other options disclosure */}
          <Pressable
            onPress={() => setShowOtherOptions((v) => !v)}
            style={styles.otherOptionsToggle}
            accessibilityRole="button"
            accessibilityLabel={showOtherOptions ? "Hide other options" : "Show other options"}
          >
            <Text style={[styles.otherOptionsLabel, { color: colors.muted }]}>
              Other options
            </Text>
            <Feather
              name={showOtherOptions ? "chevron-up" : "chevron-down"}
              size={15}
              color={colors.muted}
            />
          </Pressable>

          {showOtherOptions ? (
            <>
              {otherActions.map((a) => {
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
                    <View style={[styles.actionIcon, { backgroundColor: colors.primary + "22" }]}>
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
                  {renderCalendarPanel({
                    colors,
                    calendarsQ,
                    ownedCalendars,
                    extractQ,
                    selectedCalendarId,
                    calendarId,
                    setCalendarId,
                    evTitle,
                    setEvTitle,
                    evDate,
                    setEvDate,
                    evTime,
                    setEvTime,
                    evLocation,
                    setEvLocation,
                    evErrors,
                    sharedTitle,
                    host,
                    eventMutation,
                    submitEvent,
                  })}
                </View>
              ) : null}
            </>
          ) : null}

          {/* Auto-shorten toggle */}
          <View
            style={[
              styles.toggleRow,
              { borderColor: colors.border },
            ]}
          >
            <View style={{ flex: 1 }}>
              <Text style={[styles.toggleLabel, { color: colors.text }]}>
                Auto-shorten on share
              </Text>
              <Text style={[styles.toggleHint, { color: colors.muted }]}>
                Shorten links automatically when shared from another app
              </Text>
            </View>
            <Switch
              value={autoShortenPref ?? true}
              onValueChange={toggleAutoShorten}
              trackColor={{ true: colors.primary }}
              accessibilityLabel="Auto-shorten on share"
            />
          </View>
        </>
      ) : null}

      {/* ── Manual-entry path action list ───────────────────────────── */}
      {!sharedUrl ? (
        <>
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
              {renderCalendarPanel({
                colors,
                calendarsQ,
                ownedCalendars,
                extractQ,
                selectedCalendarId,
                calendarId,
                setCalendarId,
                evTitle,
                setEvTitle,
                evDate,
                setEvDate,
                evTime,
                setEvTime,
                evLocation,
                setEvLocation,
                evErrors,
                sharedTitle,
                host,
                eventMutation,
                submitEvent,
              })}
            </View>
          ) : null}

          {mode === "shorten" && url ? (
            <View style={styles.panel}>
              {shortened ? (
                <View
                  style={[
                    styles.resultCard,
                    { backgroundColor: colors.card, borderColor: colors.primary },
                  ]}
                >
                  <Text
                    style={[styles.resultUrl, { color: colors.text }]}
                    numberOfLines={2}
                    selectable
                  >
                    {shortened.short_url}
                  </Text>
                  <View style={styles.resultActions}>
                    <Button
                      label={copied ? "Copied!" : "Copy"}
                      onPress={() => copyShort(shortened)}
                      style={{ flex: 1 }}
                    />
                    <Button
                      label="Share"
                      variant="secondary"
                      onPress={() => shareShort(shortened)}
                      style={{ flex: 1 }}
                    />
                  </View>
                  <Pressable
                    onPress={() => router.replace(`/links/${shortened.id}` as any)}
                    style={styles.viewLinkRow}
                    accessibilityRole="button"
                  >
                    <Text style={[styles.viewLinkText, { color: colors.primary }]}>
                      View link details
                    </Text>
                    <Feather name="arrow-right" size={14} color={colors.primary} />
                  </Pressable>
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
        </>
      ) : null}
    </ScrollView>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Calendar panel helper (reused in both paths to avoid duplication)
// ─────────────────────────────────────────────────────────────────────────────

type CalendarPanelProps = {
  colors: ReturnType<typeof useColors>;
  calendarsQ: ReturnType<typeof useQuery<any>>;
  ownedCalendars: any[];
  extractQ: ReturnType<typeof useQuery<any>>;
  selectedCalendarId: number | null;
  calendarId: number | null;
  setCalendarId: (id: number) => void;
  evTitle: string | null;
  setEvTitle: (v: string) => void;
  evDate: string;
  setEvDate: (v: string) => void;
  evTime: string;
  setEvTime: (v: string) => void;
  evLocation: string | null;
  setEvLocation: (v: string) => void;
  evErrors: Record<string, string>;
  sharedTitle: string;
  host: string;
  eventMutation: ReturnType<typeof useMutation<any, any, any>>;
  submitEvent: () => void;
};

function renderCalendarPanel(p: CalendarPanelProps) {
  const { colors } = p;
  if (p.calendarsQ.isLoading) {
    return <ActivityIndicator color={colors.primary} />;
  }
  if (p.ownedCalendars.length === 0) {
    return (
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
    );
  }
  return (
    <>
      {p.extractQ.isFetching ? (
        <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 10 }}>
          <ActivityIndicator size="small" color={colors.primary} />
          <Text style={{ color: colors.muted, fontSize: 12 }}>
            Detecting event details…
          </Text>
        </View>
      ) : p.extractQ.data && p.extractQ.data.source !== "title" ? (
        <Text style={{ color: colors.muted, fontSize: 12, marginBottom: 10 }}>
          Event details detected from the page — review and adjust below.
        </Text>
      ) : null}
      {p.ownedCalendars.length > 1 ? (
        <View style={{ marginBottom: 8 }}>
          <Text style={[styles.fieldLabel, { color: colors.text }]}>
            Calendar
          </Text>
          {p.ownedCalendars.map((c: any) => {
            const sel = p.selectedCalendarId === c.id;
            return (
              <Pressable
                key={c.id}
                onPress={() => p.setCalendarId(c.id)}
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
          {p.evErrors.calendar ? (
            <Text style={{ color: colors.destructive, fontSize: 12 }}>
              {p.evErrors.calendar}
            </Text>
          ) : null}
        </View>
      ) : null}
      <TextField
        label="Event title"
        value={p.evTitle ?? (p.sharedTitle || p.host)}
        onChangeText={p.setEvTitle}
        placeholder="Event title"
      />
      <TextField
        label="Date"
        value={p.evDate}
        onChangeText={p.setEvDate}
        placeholder="YYYY-MM-DD"
        autoCapitalize="none"
        error={p.evErrors.date}
      />
      <TextField
        label="Time"
        value={p.evTime}
        onChangeText={p.setEvTime}
        placeholder="HH:MM"
        autoCapitalize="none"
        error={p.evErrors.time}
      />
      <TextField
        label="Location (optional)"
        value={p.evLocation ?? ""}
        onChangeText={p.setEvLocation}
        placeholder="Address or venue name"
      />
      <Text style={{ color: colors.muted, fontSize: 12, marginBottom: 12 }}>
        The shared URL is saved in the event description.
      </Text>
      <Button
        label={p.eventMutation.isPending ? "Adding…" : "Add event"}
        onPress={p.submitEvent}
        disabled={p.eventMutation.isPending}
      />
    </>
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
  urlText: { fontSize: 13, marginTop: 4 },
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
  autoPanel: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 16,
  },
  autoPanelHint: { fontSize: 14 },
  resultCard: {
    borderWidth: 1.5,
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
  },
  reuseTag: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    marginBottom: 8,
  },
  reuseTagText: { fontSize: 12, fontWeight: "600" },
  resultUrl: { fontSize: 18, fontWeight: "700", letterSpacing: -0.3 },
  resultTitle: { fontSize: 12, marginTop: 4 },
  resultActions: {
    flexDirection: "row",
    gap: 8,
    marginTop: 14,
  },
  viewLinkRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    marginTop: 12,
    alignSelf: "flex-start",
  },
  viewLinkText: { fontSize: 13, fontWeight: "600" },
  duplicateCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  duplicateHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    marginBottom: 6,
  },
  duplicateTitle: { fontSize: 14, fontWeight: "600" },
  duplicateHint: { fontSize: 13, lineHeight: 19, marginBottom: 12 },
  duplicateActions: { flexDirection: "row", gap: 8 },
  errorCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  errorTitle: { fontSize: 14, fontWeight: "600", marginBottom: 4 },
  errorHint: { fontSize: 13, lineHeight: 19 },
  otherOptionsToggle: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingVertical: 12,
    marginBottom: 4,
  },
  otherOptionsLabel: { fontSize: 13, fontWeight: "600" },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingTop: 20,
    marginTop: 8,
  },
  toggleLabel: { fontSize: 14, fontWeight: "600", marginBottom: 2 },
  toggleHint: { fontSize: 12, lineHeight: 17 },
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
