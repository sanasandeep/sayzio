import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
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
  listWorkspaces,
  workspaceFeatherIcon,
  type Workspace,
} from "@/lib/api/workspaces";

export default function WorkspacesScreen() {
  const colors = useColors();
  const router = useRouter();

  const q = useQuery({ queryKey: ["workspaces"], queryFn: listWorkspaces });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Workspaces" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<Workspace>
          data={q.data ?? []}
          keyExtractor={(w) => String(w.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/workspace-members?id=${item.id}&name=${encodeURIComponent(item.name)}` as never)}
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View
                style={[
                  styles.iconWrap,
                  { backgroundColor: (item.color ?? colors.primary) + "26" },
                ]}
              >
                <Feather
                  name={workspaceFeatherIcon(item)}
                  size={18}
                  color={item.color ?? colors.primary}
                />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.is_personal ? "Personal workspace" : "Team workspace"}
                  {item.slug ? ` • ${item.slug}` : ""}
                </Text>
              </View>
              {item.is_owner ? (
                <Pressable
                  onPress={() => router.push(`/workspace-edit?id=${item.id}` as never)}
                  hitSlop={8}
                  style={({ pressed }) => [
                    styles.gear,
                    { backgroundColor: pressed ? colors.muted : "transparent" },
                  ]}
                  accessibilityRole="button"
                  accessibilityLabel={`Edit workspace ${item.name}`}
                >
                  <Feather name="edit-2" size={16} color={colors.mutedForeground} />
                </Pressable>
              ) : null}
              <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="users"
              title="No workspaces yet"
              body="Workspaces let you collaborate with teammates on the same Link in Bio pages, posts and contacts."
            />
          }
          ListFooterComponent={
            (q.data?.length ?? 0) > 0 ? (
              <Text style={[styles.footer, { color: colors.mutedForeground }]}>
                Tap the edit icon to rename or restyle a workspace you own. Creating new workspaces and deleting them happens on the web app.
              </Text>
            ) : null
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
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 16, borderWidth: 1 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  gear: {
    width: 34,
    height: 34,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 999,
  },
  footer: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    marginTop: 16,
  },
});
