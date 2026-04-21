import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  listSubscribers,
  unsubscribe,
  type Subscriber,
} from "@/lib/api/subscribers";

export default function SubscribersScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");

  const q = useQuery({
    queryKey: ["subscribers", search],
    queryFn: () => listSubscribers({ q: search || undefined, per_page: 50 }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => unsubscribe(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["subscribers"] }),
  });

  const confirmRemove = (s: Subscriber) => {
    const go = () => remove.mutate(s.id);
    const label = s.email || s.phone || s.name || `#${s.id}`;
    if (Platform.OS === "web") {
      if (confirm(`Unsubscribe ${label}?`)) go();
    } else {
      Alert.alert("Unsubscribe?", `${label} will stop receiving messages.`, [
        { text: "Cancel", style: "cancel" },
        { text: "Unsubscribe", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Subscribers" }} />
      <View style={{ paddingHorizontal: 20, paddingTop: 12 }}>
        <View
          style={[
            styles.searchWrap,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <Feather name="search" size={16} color={colors.mutedForeground} />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder="Search by name or email"
            placeholderTextColor={colors.mutedForeground}
            style={[styles.searchInput, { color: colors.foreground }]}
            autoCapitalize="none"
            autoCorrect={false}
          />
        </View>
        <Text style={[styles.total, { color: colors.mutedForeground }]}>
          {q.data ? `${q.data.total} subscriber${q.data.total === 1 ? "" : "s"}` : "Loading…"}
        </Text>
      </View>

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<Subscriber>
          data={q.data?.items ?? []}
          keyExtractor={(s) => String(s.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name={item.type === "email" ? "mail" : "message-circle"} size={16} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name || item.email || item.phone || `Subscriber #${item.id}`}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.email || item.phone || "—"}
                  {item.source ? ` • ${item.source}` : ""}
                </Text>
              </View>
              {item.status === "active" ? (
                <Pressable onPress={() => confirmRemove(item)} hitSlop={6}>
                  <Feather name="user-x" size={18} color={colors.destructive} />
                </Pressable>
              ) : (
                <View
                  style={[styles.badge, { backgroundColor: colors.muted ?? colors.border }]}
                >
                  <Text style={[styles.badgeText, { color: colors.mutedForeground }]}>
                    {item.status}
                  </Text>
                </View>
              )}
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="users"
              title={search ? "No matches" : "No subscribers yet"}
              body={
                search
                  ? "Try a different search term."
                  : "When people subscribe to your biolinks, they appear here."
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

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  searchWrap: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    borderWidth: 1,
    minHeight: 44,
  },
  searchInput: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    paddingVertical: 8,
  },
  total: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    marginTop: 8,
    marginLeft: 4,
  },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 12, borderWidth: 1 },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
});
