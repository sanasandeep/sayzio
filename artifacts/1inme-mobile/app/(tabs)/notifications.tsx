import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ActivityIndicator,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  listNotifications,
  markAllRead,
  markRead,
} from "@/lib/api/notifications";

export default function NotificationsTab() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const webTop = Platform.OS === "web" ? 67 : 0;

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

  const refreshing = q.isFetching && !q.isLoading;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View
        style={{
          paddingTop: insets.top + 12 + webTop,
          paddingHorizontal: 20,
          paddingBottom: 12,
          flexDirection: "row",
          alignItems: "center",
          justifyContent: "space-between",
        }}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>Inbox</Text>
        {(q.data?.unreadCount ?? 0) > 0 ? (
          <Pressable
            onPress={() => markAll.mutate()}
            hitSlop={8}
            style={({ pressed }) => ({ opacity: pressed ? 0.7 : 1 })}
          >
            <Text style={[styles.action, { color: colors.primary }]}>
              Mark all read
            </Text>
          </Pressable>
        ) : null}
      </View>

      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(n) => String(n.id)}
          contentContainerStyle={{
            paddingHorizontal: 20,
            paddingBottom: 32,
            gap: 8,
          }}
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
                      style={[styles.rowBody, { color: colors.mutedForeground }]}
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
              body="Updates about your links and followers will appear here."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
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
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 28 },
  action: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
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
