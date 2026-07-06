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
import { TextField } from "@/components/TextField";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import {
  createCalendar,
  getCalendar,
  updateCalendar,
  type CalendarInput,
} from "@/lib/api/calendars";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";

const ACCENT_SWATCHES = [
  "#3d6bff",
  "#7c3aed",
  "#db2777",
  "#e11d48",
  "#ea580c",
  "#16a34a",
  "#0891b2",
  "#475569",
];

const HEX_RE = /^#[0-9a-fA-F]{6}$/;

function deviceTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
  } catch {
    return "UTC";
  }
}

export default function CalendarEditScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const plan = usePlanFeatures();
  const params = useLocalSearchParams<{ id?: string }>();
  const id = params.id ? Number(params.id) : null;
  const isEdit = id != null && Number.isFinite(id);

  // Proactively lock NEW calendar creation when the current plan doesn't allow
  // the Calendar page type (module_calendar off / max_calendars cap of 0) —
  // mirroring the Create tab's gate so the "Perfect pairings" deep-link into
  // this builder shows an upgrade affordance instead of an empty form that only
  // bounces at submit. Editing an existing calendar is never gated (create-only,
  // like the web module/cap gate), and fresh free accounts (max_calendars=1)
  // still fall through to build their first calendar. Fails open until plan
  // data resolves.
  const createLocked = !isEdit && plan.isLinkTypeLocked("event");

  const existingQ = useQuery({
    queryKey: ["calendar", id, true],
    queryFn: () => getCalendar(id as number, { past: true }),
    enabled: isEdit,
  });

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [timezone, setTimezone] = useState(deviceTimezone());
  const [accent, setAccent] = useState("#3d6bff");
  const [isPublic, setIsPublic] = useState(true);
  const [seeded, setSeeded] = useState(false);

  // Hydrate the form once the existing calendar loads (edit mode only).
  useEffect(() => {
    if (!isEdit || seeded) return;
    const cal = existingQ.data?.calendar;
    if (!cal) return;
    setTitle(cal.title ?? "");
    setDescription(cal.description ?? "");
    setTimezone(cal.timezone || deviceTimezone());
    setAccent(cal.accent_color || "#3d6bff");
    setIsPublic(!!cal.is_public);
    setSeeded(true);
  }, [isEdit, seeded, existingQ.data]);

  const accentError = accent.trim() && !HEX_RE.test(accent.trim());
  const canSave = title.trim().length > 0 && !accentError && timezone.trim().length > 0;

  const payload = useMemo<CalendarInput>(
    () => ({
      title: title.trim(),
      description: description.trim() || null,
      timezone: timezone.trim(),
      accent_color: accent.trim() || null,
      is_public: isPublic,
    }),
    [title, description, timezone, accent, isPublic],
  );

  const save = useMutation({
    mutationFn: () =>
      isEdit ? updateCalendar(id as number, payload) : createCalendar(payload),
    onSuccess: (cal) => {
      qc.invalidateQueries({ queryKey: ["calendars"] });
      qc.invalidateQueries({ queryKey: ["my-calendar"] });
      qc.invalidateQueries({ queryKey: ["my-calendar-today"] });
      if (isEdit) {
        qc.invalidateQueries({ queryKey: ["calendar", id] });
        router.back();
      } else {
        // Land on the new calendar's detail so the owner can add events.
        router.replace({
          pathname: "/calendars/[id]",
          params: { id: String(cal.id) },
        });
      }
    },
    onError: (e) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert(
        isEdit ? "Couldn't save calendar" : "Couldn't create calendar",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    },
  });

  if (isEdit && existingQ.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit calendar" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (createLocked) {
    const message =
      "Calendars aren't available on your current plan. Upgrade to unlock followable calendars.";
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "New calendar", headerBackTitle: "Back" }} />
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
              Calendars are a plan feature
            </Text>
            <Text style={[styles.lockBody, { color: colors.mutedForeground }]}>
              {message}
            </Text>
            <Button
              label="See plans"
              onPress={() => showUpgradePrompt({ message })}
            />
          </View>
        </ScrollView>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: isEdit ? "Edit calendar" : "New calendar", headerBackTitle: "Back" }}
      />
      <ScrollView
        contentContainerStyle={{ padding: 16, gap: 18, paddingBottom: 48 }}
        keyboardShouldPersistTaps="handled"
      >
        <TextField
          label="Title"
          value={title}
          onChangeText={setTitle}
          placeholder="e.g. Community Events"
          maxLength={120}
          returnKeyType="next"
        />

        <TextField
          label="Description"
          value={description}
          onChangeText={setDescription}
          placeholder="What this calendar is about (optional)"
          multiline
          numberOfLines={3}
          maxLength={2000}
          style={{ minHeight: 90, paddingTop: 14, textAlignVertical: "top" }}
        />

        <TextField
          label="Timezone"
          value={timezone}
          onChangeText={setTimezone}
          placeholder="e.g. America/New_York"
          autoCapitalize="none"
          autoCorrect={false}
          hint="IANA timezone name. Event times you enter are interpreted in this zone."
        />

        <View style={{ gap: 10 }}>
          <Text style={[styles.label, { color: colors.mutedForeground }]}>Accent color</Text>
          <View style={styles.swatchRow}>
            {ACCENT_SWATCHES.map((c) => {
              const active = accent.trim().toLowerCase() === c.toLowerCase();
              return (
                <Pressable
                  key={c}
                  onPress={() => setAccent(c)}
                  style={[
                    styles.swatch,
                    { backgroundColor: c, borderColor: active ? colors.foreground : "transparent" },
                  ]}
                >
                  {active ? <Feather name="check" size={16} color="#fff" /> : null}
                </Pressable>
              );
            })}
          </View>
          <TextField
            value={accent}
            onChangeText={setAccent}
            placeholder="#3d6bff"
            autoCapitalize="none"
            autoCorrect={false}
            maxLength={7}
            error={accentError ? "Use a hex color like #3d6bff" : undefined}
          />
        </View>

        <View
          style={[
            styles.toggleRow,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <View style={{ flex: 1, gap: 3 }}>
            <Text style={[styles.toggleTitle, { color: colors.foreground }]}>Public calendar</Text>
            <Text style={[styles.toggleHint, { color: colors.mutedForeground }]}>
              Public calendars can be discovered and followed by anyone. Turn off to keep it private.
            </Text>
          </View>
          <Switch
            value={isPublic}
            onValueChange={setIsPublic}
            trackColor={{ true: colors.primary }}
          />
        </View>

        <Button
          label={isEdit ? "Save changes" : "Create calendar"}
          onPress={() => save.mutate()}
          loading={save.isPending}
          disabled={!canSave}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  label: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  swatchRow: { flexDirection: "row", flexWrap: "wrap", gap: 12 },
  swatch: {
    width: 40,
    height: 40,
    borderRadius: 999,
    borderWidth: 2,
    alignItems: "center",
    justifyContent: "center",
  },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    padding: 16,
    borderWidth: 1,
  },
  toggleTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  toggleHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, lineHeight: 17 },
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
