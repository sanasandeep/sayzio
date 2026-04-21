import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  getNotificationPreferences,
  updateNotificationPreferences,
  type NotificationPreference,
} from "@/lib/api/notifications";

type LocalState = Record<
  string,
  { in_app: boolean; email: boolean; push: boolean }
>;

export default function NotificationPreferencesScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["notification-preferences"],
    queryFn: getNotificationPreferences,
  });

  // Local mirror so toggles feel instant; we PUT on Save.
  const [state, setState] = useState<LocalState>({});
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    if (!q.data) return;
    const next: LocalState = {};
    q.data.forEach((p) => {
      next[p.type] = { in_app: p.in_app, email: p.email, push: p.push };
    });
    setState(next);
    setDirty(false);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () => updateNotificationPreferences(state),
    onSuccess: (items) => {
      qc.setQueryData(["notification-preferences"], items);
      setDirty(false);
    },
  });

  const toggle = (
    type: string,
    channel: "in_app" | "email" | "push",
    value: boolean,
  ) => {
    setState((s) => ({
      ...s,
      [type]: { ...s[type], [channel]: value },
    }));
    setDirty(true);
  };

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <Stack.Screen
        options={{
          title: "Notification preferences",
          headerStyle: { backgroundColor: colors.background },
          headerTintColor: colors.text,
        }}
      />

      {q.isLoading ? (
        <View style={styles.loading}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.scroll}>
          <Text style={[styles.intro, { color: colors.mutedForeground }]}>
            Choose which alerts reach you, and where.
          </Text>

          {q.data?.map((pref) => (
            <PrefRow
              key={pref.type}
              pref={pref}
              local={state[pref.type]}
              onToggle={(ch, v) => toggle(pref.type, ch, v)}
              colors={colors}
            />
          ))}

          <Text style={[styles.footnote, { color: colors.mutedForeground }]}>
            Push delivery rolls out with the next mobile release.
          </Text>
        </ScrollView>
      )}

      <View
        style={[
          styles.bar,
          { backgroundColor: colors.card, borderTopColor: colors.border },
        ]}
      >
        <TouchableOpacity
          disabled={!dirty || save.isPending}
          onPress={() => save.mutate()}
          style={[
            styles.saveBtn,
            {
              backgroundColor:
                !dirty || save.isPending ? colors.border : colors.primary,
            },
          ]}
        >
          {save.isPending ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <>
              <Feather name="check" size={16} color="#fff" />
              <Text style={styles.saveText}>
                {dirty ? "Save preferences" : "Saved"}
              </Text>
            </>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

function PrefRow({
  pref,
  local,
  onToggle,
  colors,
}: {
  pref: NotificationPreference;
  local: { in_app: boolean; email: boolean; push: boolean } | undefined;
  onToggle: (ch: "in_app" | "email" | "push", v: boolean) => void;
  colors: ReturnType<typeof useColors>;
}) {
  const value = local ?? { in_app: pref.in_app, email: pref.email, push: pref.push };
  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <Text style={[styles.label, { color: colors.text }]}>{pref.label}</Text>
      <Text style={[styles.desc, { color: colors.mutedForeground }]}>
        {pref.description}
      </Text>

      <ChannelRow
        label="In-app"
        value={value.in_app}
        onChange={(v) => onToggle("in_app", v)}
        colors={colors}
      />
      <ChannelRow
        label="Email"
        value={value.email}
        onChange={(v) => onToggle("email", v)}
        colors={colors}
      />
      <ChannelRow
        label="Push"
        value={value.push}
        onChange={(v) => onToggle("push", v)}
        colors={colors}
      />
    </View>
  );
}

function ChannelRow({
  label,
  value,
  onChange,
  colors,
}: {
  label: string;
  value: boolean;
  onChange: (v: boolean) => void;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.channelRow}>
      <Text style={[styles.channelLabel, { color: colors.text }]}>{label}</Text>
      <Switch
        value={value}
        onValueChange={onChange}
        trackColor={{ false: colors.border, true: colors.primary }}
        thumbColor="#fff"
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  loading: { flex: 1, alignItems: "center", justifyContent: "center" },
  scroll: { padding: 16, paddingBottom: 120 },
  intro: { fontSize: 14, marginBottom: 16 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    marginBottom: 12,
  },
  label: { fontSize: 15, fontWeight: "600" },
  desc: { fontSize: 12, marginTop: 4, marginBottom: 10 },
  channelRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 6,
  },
  channelLabel: { fontSize: 14 },
  footnote: { fontSize: 11, textAlign: "center", marginTop: 8 },
  bar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    padding: 16,
    borderTopWidth: 1,
  },
  saveBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 12,
    borderRadius: 12,
  },
  saveText: { color: "#fff", fontWeight: "700", fontSize: 14 },
});
