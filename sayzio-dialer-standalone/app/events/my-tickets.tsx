import { router } from "expo-router";
import { useCallback, useEffect, useState } from "react";
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
import { type EventTicket, getMyEventTickets } from "@/lib/api/events";

export default function MyTicketsScreen() {
  const colors = useColors();
  const [tickets, setTickets] = useState<EventTicket[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    const res = await getMyEventTickets();
    setTickets(res.items);
  }, []);

  useEffect(() => {
    load().finally(() => setLoading(false));
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await load();
    } finally {
      setRefreshing(false);
    }
  }, [load]);

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (tickets.length === 0) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <EmptyState
          icon="calendar"
          title="No tickets yet"
          body="Tickets you buy from the events directory will show up here."
        />
      </View>
    );
  }

  return (
    <FlatList
      style={{ backgroundColor: colors.background }}
      data={tickets}
      keyExtractor={(t) => String(t.id)}
      contentContainerStyle={{ padding: 16, gap: 12 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      renderItem={({ item }) => (
        <Pressable
          onPress={() =>
            item.event && router.push(`/events/ticket/${item.event.alias}/${item.code}`)
          }
          style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}
        >
          <Text style={{ color: colors.foreground, fontWeight: "700" }}>
            {item.event?.title ?? "Event"}
          </Text>
          {item.event?.start_date ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
              {new Date(item.event.start_date).toLocaleString()}
            </Text>
          ) : null}
          <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
            {item.tier?.name} · {item.quantity} ticket{item.quantity > 1 ? "s" : ""}
          </Text>
          <Text
            style={{
              color: item.status === "checked_in" ? colors.primary : colors.foreground,
              fontWeight: "600",
              marginTop: 2,
              textTransform: "capitalize",
            }}
          >
            {item.status.replace("_", " ")}
          </Text>
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { borderWidth: 1, borderRadius: 16, padding: 16, gap: 3 },
});
