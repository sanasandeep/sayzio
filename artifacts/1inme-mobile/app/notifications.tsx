import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router } from "expo-router";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  listNotifications,
  markAllRead,
  markRead,
} from "@/lib/api/notifications";

export default function NotificationsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["notifications"],
    queryFn: listNotifications,
  });

  const markAll = useMutation({
    mutationFn: markAllRead,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const markOne = useMutation({
    mutationFn: (id: number) => markRead(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Notifications",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <View style={{ flexDirection: "row", alignItems: "center", gap: 14, paddingRight: 12 }}>
              {(q.data?.unreadCount ?? 0) > 0 ? (
                <Pressable onPress={() => markAll.mutate()} hitSlop={8}>
                  <Text
                    style={{
                      color: colors.primary,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 13,
                    }}
                  >
                    Mark all
                  </Text>
                </Pressable>
              ) : null}
              <Pressable
                onPress={() => router.push("/api-usage")}
                hitSlop={8}
              >
                <Feather name="activity" size={18} color={colors.primary} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/notification-preferences")}
                hitSlop={8}
              >
                <Feather name="settings" size={18} color={colors.primary} />
              </Pressable>
            </View>
          ),
        }}
      />

      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(n) => String(n.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          renderItem={({ item }) => {
            const unread = !item.read_at;
            return (
              <Pressable
                onPress={() => unread && markOne.mutate(item.id)}
                style={[
                  styles.row,
                  {
                    backgroundColor: colors.card,
                    borderColor: unread ? colors.primary : colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: colors.primary + "1c" },
                  ]}
                >
                  <Feather name="bell" size={16} color={colors.primary} />
                </View>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text
                    numberOfLines={1}
                    style={[styles.rowTitle, { color: colors.foreground }]}
                  >
                    {item.title || item.type || "Notification"}
                  </Text>
                  {item.body ? (
                    <Text
                      numberOfLines={2}
                      style={[
                        styles.rowBody,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      {item.body}
                    </Text>
                  ) : null}
                </View>
                {unread ? (
                  <View
                    style={[
                      styles.unreadDot,
                      { backgroundColor: colors.primary },
                    ]}
                  />
                ) : null}
              </Pressable>
            );
          }}
          ListEmptyComponent={
            <EmptyState
              icon="bell"
              title="No notifications yet"
              body="Updates about your links and followers appear here."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  rowTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, lineHeight: 16 },
  unreadDot: { width: 8, height: 8, borderRadius: 999 },
});
