import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  listSessions,
  revokeOtherSessions,
  revokeSession,
  type SessionInfo,
} from "@/lib/api/sessions";

function platformIcon(platform: string | null, kind: string): keyof typeof Feather.glyphMap {
  if (kind === "web") return "globe";
  if (platform === "ios" || platform === "macos") return "smartphone";
  if (platform === "android") return "smartphone";
  if (platform === "windows" || platform === "linux") return "monitor";
  return "smartphone";
}

function timeAgo(iso: string | null): string {
  if (!iso) return "—";
  const t = Date.parse(iso);
  if (Number.isNaN(t)) return iso;
  const sec = Math.floor((Date.now() - t) / 1000);
  if (sec < 60) return "just now";
  if (sec < 3600) return `${Math.floor(sec / 60)}m ago`;
  if (sec < 86400) return `${Math.floor(sec / 3600)}h ago`;
  return `${Math.floor(sec / 86400)}d ago`;
}

function confirm(title: string, message: string, onYes: () => void) {
  if (Platform.OS === "web") {
    if (typeof window !== "undefined" && window.confirm(`${title}\n\n${message}`)) onYes();
    return;
  }
  Alert.alert(title, message, [
    { text: "Cancel", style: "cancel" },
    { text: "Confirm", style: "destructive", onPress: onYes },
  ]);
}

export default function AccountSessions() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["sessions"], queryFn: listSessions });

  const revokeOne = useMutation({
    mutationFn: (id: string) => revokeSession(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["sessions"] }),
    onError: (e: any) =>
      Alert.alert("Could not revoke", e?.message ?? "Unknown error"),
  });

  const revokeOthers = useMutation({
    mutationFn: () => revokeOtherSessions(),
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ["sessions"] });
      Alert.alert(
        "Done",
        `Signed out ${r.revoked_tokens + r.revoked_sessions} other session${
          r.revoked_tokens + r.revoked_sessions === 1 ? "" : "s"
        }.`,
      );
    },
    onError: (e: any) =>
      Alert.alert("Could not sign out", e?.message ?? "Unknown error"),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Devices & sessions" }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 12 }}
        refreshControl={
          <RefreshControl
            refreshing={q.isFetching && !q.isLoading}
            onRefresh={() => q.refetch()}
            tintColor={colors.primary}
          />
        }
      >
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Every browser and app currently signed into your account. Revoke
          anything you don&apos;t recognise.
        </Text>

        <Pressable
          onPress={() =>
            confirm(
              "Sign out everywhere else?",
              "You'll stay signed in on this device.",
              () => revokeOthers.mutate(),
            )
          }
          disabled={revokeOthers.isPending}
          style={({ pressed }) => [
            styles.dangerBtn,
            {
              borderColor: colors.destructive ?? "#ef4444",
              backgroundColor: (colors.destructive ?? "#ef4444") + "1a",
              opacity: pressed || revokeOthers.isPending ? 0.6 : 1,
            },
          ]}
        >
          <Feather name="log-out" size={16} color={colors.destructive ?? "#ef4444"} />
          <Text style={[styles.dangerLabel, { color: colors.destructive ?? "#ef4444" }]}>
            Sign out everywhere except this
          </Text>
        </Pressable>

        {q.isLoading ? (
          <View style={{ padding: 24, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError ? (
          <Text style={{ color: colors.destructive ?? "#ef4444" }}>
            {(q.error as any)?.message ?? "Failed to load sessions"}
          </Text>
        ) : (q.data ?? []).length === 0 ? (
          <Text style={{ color: colors.mutedForeground }}>
            No active sessions.
          </Text>
        ) : (
          (q.data ?? []).map((s: SessionInfo) => (
            <View
              key={s.id}
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={styles.cardHeader}>
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: colors.primary + "1a" },
                  ]}
                >
                  <Feather
                    name={platformIcon(s.platform, s.kind)}
                    size={18}
                    color={colors.primary}
                  />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <View style={styles.titleRow}>
                    <Text
                      style={[styles.title, { color: colors.foreground }]}
                      numberOfLines={1}
                    >
                      {s.device_label}
                    </Text>
                    {s.is_current ? (
                      <Text
                        style={[
                          styles.badge,
                          {
                            color: colors.success,
                            backgroundColor: colors.success + "1f",
                          },
                        ]}
                      >
                        This device
                      </Text>
                    ) : null}
                  </View>
                  <Text
                    style={[styles.meta, { color: colors.mutedForeground }]}
                  >
                    {(s.country || "Unknown country") +
                      (s.ip ? ` · ${s.ip}` : "")}
                  </Text>
                  <Text
                    style={[styles.meta, { color: colors.mutedForeground }]}
                  >
                    Last active {timeAgo(s.last_active_at)}
                    {s.first_seen_at
                      ? ` · First seen ${timeAgo(s.first_seen_at)}`
                      : ""}
                  </Text>
                  {s.user_agent ? (
                    <Text
                      numberOfLines={2}
                      style={[styles.ua, { color: colors.mutedForeground }]}
                    >
                      {s.user_agent}
                    </Text>
                  ) : null}
                </View>
              </View>

              {!s.is_current ? (
                <Pressable
                  onPress={() =>
                    confirm(
                      "Revoke session?",
                      "The device will be signed out on its next request.",
                      () => revokeOne.mutate(s.id),
                    )
                  }
                  disabled={revokeOne.isPending}
                  style={({ pressed }) => [
                    styles.revokeBtn,
                    {
                      borderColor: colors.destructive ?? "#ef4444",
                      opacity: pressed ? 0.6 : 1,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: colors.destructive ?? "#ef4444",
                      fontWeight: "600",
                      fontSize: 13,
                    }}
                  >
                    Revoke
                  </Text>
                </Pressable>
              ) : null}
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  intro: { fontSize: 13, lineHeight: 18 },
  dangerBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  dangerLabel: { fontSize: 14, fontWeight: "600" },
  card: { borderWidth: 1, padding: 14, gap: 10 },
  cardHeader: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  titleRow: { flexDirection: "row", alignItems: "center", gap: 8, flexWrap: "wrap" },
  title: { fontSize: 15, fontWeight: "600", flexShrink: 1 },
  badge: {
    fontSize: 10,
    fontWeight: "700",
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
    overflow: "hidden",
    textTransform: "uppercase",
  },
  meta: { fontSize: 12, marginTop: 2 },
  ua: { fontSize: 11, marginTop: 4, fontStyle: "italic" },
  revokeBtn: {
    alignSelf: "flex-end",
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
  },
});
