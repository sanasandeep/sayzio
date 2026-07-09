import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
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

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  disconnectCalendar,
  listCalendarAccounts,
  type CalendarAccount,
} from "@/lib/api/calendar";
import { showAlert } from "@/lib/webAlert";

export default function CalendarScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["calendar-accounts"], queryFn: listCalendarAccounts });

  const remove = useMutation({
    mutationFn: (id: number) => disconnectCalendar(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["calendar-accounts"] }),
  });

  const confirmRemove = (a: CalendarAccount) => {
    const label = a.account_email || a.display_name || a.provider;
    const go = () => remove.mutate(a.id);
    if (Platform.OS === "web") {
      if (confirm(`Disconnect ${label}?`)) go();
    } else {
      showAlert("Disconnect calendar?", label, [
        { text: "Cancel", style: "cancel" },
        { text: "Disconnect", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Calendars" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<CalendarAccount>
          data={q.data ?? []}
          keyExtractor={(a) => String(a.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="calendar" size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.display_name || item.account_email || item.provider}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.provider}
                  {item.last_synced_at ? ` • synced ${new Date(item.last_synced_at).toLocaleDateString()}` : ""}
                </Text>
              </View>
              <Pressable onPress={() => confirmRemove(item)} hitSlop={6}>
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="calendar"
              title="No calendars connected"
              body="Connect Google Calendar from the web app to mirror events into your Link in Bio pages."
            />
          }
          ListFooterComponent={
            (q.data?.length ?? 0) > 0 ? (
              <Text style={[styles.footer, { color: colors.mutedForeground }]}>
                Add or re-authorise calendars from the web — Google's OAuth flow lives there.
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
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  footer: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    marginTop: 16,
  },
});
