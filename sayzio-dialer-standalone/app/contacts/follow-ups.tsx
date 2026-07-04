import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Animated,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { DateTimePickerModal } from "@/components/DateTimePickerModal";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  clearContactFollowUp,
  type Contact,
  listFollowUps,
  setContactFollowUp,
} from "@/lib/api/contacts";

const deviceTimeZone = (() => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
  } catch {
    return null;
  }
})();

function formatWhen(iso: string): { abs: string; rel: string } {
  const d = new Date(iso);
  const abs = d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
  const diffMs = d.getTime() - Date.now();
  const past = diffMs < 0;
  const mins = Math.round(Math.abs(diffMs) / 60000);
  let rel: string;
  if (mins < 60) rel = `${mins}m`;
  else if (mins < 1440) rel = `${Math.round(mins / 60)}h`;
  else rel = `${Math.round(mins / 1440)}d`;
  return { abs, rel: past ? `${rel} ago` : `in ${rel}` };
}

export default function FollowUpsScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["follow-ups"],
    queryFn: listFollowUps,
  });

  // Inline quick-actions: clear ("Done") or snooze to a preset (+1 day / +7
  // days). Both reuse the existing set/clear endpoints and refresh the list.
  const [busyId, setBusyId] = useState<number | null>(null);

  // Brief window to restore a follow-up cleared by accident. We stash the
  // contact (with its previous date + note) and re-set it via the existing
  // set endpoint if the user taps Undo.
  const [undoContact, setUndoContact] = useState<Contact | null>(null);
  const undoTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const dismissUndo = () => {
    if (undoTimer.current) clearTimeout(undoTimer.current);
    undoTimer.current = null;
    setUndoContact(null);
  };

  const showUndo = (c: Contact) => {
    if (!c.follow_up_at) return;
    setUndoContact(c);
    if (undoTimer.current) clearTimeout(undoTimer.current);
    undoTimer.current = setTimeout(() => setUndoContact(null), 6000);
  };

  useEffect(() => {
    return () => {
      if (undoTimer.current) clearTimeout(undoTimer.current);
    };
  }, []);

  const doneM = useMutation({
    mutationFn: (c: Contact) => clearContactFollowUp(c.id),
    onMutate: (c) => setBusyId(c.id),
    onSuccess: (_data, c) => showUndo(c),
    onSettled: () => {
      setBusyId(null);
      qc.invalidateQueries({ queryKey: ["follow-ups"] });
    },
    onError: () => Alert.alert("Couldn't clear", "Please try again."),
  });

  const undoM = useMutation({
    mutationFn: (c: Contact) =>
      setContactFollowUp(
        c.id,
        c.follow_up_at as string,
        c.follow_up_note,
        c.follow_up_tz ?? null,
        true,
      ),
    onMutate: () => dismissUndo(),
    onSettled: () => qc.invalidateQueries({ queryKey: ["follow-ups"] }),
    onError: () => Alert.alert("Couldn't undo", "Please try again."),
  });

  const snoozeM = useMutation({
    mutationFn: ({ c, days }: { c: Contact; days: number }) => {
      const at = new Date(Date.now() + days * 86400000);
      return setContactFollowUp(c.id, at.toISOString(), c.follow_up_note);
    },
    onMutate: ({ c }) => setBusyId(c.id),
    onSettled: () => {
      setBusyId(null);
      qc.invalidateQueries({ queryKey: ["follow-ups"] });
    },
    onError: () => Alert.alert("Couldn't snooze", "Please try again."),
  });

  // Snooze to a user-picked datetime-local value; sent as a naive wall-clock
  // string plus the device timezone so the server anchors it correctly.
  const snoozeAtM = useMutation({
    mutationFn: ({ c, value }: { c: Contact; value: string }) =>
      setContactFollowUp(c.id, value, c.follow_up_note, deviceTimeZone),
    onMutate: ({ c }) => setBusyId(c.id),
    onSettled: () => {
      setBusyId(null);
      qc.invalidateQueries({ queryKey: ["follow-ups"] });
    },
    onError: () => Alert.alert("Couldn't snooze", "Please try again."),
  });

  // Contact whose custom "Pick a time…" picker is open (null when closed).
  const [pickerFor, setPickerFor] = useState<Contact | null>(null);

  const promptSnooze = (c: Contact) => {
    Alert.alert("Snooze follow-up", `Reschedule ${c.display_name || "this contact"}?`, [
      { text: "Tomorrow", onPress: () => snoozeM.mutate({ c, days: 1 }) },
      { text: "Next week", onPress: () => snoozeM.mutate({ c, days: 7 }) },
      { text: "Pick a time…", onPress: () => setPickerFor(c) },
      { text: "Cancel", style: "cancel" },
    ]);
  };

  const Row = ({ c, overdue }: { c: Contact; overdue: boolean }) => {
    const when = c.follow_up_at ? formatWhen(c.follow_up_at) : null;
    const accent = overdue ? colors.destructive : colors.primary;
    const busy = busyId === c.id;
    return (
      <Pressable
        onPress={() => router.push(`/contacts/${c.id}`)}
        style={[
          styles.row,
          {
            backgroundColor: overdue ? colors.destructive + "10" : colors.card,
            borderColor: overdue ? colors.destructive + "44" : colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <View style={[styles.avatar, { backgroundColor: accent + "1c" }]}>
          <Text style={{ color: accent, fontFamily: "SpaceGrotesk_700Bold" }}>
            {(c.display_name || "?").slice(0, 1).toUpperCase()}
          </Text>
        </View>
        <View style={{ flex: 1 }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
            <Text
              numberOfLines={1}
              style={[styles.name, { color: colors.foreground, flexShrink: 1 }]}
            >
              {c.display_name || "Unnamed"}
            </Text>
            {overdue ? (
              <View
                style={[styles.badge, { backgroundColor: colors.destructive + "22" }]}
              >
                <Text style={[styles.badgeText, { color: colors.destructive }]}>
                  OVERDUE
                </Text>
              </View>
            ) : null}
          </View>
          {when ? (
            <View style={styles.metaRow}>
              <Feather
                name={overdue ? "alert-circle" : "bell"}
                size={11}
                color={overdue ? colors.destructive : colors.mutedForeground}
              />
              <Text
                style={{
                  color: overdue ? colors.destructive : colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_400Regular",
                  fontSize: 12,
                }}
              >
                {when.abs} · {when.rel}
              </Text>
            </View>
          ) : null}
          {c.follow_up_note ? (
            <Text
              numberOfLines={2}
              style={[styles.note, { color: colors.mutedForeground }]}
            >
              {c.follow_up_note}
            </Text>
          ) : null}
          <View style={styles.actions}>
            <Pressable
              disabled={busy}
              onPress={() => doneM.mutate(c)}
              hitSlop={6}
              style={[
                styles.actionBtn,
                { backgroundColor: colors.success + "1e", borderColor: colors.success + "3a" },
              ]}
            >
              <Feather name="check" size={12} color={colors.success} />
              <Text style={[styles.actionText, { color: colors.success }]}>Done</Text>
            </Pressable>
            <Pressable
              disabled={busy}
              onPress={() => promptSnooze(c)}
              hitSlop={6}
              style={[
                styles.actionBtn,
                { backgroundColor: colors.primary + "1e", borderColor: colors.primary + "3a" },
              ]}
            >
              <Feather name="clock" size={12} color={colors.primary} />
              <Text style={[styles.actionText, { color: colors.primary }]}>Snooze</Text>
            </Pressable>
          </View>
        </View>
        {busy ? (
          <ActivityIndicator size="small" color={colors.mutedForeground} />
        ) : (
          <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
        )}
      </Pressable>
    );
  };

  const overdue = q.data?.overdue ?? [];
  const upcoming = q.data?.upcoming ?? [];
  const empty = overdue.length === 0 && upcoming.length === 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Follow-ups",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 16, gap: 8 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        >
          {empty ? (
            <View style={{ marginTop: 40 }}>
              <EmptyState
                icon="bell"
                title="No follow-ups scheduled"
                body="Set a reminder on any contact and it'll show up here."
              />
            </View>
          ) : null}

          {overdue.length > 0 ? (
            <>
              <Text style={[styles.section, { color: colors.destructive }]}>
                OVERDUE ({overdue.length})
              </Text>
              {overdue.map((c) => (
                <Row key={c.id} c={c} overdue />
              ))}
            </>
          ) : null}

          {upcoming.length > 0 ? (
            <>
              <Text
                style={[
                  styles.section,
                  { color: colors.mutedForeground, marginTop: overdue.length ? 12 : 0 },
                ]}
              >
                UPCOMING ({upcoming.length})
              </Text>
              {upcoming.map((c) => (
                <Row key={c.id} c={c} overdue={false} />
              ))}
            </>
          ) : null}
        </ScrollView>
      )}

      {undoContact ? (
        <UndoSnackbar
          label="Follow-up cleared"
          onUndo={() => undoM.mutate(undoContact)}
        />
      ) : null}
      <DateTimePickerModal
        visible={pickerFor !== null}
        title="Snooze until"
        onClose={() => setPickerFor(null)}
        onPick={(value) => {
          if (pickerFor) snoozeAtM.mutate({ c: pickerFor, value });
        }}
      />
    </View>
  );
}

