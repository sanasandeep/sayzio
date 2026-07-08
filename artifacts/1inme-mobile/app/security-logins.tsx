import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";
import type { ApiError } from "@/lib/api";

type LoginEvent = {
  id: number;
  channel: string;
  country_code: string | null;
  platform: string | null;
  browser: string | null;
  device_label: string | null;
  is_new: boolean;
  new_reasons: string[] | null;
  revoked_at: string | null;
  created_at: string;
  status: "revoked" | "new" | "recognized";
};

type ListResponse = { data: { events: LoginEvent[] } };
type RevokeResponse = { data: { revoked: boolean; message: string } };

function formatWhen(iso: string, now: number): string {
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  let diff = Math.floor((now - d.getTime()) / 1000);
  if (diff < 0) diff = 0;
  if (diff < 45) return "Just now";
  const mins = Math.floor(diff / 60);
  if (mins < 60) return mins <= 1 ? "1 minute ago" : `${mins} minutes ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return hours === 1 ? "1 hour ago" : `${hours} hours ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return days === 1 ? "1 day ago" : `${days} days ago`;
  return d.toLocaleString();
}

export default function SecurityLoginsScreen() {
  const colors = useColors();
  const router = useRouter();
  const { signOut } = useAuth();

  const [events, setEvents] = useState<LoginEvent[] | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [revokingId, setRevokingId] = useState<number | null>(null);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 60000);
    return () => clearInterval(timer);
  }, []);

  const load = useCallback(async () => {
    try {
      setError(null);
      const res = await apiFetch<ListResponse>("/security/logins");
      setEvents(res.data.events ?? []);
    } catch (err) {
      const e = err as ApiError;
      setError(e.message ?? "Could not load login history");
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const onRevoke = useCallback(
    (event: LoginEvent) => {
      Alert.alert(
        "This wasn't me?",
        "We'll sign every device out and clear your password so only you can set a new one.",
        [
          { text: "Cancel", style: "cancel" },
          {
            text: "Revoke",
            style: "destructive",
            onPress: async () => {
              setRevokingId(event.id);
              try {
                await apiFetch<RevokeResponse>(
                  `/security/logins/${event.id}/revoke`,
                  { method: "POST" },
                );
                await signOut();
                router.replace("/(auth)");
              } catch (err) {
                const e = err as ApiError;
                Alert.alert("Could not revoke", e.message ?? "Please try again.");
              } finally {
                setRevokingId(null);
              }
            },
          },
        ],
      );
    },
    [router, signOut],
  );

  const renderItem = ({ item }: { item: LoginEvent }) => {
    const when = formatWhen(item.created_at, now);
    const badge =
      item.status === "revoked"
        ? { label: "Revoked", bg: colors.destructive + "22", fg: colors.destructive }
        : item.status === "new"
          ? { label: "New device", bg: colors.warning + "22", fg: colors.warning }
          : { label: "Recognized", bg: colors.success + "22", fg: colors.success };
    return (
      <View
        style={[
          styles.row,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <View style={styles.rowHeader}>
          <Text style={[styles.device, { color: colors.foreground }]}>
            {item.device_label ?? "Unknown device"}
          </Text>
          <View
            style={[styles.badge, { backgroundColor: badge.bg }]}
          >
            <Text style={[styles.badgeText, { color: badge.fg }]}>
              {badge.label}
            </Text>
          </View>
        </View>
        <Text style={[styles.muted, { color: colors.mutedForeground }]}>
          {when}
        </Text>
        <Text style={[styles.muted, { color: colors.mutedForeground }]}>
          {item.country_code ?? "Unknown location"}
        </Text>
        {item.status !== "revoked" && (
          <Pressable
            onPress={() => onRevoke(item)}
            disabled={revokingId === item.id}
            style={({ pressed }) => [
              styles.revokeBtn,
              { opacity: pressed || revokingId === item.id ? 0.6 : 1 },
            ]}
            hitSlop={8}
          >
            <Feather name="alert-triangle" size={14} color={colors.destructive} />
            <Text style={[styles.revokeText, { color: colors.destructive }]}>
              {revokingId === item.id ? "Revoking…" : "This wasn't me"}
            </Text>
          </Pressable>
        )}
      </View>
    );
  };

  return (
    <>
      <Stack.Screen options={{ title: "Recent logins" }} />
      <View style={[styles.container, { backgroundColor: colors.background }]}>
        {events === null && !error ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : error ? (
          <View style={styles.center}>
            <Text style={{ color: colors.mutedForeground }}>{error}</Text>
            <Pressable onPress={load} style={styles.retry} hitSlop={8}>
              <Text style={{ color: colors.primary }}>Try again</Text>
            </Pressable>
          </View>
        ) : (
          <FlatList
            data={events ?? []}
            keyExtractor={(it) => String(it.id)}
            renderItem={renderItem}
            contentContainerStyle={styles.listContent}
            refreshControl={
              <RefreshControl
                refreshing={refreshing}
                onRefresh={onRefresh}
                tintColor={colors.primary}
              />
            }
            ListHeaderComponent={
              <Text style={[styles.intro, { color: colors.mutedForeground }]}>
                Every successful sign-in to your account from the last few
                weeks. We email you when a new device, browser, or country
                signs in.
              </Text>
            }
            ListEmptyComponent={
              <Text style={[styles.empty, { color: colors.mutedForeground }]}>
                No login activity recorded yet.
              </Text>
            }
          />
        )}
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12 },
  listContent: { padding: 16, gap: 12 },
  intro: { fontSize: 13, lineHeight: 18, marginBottom: 8 },
  empty: { textAlign: "center", marginTop: 32, fontSize: 14 },
  row: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 14,
    gap: 4,
  },
  rowHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  device: { fontSize: 15, fontWeight: "600", flexShrink: 1, marginRight: 8 },
  muted: { fontSize: 12 },
  badge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  badgeText: { fontSize: 11, fontWeight: "600" },
  revokeBtn: {
    marginTop: 10,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  revokeText: { fontSize: 13, fontWeight: "600" },
  retry: { padding: 8 },
});
