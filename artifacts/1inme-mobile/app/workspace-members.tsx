import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  listWorkspaceMembers,
  type WorkspaceMember,
} from "@/lib/api/workspaces";

export default function WorkspaceMembersScreen() {
  const colors = useColors();
  const { id, name } = useLocalSearchParams<{ id?: string; name?: string }>();
  const wsId = Number(id);

  const q = useQuery({
    queryKey: ["workspace-members", wsId],
    queryFn: () => listWorkspaceMembers(wsId),
    enabled: Number.isFinite(wsId),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: name || "Members" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<WorkspaceMember>
          data={q.data ?? []}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="user" size={16} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name || item.email || `Member #${item.user_id}`}
                </Text>
                {item.email ? (
                  <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                    {item.email}
                  </Text>
                ) : null}
              </View>
              <View
                style={[styles.badge, { backgroundColor: colors.primary + "22" }]}
              >
                <Text style={[styles.badgeText, { color: colors.primary }]}>
                  {item.role}
                </Text>
              </View>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="user-plus"
              title="No members yet"
              body="Invite teammates from the web app to collaborate in this workspace."
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
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
});
