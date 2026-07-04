import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { type Contact, listFollowUps } from "@/lib/api/contacts";

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

  const q = useQuery({
    queryKey: ["follow-ups"],
    queryFn: listFollowUps,
  });

  const Row = ({ c, overdue }: { c: Contact; overdue: boolean }) => {
    const when = c.follow_up_at ? formatWhen(c.follow_up_at) : null;
    const accent = overdue ? colors.destructive : colors.primary;
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
        </View>
        <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
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
    </View>
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
});