function UndoSnackbar({
  label,
  onUndo,
}: {
  label: string;
  onUndo: () => void;
}) {
  const colors = useColors();
  const anim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.timing(anim, {
      toValue: 1,
      duration: 200,
      useNativeDriver: true,
    }).start();
  }, [anim]);

  return (
    <Animated.View
      pointerEvents="box-none"
      style={[
        styles.snackbarWrap,
        {
          opacity: anim,
          transform: [
            {
              translateY: anim.interpolate({
                inputRange: [0, 1],
                outputRange: [16, 0],
              }),
            },
          ],
        },
      ]}
    >
      <View
        style={[
          styles.snackbar,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <Feather name="check-circle" size={14} color={colors.success} />
        <Text
          style={[styles.snackbarText, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {label}
        </Text>
        <Pressable
          onPress={onUndo}
          hitSlop={8}
          style={[
            styles.snackbarBtn,
            { backgroundColor: colors.primary + "26", borderColor: colors.primary + "46" },
          ]}
        >
          <Feather name="rotate-ccw" size={12} color={colors.primary} />
          <Text style={[styles.snackbarBtnText, { color: colors.primary }]}>Undo</Text>
        </Pressable>
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  section: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11,
    letterSpacing: 0.6,
    marginBottom: 2,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  metaRow: { flexDirection: "row", alignItems: "center", gap: 5, marginTop: 3 },
  note: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 4,
  },
  actions: {
    flexDirection: "row",
    gap: 8,
    marginTop: 10,
  },
  actionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
  },
  actionText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
  },
  badge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 9,
    letterSpacing: 0.5,
  },
  snackbarWrap: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 24,
    alignItems: "center",
    paddingHorizontal: 16,
  },
  snackbar: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingLeft: 14,
    paddingRight: 8,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
    shadowColor: "#000",
    shadowOpacity: 0.3,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 8,
  },
  snackbarText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    flexShrink: 1,
  },
  snackbarBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
  },
  snackbarBtnText: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 12,
  },
});
