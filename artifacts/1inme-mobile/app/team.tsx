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
  listWorkspaceMembers,
  listWorkspaces,
  type Workspace,
  type WorkspaceMember,
} from "@/lib/api/workspaces";

export default function TeamScreen() {
  const colors = useColors();
  const router = useRouter();

  const workspaces = useQuery({
    queryKey: ["workspaces"],
    queryFn: listWorkspaces,
  });

  // Pull members for the personal/owned workspace by default so the
  // Team tab shows something useful immediately. Other workspaces are
  // listed below for quick navigation.
  const primary: Workspace | undefined = (workspaces.data ?? []).find(
    (w) => w.is_personal,
  ) ?? (workspaces.data ?? [])[0];

  const members = useQuery({
    queryKey: ["workspace-members", primary?.id],
    queryFn: () => listWorkspaceMembers(primary!.id),
    enabled: !!primary?.id,
  });

  const loading = workspaces.isLoading || members.isLoading;
  const refetching =
    (workspaces.isFetching && !workspaces.isLoading) ||
    (members.isFetching && !members.isLoading);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Team & staff" }} />
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : workspaces.isError ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load your team"
          body={(workspaces.error as { message?: string })?.message ?? "Try again."}
        />
      ) : (
        <FlatList<WorkspaceMember>
          data={members.data ?? []}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          ListHeaderComponent={
            <View style={{ marginBottom: 8, gap: 6 }}>
              <Text style={[styles.h, { color: colors.foreground }]}>
                {primary?.name ?? "Workspace"}
              </Text>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                {(members.data?.length ?? 0)} member
                {(members.data?.length ?? 0) === 1 ? "" : "s"} ·{" "}
                {primary?.is_personal ? "Personal workspace" : "Shared workspace"}
              </Text>
            </View>
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
              <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="user" size={16} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name || item.email || `Member #${item.user_id}`}
                </Text>
                {item.email ? (
                  <Text style={[styles.subRow, { color: colors.mutedForeground }]} numberOfLines={1}>
                    {item.email}
                  </Text>
                ) : null}
              </View>
              <View style={[styles.badge, { backgroundColor: colors.primary + "22" }]}>
                <Text style={[styles.badgeText, { color: colors.primary }]}>
                  {item.role}
                </Text>
              </View>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="user-plus"
              title="No teammates yet"
              body="Invite teammates from the web app — once they accept they'll appear here."
            />
          }
          ListFooterComponent={
            workspaces.data && workspaces.data.length > 1 ? (
              <View style={{ marginTop: 18, gap: 8 }}>
                <Text style={[styles.h2, { color: colors.foreground }]}>
                  Other workspaces
                </Text>
                {workspaces.data
                  .filter((w) => w.id !== primary?.id)
                  .map((w) => (
                    <Pressable
                      key={w.id}
                      onPress={() =>
                        router.push({
                          pathname: "/workspace-members",
                          params: { id: String(w.id), name: w.name },
                        })
                      }
                      style={({ pressed }) => [
                        styles.row,
                        {
                          backgroundColor: colors.card,
                          borderColor: colors.border,
                          borderRadius: colors.radius,
                          opacity: pressed ? 0.7 : 1,
                        },
                      ]}
                    >
                      <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
                        <Feather name="briefcase" size={16} color={colors.primary} />
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                          {w.name}
                        </Text>
                      </View>
                      <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                    </Pressable>
                  ))}
              </View>
            ) : null
          }
          refreshControl={
            <RefreshControl
              refreshing={refetching}
              onRefresh={() => {
                workspaces.refetch();
                members.refetch();
              }}
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
  h: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  subRow: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
});
