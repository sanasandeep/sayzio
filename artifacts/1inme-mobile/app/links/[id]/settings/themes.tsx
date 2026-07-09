import { Stack, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import {
  BiolinkSavedTheme,
  BiolinkThemeSchedule,
  BiolinkThemesPayload,
  cancelBiolinkThemeSchedule,
  deleteBiolinkTheme,
  listBiolinkThemes,
  saveBiolinkTheme,
  scheduleBiolinkTheme,
  updateBiolinkThemeSchedule,
} from "@/lib/api/biolinkThemes";
import { showAlert } from "@/lib/webAlert";

function pad(n: number) {
  return String(n).padStart(2, "0");
}

function toLocalIso(d: Date) {
  return (
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
    `T${pad(d.getHours())}:${pad(d.getMinutes())}:00`
  );
}

function fmt(iso: string | null) {
  if (!iso) return "";
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

export default function BiolinkThemesScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const linkId = Number(id);
  const tz = useMemo(
    () =>
      Intl?.DateTimeFormat?.().resolvedOptions().timeZone ?? "UTC",
    [],
  );

  const [data, setData] = useState<BiolinkThemesPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState("");
  const [pickerThemeId, setPickerThemeId] = useState<number | null>(null);
  const [editingScheduleId, setEditingScheduleId] = useState<number | null>(
    null,
  );
  const [starts, setStarts] = useState("");
  const [ends, setEnds] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const d = await listBiolinkThemes(linkId);
      setData(d);
    } catch (e: unknown) {
      showAlert("Failed to load themes", (e as Error).message);
    } finally {
      setLoading(false);
    }
  }, [linkId]);

  useEffect(() => {
    void load();
  }, [load]);

  const onSave = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      await saveBiolinkTheme(linkId, name.trim());
      setName("");
      await load();
    } catch (e: unknown) {
      showAlert("Couldn't save theme", (e as Error).message);
    } finally {
      setSaving(false);
    }
  };

  const openPicker = (theme: BiolinkSavedTheme) => {
    const now = new Date();
    setStarts(toLocalIso(new Date(now.getTime() + 60 * 60 * 1000)));
    setEnds(toLocalIso(new Date(now.getTime() + 25 * 60 * 60 * 1000)));
    setPickerThemeId(theme.id);
    setEditingScheduleId(null);
  };

  const openEditor = (s: BiolinkThemeSchedule) => {
    // Only pending schedules are editable; once a window has activated
    // the prev_settings snapshot is locked in and re-timing it would
    // race the cron's revert pass.
    if (s.status !== "pending") {
      showAlert(
        "Can't edit this schedule",
        "Only upcoming (not-yet-started) schedules can be re-timed. Cancel and create a new one instead.",
      );
      return;
    }
    setStarts(s.starts_at ? toLocalIso(new Date(s.starts_at)) : "");
    setEnds(s.ends_at ? toLocalIso(new Date(s.ends_at)) : "");
    setEditingScheduleId(s.id);
    setPickerThemeId(null);
  };

  const onSchedule = async () => {
    if (new Date(ends) <= new Date(starts)) {
      showAlert("Invalid window", "End must be after start.");
      return;
    }
    try {
      if (editingScheduleId) {
        await updateBiolinkThemeSchedule(linkId, editingScheduleId, {
          starts_at: starts,
          ends_at: ends,
          timezone: tz,
        });
        setEditingScheduleId(null);
      } else if (pickerThemeId) {
        await scheduleBiolinkTheme(linkId, {
          theme_id: pickerThemeId,
          starts_at: starts,
          ends_at: ends,
          timezone: tz,
        });
        setPickerThemeId(null);
      } else {
        return;
      }
      await load();
    } catch (e: unknown) {
      showAlert("Couldn't save schedule", (e as Error).message);
    }
  };

  const onCancel = async (s: BiolinkThemeSchedule) => {
    showAlert(
      s.is_live ? "End this scheduled theme now?" : "Cancel scheduled theme?",
      s.is_live
        ? "The page will revert to its previous look."
        : "It won't be applied.",
      [
        { text: "Keep", style: "cancel" },
        {
          text: s.is_live ? "End now" : "Cancel",
          style: "destructive",
          onPress: async () => {
            try {
              await cancelBiolinkThemeSchedule(linkId, s.id);
              await load();
            } catch (e: unknown) {
              showAlert("Failed", (e as Error).message);
            }
          },
        },
      ],
    );
  };

  const onDelete = async (t: BiolinkSavedTheme) => {
    showAlert(
      "Delete theme?",
      `"${t.name}" will be removed. Active schedules will be cancelled.`,
      [
        { text: "Keep", style: "cancel" },
        {
          text: "Delete",
          style: "destructive",
          onPress: async () => {
            try {
              await deleteBiolinkTheme(linkId, t.id);
              await load();
            } catch (e: unknown) {
              showAlert("Failed", (e as Error).message);
            }
          },
        },
      ],
    );
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Scheduled themes" }} />
      <ScrollView contentContainerStyle={styles.page}>
        <Text style={styles.blurb}>
          Save the current look as a theme, then schedule it to apply over a
          date range. The page reverts automatically when the window ends.
        </Text>

        <View style={styles.card}>
          <Text style={styles.h2}>Save current look</Text>
          <TextInput
            placeholder="e.g. Holiday 2026"
            placeholderTextColor="#888"
            value={name}
            onChangeText={setName}
            style={styles.input}
            maxLength={120}
          />
          <Pressable
            onPress={onSave}
            disabled={!name.trim() || saving}
            style={[styles.btnPrimary, (!name.trim() || saving) && styles.btnDisabled]}
          >
            <Text style={styles.btnPrimaryText}>
              {saving ? "Saving…" : "Save as theme"}
            </Text>
          </Pressable>
        </View>

        <View style={styles.card}>
          <Text style={styles.h2}>Your themes</Text>
          {loading ? (
            <ActivityIndicator />
          ) : !data?.themes.length ? (
            <Text style={styles.muted}>No saved themes yet.</Text>
          ) : (
            data.themes.map((t) => (
              <View key={t.id} style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.rowTitle}>{t.name}</Text>
                  <Text style={styles.rowMuted}>Saved {fmt(t.created_at)}</Text>
                </View>
                <Pressable
                  onPress={() => openPicker(t)}
                  style={styles.btnSecondary}
                >
                  <Text style={styles.btnSecondaryText}>Schedule</Text>
                </Pressable>
                <Pressable
                  onPress={() => onDelete(t)}
                  style={styles.btnDanger}
                >
                  <Text style={styles.btnDangerText}>Delete</Text>
                </Pressable>
              </View>
            ))
          )}
        </View>

        {(pickerThemeId !== null || editingScheduleId !== null) && (
          <View style={[styles.card, styles.cardAccent]}>
            <Text style={styles.h2}>
              {editingScheduleId
                ? `Edit window for ${
                    data?.schedules.find((s) => s.id === editingScheduleId)
                      ?.theme_name ?? "schedule"
                  }`
                : `Schedule ${
                    data?.themes.find((t) => t.id === pickerThemeId)?.name ?? ""
                  }`}
            </Text>
            <Text style={styles.label}>Starts (YYYY-MM-DD HH:MM)</Text>
            <TextInput
              value={starts}
              onChangeText={setStarts}
              style={styles.input}
              placeholderTextColor="#888"
              autoCapitalize="none"
            />
            <Text style={styles.label}>Ends</Text>
            <TextInput
              value={ends}
              onChangeText={setEnds}
              style={styles.input}
              placeholderTextColor="#888"
              autoCapitalize="none"
            />
            <Text style={styles.rowMuted}>Timezone: {tz}</Text>
            <View style={{ flexDirection: "row", gap: 8, marginTop: 12 }}>
              <Pressable
                onPress={() => {
                  setPickerThemeId(null);
                  setEditingScheduleId(null);
                }}
                style={[styles.btnSecondary, { flex: 1 }]}
              >
                <Text style={styles.btnSecondaryText}>Cancel</Text>
              </Pressable>
              <Pressable
                onPress={onSchedule}
                style={[styles.btnPrimary, { flex: 1 }]}
              >
                <Text style={styles.btnPrimaryText}>
                  {editingScheduleId ? "Save changes" : "Schedule"}
                </Text>
              </Pressable>
            </View>
          </View>
        )}

        <View style={styles.card}>
          <Text style={styles.h2}>Timeline</Text>
          {!data?.schedules.length ? (
            <Text style={styles.muted}>No upcoming or active schedules.</Text>
          ) : (
            data.schedules.map((s) => (
              <View
                key={s.id}
                style={[
                  styles.row,
                  s.is_live && {
                    backgroundColor: "rgba(34,197,94,0.10)",
                    borderColor: "rgba(34,197,94,0.4)",
                    borderWidth: 1,
                  },
                ]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={styles.rowTitle}>
                    {s.theme_name}
                    {s.is_live ? "  • LIVE" : ""}
                  </Text>
                  <Text style={styles.rowMuted}>
                    {fmt(s.starts_at)} → {fmt(s.ends_at)} ({s.timezone})
                  </Text>
                </View>
                {s.status === "pending" && !s.is_live && (
                  <Pressable
                    onPress={() => openEditor(s)}
                    style={styles.btnSecondary}
                  >
                    <Text style={styles.btnSecondaryText}>Edit</Text>
                  </Pressable>
                )}
                <Pressable onPress={() => onCancel(s)} style={styles.btnDanger}>
                  <Text style={styles.btnDangerText}>
                    {s.is_live ? "End" : "Cancel"}
                  </Text>
                </Pressable>
              </View>
            ))
          )}
        </View>
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  page: { padding: 16, gap: 12, paddingBottom: 60 },
  blurb: { color: "#bbb", fontSize: 13, marginBottom: 4 },
  card: {
    backgroundColor: "#111",
    borderRadius: 14,
    padding: 14,
    gap: 8,
    borderColor: "#222",
    borderWidth: 1,
  },
  cardAccent: { borderColor: "#7d9bff" },
  h2: { color: "#fff", fontSize: 15, fontWeight: "600", marginBottom: 4 },
  label: { color: "#aaa", fontSize: 12, marginTop: 4 },
  input: {
    backgroundColor: "#1a1a1a",
    color: "#fff",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderColor: "#2a2a2a",
    borderWidth: 1,
  },
  muted: { color: "#888", fontSize: 13 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 8,
    borderBottomColor: "#1f1f1f",
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderRadius: 8,
    paddingHorizontal: 4,
  },
  rowTitle: { color: "#fff", fontSize: 14, fontWeight: "600" },
  rowMuted: { color: "#888", fontSize: 11, marginTop: 2 },
  btnPrimary: {
    backgroundColor: "#7d9bff",
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    alignItems: "center",
  },
  btnPrimaryText: { color: "#0a0612", fontWeight: "700" },
  btnDisabled: { opacity: 0.5 },
  btnSecondary: {
    backgroundColor: "rgba(167,139,250,0.18)",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  btnSecondaryText: { color: "#7d9bff", fontWeight: "600", fontSize: 12 },
  btnDanger: {
    backgroundColor: "rgba(239,68,68,0.15)",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  btnDangerText: { color: "#fca5a5", fontWeight: "600", fontSize: 12 },
});
