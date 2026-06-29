import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
  getWhatsappPaymentAlerts,
  updateNotificationPreferences,
  updateWhatsappPaymentAlerts,
  type NotificationPreference,
} from "@/lib/api/notifications";

type LocalState = Record<
  string,
  { in_app: boolean; email: boolean; push: boolean }
>;

export default function NotificationPreferencesScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const router = useRouter();

  const q = useQuery({
    queryKey: ["notification-preferences"],
    queryFn: getNotificationPreferences,
  });

  const waq = useQuery({
    queryKey: ["whatsapp-payment-alerts"],
    queryFn: getWhatsappPaymentAlerts,
  });

  const waMutation = useMutation({
    mutationFn: (enabled: boolean) => updateWhatsappPaymentAlerts(enabled),
    onSuccess: (data) => {
      qc.setQueryData(["whatsapp-payment-alerts"], data);
    },
    onError: (e: any) => {
      Alert.alert("Couldn't save", e?.message ?? "Try again");
    },
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

          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.waHeader}>
              <View style={styles.waHeaderText}>
                <Text style={[styles.label, { color: colors.text }]}>
                  WhatsApp payment alerts
                </Text>
                <Text style={[styles.desc, { color: colors.mutedForeground }]}>
                  {waq.data && !waq.data.has_whatsapp_number
                    ? "Verify a WhatsApp number to get pinged on new subscribers, tips, unlocks and paid forms."
                    : "Get a one-way WhatsApp ping for new subscribers, tips, unlocks and paid form payments."}
                </Text>
              </View>
              {waq.isLoading || waMutation.isPending ? (
                <ActivityIndicator color={colors.primary} />
              ) : (
                <Switch
                  value={!!waq.data?.enabled}
                  disabled={
                    !waq.data?.has_whatsapp_number || waMutation.isPending
                  }
                  onValueChange={(v) => waMutation.mutate(v)}
                  trackColor={{ false: colors.border, true: colors.primary }}
                  thumbColor="#fff"
                />
              )}
            </View>
            {waq.data && !waq.data.has_whatsapp_number ? (
              <TouchableOpacity
                onPress={() => router.push("/whatsapp-verify")}
                style={[styles.verifyBtn, { borderColor: colors.primary }]}
              >
                <Feather name="message-circle" size={15} color={colors.primary} />
                <Text style={[styles.verifyText, { color: colors.primary }]}>
                  Verify a WhatsApp number
                </Text>
              </TouchableOpacity>
            ) : null}
            {waq.data && waq.data.has_whatsapp_number ? (
              <TouchableOpacity
                onPress={() => router.push("/whatsapp-verify")}
                style={[styles.manageRow, { borderTopColor: colors.border }]}
              >
                <View style={styles.manageRowText}>
                  <Feather
                    name="message-circle"
                    size={15}
                    color={colors.mutedForeground}
                  />
                  <Text style={[styles.manageNumber, { color: colors.text }]}>
                    {waq.data.mobile_masked ?? "Connected"}
                  </Text>
                </View>
                <View style={styles.manageRowLink}>
                  <Text style={[styles.manageLink, { color: colors.primary }]}>
                    Manage
                  </Text>
                  <Feather name="chevron-right" size={16} color={colors.primary} />
                </View>
              </TouchableOpacity>
            ) : null}
          </View>

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
            Push delivery is enabled on this device. Make sure notifications are
            allowed in your system settings to receive alerts.
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
  waHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  waHeaderText: { flex: 1 },
  verifyBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 10,
    marginTop: 12,
  },
  verifyText: { fontSize: 14, fontWeight: "600" },
  manageRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderTopWidth: 1,
    marginTop: 12,
    paddingTop: 12,
  },
  manageRowText: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flexShrink: 1,
  },
  manageNumber: { fontSize: 14, fontWeight: "600", letterSpacing: 0.5 },
  manageRowLink: { flexDirection: "row", alignItems: "center", gap: 2 },
  manageLink: { fontSize: 14, fontWeight: "600" },
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
