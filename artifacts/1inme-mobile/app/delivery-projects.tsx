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
  listDeliveryProjects,
  type DeliveryProjectSummary,
} from "@/lib/api/deliveryProjects";

const statusTint = (
  colors: ReturnType<typeof useColors>,
): Record<string, string> => ({
  active: colors.primary,
  completed: colors.success,
  archived: "#9ca3af",
});

export default function DeliveryProjectsScreen() {
  const colors = useColors();
  const router = useRouter();

  const q = useQuery({
    queryKey: ["delivery-projects"],
    queryFn: listDeliveryProjects,
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Delivery projects" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load projects"
          body={
            (q.error as { message?: string })?.message ??
            "Check your connection and try again."
          }
        />
      ) : (
        <FlatList<DeliveryProjectSummary>
          data={q.data ?? []}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => {
            const tint =
              statusTint(colors)[String(item.status).toLowerCase()] ??
              colors.primary;
            return (
              <Pressable
                onPress={() =>
                  router.push(`/delivery-projects/${item.id}` as never)
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
                <View style={{ flex: 1, gap: 6 }}>
                  <View style={styles.rowTop}>
                    <Text
                      style={[styles.name, { color: colors.foreground }]}
                      numberOfLines={1}
                    >
                      {item.title}
                    </Text>
                    <Text style={[styles.pct, { color: tint }]}>
                      {item.progress}%
                    </Text>
                  </View>
                  <View
                    style={[styles.barTrack, { backgroundColor: colors.border }]}
                  >
                    <View
                      style={[
                        styles.barFill,
                        { width: `${item.progress}%`, backgroundColor: tint },
                      ]}
                    />
                  </View>
                  <Text
                    style={[styles.sub, { color: colors.mutedForeground }]}
                    numberOfLines={1}
                  >
                    {item.status_label}
                    {item.tasks_count
                      ? ` · ${item.done_tasks_count ?? 0}/${item.tasks_count} tasks`
                      : " · no tasks"}
                    {item.client_name ? ` · ${item.client_name}` : ""}
                  </Text>
                </View>
                <Feather
                  name="chevron-right"
                  size={16}
                  color={colors.mutedForeground}
                />
              </Pressable>
            );
          }}
          ListEmptyComponent={
            <EmptyState
              icon="clipboard"
              title="No delivery projects yet"
              body="Turn a finalized sale — an invoice, order, or form submission — into a shared project from the website to track delivery here."
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
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  rowTop: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15, flex: 1 },
  pct: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 13 },
  barTrack: { height: 6, borderRadius: 999, overflow: "hidden" },
  barFill: { height: "100%", borderRadius: 999 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, letterSpacing: 0.3 },
});
