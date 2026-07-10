import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import { useState } from "react";
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
import { TOP_BAR_H, useTabBar, useTabBarBottomInset } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";
import { listConversations } from "@/lib/api/inbox";

const TABS: { key: "open" | "archived"; label: string }[] = [
  { key: "open", label: "Open" },
  { key: "archived", label: "Archived" },
];

const ASSIGNEE_TABS: { key: "all" | "me"; label: string }[] = [
  { key: "all", label: "Everyone" },
  { key: "me", label: "Assigned to me" },
];

export default function InboxTab() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const tabBarBottomInset = useTabBarBottomInset();
  const { reportScroll } = useTabBar();
  const [tab, setTab] = useState<"open" | "archived">("open");
  const [assigneeTab, setAssigneeTab] = useState<"all" | "me">("all");

  const q = useQuery({
    queryKey: ["inbox", "conversations", tab, assigneeTab],
    queryFn: () =>
      listConversations(tab, assigneeTab === "me" ? "me" : undefined),
    // Poll so new conversations / unread updates appear without pull-to-
    // refresh. Longer than the thread's 7s interval to limit load; React
    // Query dedupes overlapping refetches from pull-to-refresh/foreground.
    refetchInterval: 15000,
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View
        style={{
          paddingTop: insets.top + TOP_BAR_H + 12,
          paddingHorizontal: 20,
          paddingBottom: 8,
          flexDirection: "row",
          alignItems: "center",
          justifyContent: "space-between",
        }}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>Inbox</Text>
        <View style={{ flexDirection: "row", gap: 8 }}>
          <Pressable
            onPress={() => router.push("/inbox/spam-settings")}
            hitSlop={8}
            style={({ pressed }) => [
              styles.bellWrap,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: 999,
                opacity: pressed ? 0.7 : 1,
              },
            ]}
          >
            <Feather name="shield" size={18} color={colors.primary} />
          </Pressable>
          <Pressable
            onPress={() => router.push("/inbox/forwarding")}
            hitSlop={8}
            style={({ pressed }) => [
              styles.bellWrap,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: 999,
                opacity: pressed ? 0.7 : 1,
              },
            ]}
          >
            <Feather name="send" size={18} color={colors.primary} />
          </Pressable>
        </View>
      </View>

      <View style={[styles.segment, { paddingHorizontal: 20 }]}>
        {TABS.map((t) => {
          const active = tab === t.key;
          return (
            <Pressable
              key={t.key}
              onPress={() => setTab(t.key)}
              style={[
                styles.segmentItem,
                {
                  backgroundColor: active ? colors.primary + "1c" : "transparent",
                  borderRadius: colors.radius - 4,
                },
              ]}
            >
              <Text
                style={{
                  color: active ? colors.primary : colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 13,
                }}
              >
                {t.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <View style={[styles.segment, { paddingHorizontal: 20 }]}>
        {ASSIGNEE_TABS.map((t) => {
          const active = assigneeTab === t.key;
          return (
            <Pressable
              key={t.key}
              onPress={() => setAssigneeTab(t.key)}
              style={[
                styles.segmentItem,
                {
                  backgroundColor: active ? colors.primary + "1c" : "transparent",
                  borderRadius: colors.radius - 4,
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                },
              ]}
            >
              {t.key === "me" ? (
                <Feather
                  name="user-check"
                  size={12}
                  color={active ? colors.primary : colors.mutedForeground}
                />
              ) : null}
              <Text
                style={{
                  color: active ? colors.primary : colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_500Medium",
                  fontSize: 12,
                }}
              >
                {t.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(c) => String(c.id)}
          onScroll={(e) => reportScroll(e.nativeEvent.contentOffset.y)}
          scrollEventThrottle={16}
          contentContainerStyle={{
            paddingHorizontal: 20,
            paddingBottom: tabBarBottomInset,
            paddingTop: 12,
            gap: 8,
          }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/inbox/${item.id}`)}
              style={({ pressed }) => [
                styles.row,
                {
                  backgroundColor: colors.card,
                  borderColor:
                    item.owner_unread_count > 0 ? colors.primary : colors.border,
                  borderRadius: colors.radius,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}
            >
              <View
                style={[
                  styles.avatar,
                  { backgroundColor: colors.primary + "1c" },
                ]}
              >
                <Feather name="user" size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <View style={styles.rowHead}>
                  <Text
                    numberOfLines={1}
                    style={[styles.rowName, { color: colors.foreground }]}
                  >
                    {item.viewer_name || "Anonymous viewer"}
                  </Text>
                  {item.last_message_at ? (
                    <Text
                      style={[styles.rowTime, { color: colors.mutedForeground }]}
                    >
                      {timeAgo(item.last_message_at)}
                    </Text>
                  ) : null}
                </View>
                <Text
                  numberOfLines={2}
                  style={[styles.rowBody, { color: colors.mutedForeground }]}
                >
                  {item.last_message_preview || "No messages yet"}
                </Text>
                {item.link_title || item.link_alias ? (
                  <Text
                    numberOfLines={1}
                    style={[styles.rowMeta, { color: colors.primary }]}
                  >
                    on {item.link_title || `/${item.link_alias}`}
                  </Text>
                ) : null}
                {item.assignee_name ? (
                  <View
                    style={[
                      styles.assigneeChip,
                      {
                        backgroundColor: colors.primary + "1c",
                        borderColor: colors.primary + "55",
                      },
                    ]}
                  >
                    <Feather name="user" size={10} color={colors.primary} />
                    <Text
                      numberOfLines={1}
                      style={{
                        color: colors.primary,
                        fontFamily: "SpaceGrotesk_500Medium",
                        fontSize: 10,
                      }}
                    >
                      {item.assignee_name}
                    </Text>
                  </View>
                ) : null}
              </View>
              {item.owner_unread_count > 0 ? (
                <View
                  style={[styles.badge, { backgroundColor: colors.primary }]}
                >
                  <Text style={styles.badgeText}>
                    {item.owner_unread_count}
                  </Text>
                </View>
              ) : null}
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="message-circle"
              title={tab === "open" ? "No messages yet" : "No archived chats"}
              body={
                tab === "open"
                  ? "When viewers DM you on a Link in Bio it'll appear here."
                  : "Archived conversations will appear here."
              }
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

function timeAgo(iso: string): string {
  const t = new Date(iso).getTime();
  const s = Math.max(1, Math.floor((Date.now() - t) / 1000));
  if (s < 60) return `${s}s`;
  if (s < 3600) return `${Math.floor(s / 60)}m`;
  if (s < 86400) return `${Math.floor(s / 3600)}h`;
  return `${Math.floor(s / 86400)}d`;
}

const styles = StyleSheet.create({
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 28 },
  bellWrap: {
    width: 40,
    height: 40,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
  },
  segment: { flexDirection: "row", gap: 6, paddingVertical: 4 },
  segmentItem: {
    paddingVertical: 8,
    paddingHorizontal: 14,
  },
  row: {
    flexDirection: "row",
    alignItems: "flex-start",
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
  rowHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  rowName: { flex: 1, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowTime: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  rowBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 17 },
  rowMeta: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, marginTop: 2 },
  badge: {
    minWidth: 22,
    paddingHorizontal: 6,
    height: 22,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  badgeText: {
    color: "#fff",
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11,
  },
  assigneeChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    alignSelf: "flex-start",
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
    borderWidth: 1,
    marginTop: 4,
  },
});
