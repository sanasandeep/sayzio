import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
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

import { useColors } from "@/hooks/useColors";
import { googleContacts } from "@/lib/api/contacts";

// Google Contacts two-way sync management (parity with the web Contacts
// settings panel). OAuth *connect* still happens on the web (session-based);
// here the owner can run a sync, toggle pull/push, and disconnect an already
// linked Google account.
export default function GoogleSyncScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["google-contacts-status"],
    queryFn: googleContacts.status,
  });

  const account = q.data;

  const syncMut = useMutation({
    mutationFn: googleContacts.sync,
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ["google-contacts-status"] });
      qc.invalidateQueries({ queryKey: ["contacts"] });

      if (r.status === "in_progress") {
        Alert.alert(
          "Sync already running",
          "A sync is already in progress. Give it a moment and check back.",
        );
        return;
      }

      if (r.status === "throttled") {
        const secs = Math.max(1, Math.round(r.retry_after ?? 0));
        Alert.alert(
          "Already up to date",
          `You synced very recently. Try again in ${secs}s.`,
        );
        return;
      }

      const s = r.stats;
      if (!s) {
        Alert.alert("Sync complete", "Your contacts are up to date.");
        return;
      }
      Alert.alert(
        "Sync complete",
        `Created ${s.created}, updated ${s.updated}, deleted ${s.deleted}, pushed ${s.pushed}` +
          (s.skipped_capped ? `, ${s.skipped_capped} skipped (plan cap)` : "") +
          (s.errors ? `, ${s.errors} error(s)` : "") +
          ".",
      );
    },
    onError: (e: any) =>
      Alert.alert("Sync failed", e?.message ?? "Try again"),
  });

  const updateMut = useMutation({
    mutationFn: (prefs: { pull_enabled?: boolean; push_enabled?: boolean }) =>
      googleContacts.update(prefs),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["google-contacts-status"] }),
    onError: (e: any) => Alert.alert("Error", e?.message ?? "Try again"),
  });

  const disconnectMut = useMutation({
    mutationFn: googleContacts.disconnect,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["google-contacts-status"] });
      Alert.alert("Disconnected", "Your Google account has been unlinked.");
    },
    onError: (e: any) => Alert.alert("Error", e?.message ?? "Try again"),
  });

  const confirmDisconnect = () =>
    Alert.alert(
      "Disconnect Google?",
      "Contacts already imported stay, but they'll no longer sync.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Disconnect",
          style: "destructive",
          onPress: () => disconnectMut.mutate(),
        },
      ],
    );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Google Contacts" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
        {q.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : !account ? (
          <View style={[card(colors)]}>
            <Feather name="link" size={24} color={colors.mutedForeground} />
            <Text style={[styles.title, { color: colors.foreground, marginTop: 10 }]}>
              No Google account connected
            </Text>
            <Text style={{ color: colors.mutedForeground, marginTop: 6 }}>
              Connect your Google account from the Sayzio web app (Contacts →
              Settings) to enable two-way sync. Once connected, you can run
              syncs and manage preferences here.
            </Text>
          </View>
        ) : (
          <>
            <View style={[card(colors)]}>
              <View style={styles.head}>
                <Feather name="user-check" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {account.account_email ?? "Google account"}
                </Text>
              </View>
              {account.last_synced_at ? (
                <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                  Last synced{" "}
                  {new Date(account.last_synced_at).toLocaleString()} ·{" "}
                  {account.last_sync_status ?? "ok"}
                </Text>
              ) : (
                <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                  Not synced yet.
                </Text>
              )}
              {account.last_sync_error ? (
                <Text style={{ color: colors.destructive, marginTop: 4, fontSize: 12 }}>
                  {account.last_sync_error}
                </Text>
              ) : null}
            </View>

            <View style={[card(colors)]}>
              <View style={styles.toggleRow}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                    Pull from Google
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    Import & update contacts from your Google account.
                  </Text>
                </View>
                <Switch
                  value={account.pull_enabled}
                  onValueChange={(v) => updateMut.mutate({ pull_enabled: v })}
                  disabled={updateMut.isPending}
                />
              </View>
              <View style={[styles.toggleRow, { marginTop: 14 }]}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                    Push to Google
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    Send your Sayzio contacts back to Google.
                  </Text>
                </View>
                <Switch
                  value={account.push_enabled}
                  onValueChange={(v) => updateMut.mutate({ push_enabled: v })}
                  disabled={updateMut.isPending}
                />
              </View>
            </View>

            <Pressable
              onPress={() => syncMut.mutate()}
              disabled={syncMut.isPending}
              style={[btn(colors, colors.primary)]}
            >
              {syncMut.isPending ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.btnText}>Sync now</Text>
              )}
            </Pressable>

            <Pressable
              onPress={confirmDisconnect}
              disabled={disconnectMut.isPending}
              style={[btn(colors, colors.card), { borderColor: colors.border, borderWidth: StyleSheet.hairlineWidth }]}
            >
              <Text style={{ color: colors.destructive, fontWeight: "600" }}>
                Disconnect Google
              </Text>
            </Pressable>
          </>
        )}
      </ScrollView>
    </View>
  );
}

const card = (colors: ReturnType<typeof useColors>) => ({
  backgroundColor: colors.card,
  borderColor: colors.border,
  borderWidth: StyleSheet.hairlineWidth,
  borderRadius: colors.radius,
  padding: 16,
});

const btn = (colors: ReturnType<typeof useColors>, bg: string) => ({
  backgroundColor: bg,
  borderRadius: colors.radius,
  paddingVertical: 14,
  alignItems: "center" as const,
  justifyContent: "center" as const,
});

const styles = StyleSheet.create({
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  btnText: { color: "#fff", fontWeight: "700", fontSize: 15 },
  toggleRow: { flexDirection: "row", alignItems: "center", gap: 12 },
});
