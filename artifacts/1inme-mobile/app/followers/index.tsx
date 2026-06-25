import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
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
import { listFollowers, listFollowing } from "@/lib/api/follows";

const TABS = [
  { key: "followers", label: "Followers" },
  { key: "following", label: "Following" },
] as const;

type Tab = typeof TABS[number]["key"];

export default function FollowersScreen() {
  const colors = useColors();
  const [tab, setTab] = useState<Tab>("followers");

  const q = useQuery({
    queryKey: ["follows", tab],
    queryFn: () => (tab === "followers" ? listFollowers() : listFollowing()),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Network",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      <View style={[styles.segment, { paddingHorizontal: 20, paddingTop: 12 }]}>
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
      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(u) => String(u.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
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
              <View style={{ flex: 1 }}>
                <Text
                  numberOfLines={1}
                  style={[styles.name, { color: colors.foreground }]}
                >
                  {item.display_name || item.name || "User"}
                </Text>
                {item.handle ? (
                  <Text
                    numberOfLines={1}
                    style={[styles.handle, { color: colors.mutedForeground }]}
                  >
                    @{item.handle}
                  </Text>
                ) : null}
              </View>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="users"
              title={tab === "followers" ? "No followers yet" : "Not following anyone"}
              body={
                tab === "followers"
                  ? "Share your Link in Bio to grow your audience."
                  : "Find creators to follow on 1INME."
              }
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  segment: { flexDirection: "row", gap: 6 },
  segmentItem: { paddingVertical: 8, paddingHorizontal: 14 },
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
  handle: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
});
