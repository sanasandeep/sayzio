import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { useForegroundRefresh } from "@/hooks/useForegroundRefresh";
import {
  getOwnerOrders,
  ORDER_ACTION_LABELS,
  ORDER_STATUS_FLOW,
  OPEN_ORDER_STATUSES,
  pollOwnerOrders,
  updateOwnerOrderStatus,
  type OwnerOrder,
} from "@/lib/api/restaurant";

const statusColors = (
  colors: ReturnType<typeof useColors>,
): Record<string, string> => ({
  new: colors.destructive,
  accepted: colors.warning,
  preparing: "#3b82f6",
  ready: colors.success,
  completed: "#6b7280",
  cancelled: "#9ca3af",
});

export default function RestaurantOrdersScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ id: string }>();
  const linkId = String(params.id ?? "");
  const qc = useQueryClient();

  const [filter, setFilter] = useState<"open" | "all">("open");
  const [orders, setOrders] = useState<OwnerOrder[]>([]);
  const [openCount, setOpenCount] = useState(0);
  const cursor = useRef<string | null>(null);

  const initial = useQuery({
    queryKey: ["restaurant-owner-orders", linkId],
    queryFn: () => getOwnerOrders(linkId),
    enabled: linkId.length > 0,
  });

  const merge = useCallback((incoming: OwnerOrder[]) => {
    setOrders((prev) => {
      const map = new Map(prev.map((o) => [o.id, o]));
      incoming.forEach((o) => map.set(o.id, o));
      return Array.from(map.values()).sort((a, b) => b.id - a.id);
    });
  }, []);

  useEffect(() => {
    if (initial.data) {
      merge(initial.data.orders);
      setOpenCount(initial.data.open_count);
      cursor.current = initial.data.server_time;
    }
  }, [initial.data, merge]);

  const pollNow = useCallback(async () => {
    if (linkId.length === 0) return;
    try {
      const res = await pollOwnerOrders(linkId, cursor.current);
      cursor.current = res.server_time;
      setOpenCount(res.open_count);
      if (res.orders.length) merge(res.orders);
    } catch {
      // ignore transient poll errors
    }
  }, [linkId, merge]);

  useEffect(() => {
    if (linkId.length === 0) return;
    const t = setInterval(pollNow, 6000);
    return () => clearInterval(t);
  }, [linkId, pollNow]);

  // Timers pause while backgrounded — catch up as soon as the app resumes.
  useForegroundRefresh(pollNow);

  const setStatus = useMutation({
    mutationFn: ({ orderId, status }: { orderId: number; status: string }) =>
      updateOwnerOrderStatus(linkId, orderId, status),
    onSuccess: (order) => {
      merge([order]);
      qc.invalidateQueries({ queryKey: ["restaurant-owner-orders", linkId] });
    },
  });

  const visible =
    filter === "open"
      ? orders.filter((o) => OPEN_ORDER_STATUSES.includes(o.status))
      : orders;

  if (initial.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Orders" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Orders" }} />
      <View style={styles.tabs}>
        {(["open", "all"] as const).map((f) => (
          <Pressable
            key={f}
            onPress={() => setFilter(f)}
            style={[
              styles.tab,
              { borderColor: colors.border },
              filter === f && { backgroundColor: colors.primary },
            ]}
          >
            <Text
              style={{
                color: filter === f ? "#fff" : colors.mutedForeground,
                fontWeight: "600",
                fontSize: 13,
              }}
            >
              {f === "open" ? `Open (${openCount})` : "All"}
            </Text>
          </Pressable>
        ))}
      </View>

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
        {visible.length === 0 ? (
          <View style={styles.center}>
            <Feather name="inbox" size={32} color={colors.mutedForeground} />
            <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
              No orders yet. New orders appear here automatically.
            </Text>
          </View>
        ) : null}

        {visible.map((o) => (
          <View
            key={o.id}
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.cardHead}>
              <View>
                <Text style={[styles.table, { color: colors.foreground }]}>
                  {o.table_label ? `Table ${o.table_label}` : "Walk-in"}
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {o.customer_name ? `${o.customer_name} · ` : ""}#{o.id}
                </Text>
              </View>
              <View
                style={[
                  styles.statusPill,
                  { backgroundColor: statusColors(colors)[o.status] ?? "#6b7280" },
                ]}
              >
                <Text style={styles.statusText}>{o.status_label}</Text>
              </View>
            </View>

            <View style={{ marginTop: 10 }}>
              {o.items.map((it) => (
                <View key={it.id} style={styles.line}>
                  <Text style={{ color: colors.foreground, fontSize: 13.5 }}>
                    {it.quantity}× {it.name}
                  </Text>
                  <Text style={{ color: colors.foreground, fontSize: 13.5 }}>
                    {o.currency} {Number(it.line_total).toFixed(2)}
                  </Text>
                </View>
              ))}
            </View>

            {o.customer_note ? (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontStyle: "italic",
                  marginTop: 6,
                  fontSize: 12.5,
                }}
              >
                “{o.customer_note}”
              </Text>
            ) : null}

            <View
              style={[styles.total, { borderTopColor: colors.border }]}
            >
              <View style={styles.breakdownRow}>
                <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                  Subtotal
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                  {o.currency} {Number(o.subtotal).toFixed(2)}
                </Text>
              </View>
              {Number(o.discount_amount ?? 0) > 0 ? (
                <View style={styles.breakdownRow}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                    Discount{o.coupon_code ? ` (${o.coupon_code})` : ""}
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                    −{o.currency} {Number(o.discount_amount).toFixed(2)}
                  </Text>
                </View>
              ) : null}
              {Number(o.tax_rate ?? 0) > 0 ? (
                <View style={styles.breakdownRow}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                    Tax ({Number(o.tax_rate).toFixed(2)}%)
                    {o.tax_inclusive ? " incl." : ""}
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                    {o.tax_inclusive
                      ? "incl."
                      : `${o.currency} ${Number(o.tax_amount ?? 0).toFixed(2)}`}
                  </Text>
                </View>
              ) : null}
              <View style={[styles.breakdownRow, { marginTop: 4 }]}>
                <Text style={{ color: colors.foreground, fontWeight: "700" }}>
                  Estimated total
                </Text>
                <Text style={{ color: colors.foreground, fontWeight: "700" }}>
                  {o.currency}{" "}
                  {Number(o.total ?? o.subtotal).toFixed(2)}
                </Text>
              </View>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 11,
                  marginTop: 6,
                }}
              >
                Estimated bill, not the actual bill.
              </Text>
            </View>

            <View style={styles.actions}>
              {(ORDER_STATUS_FLOW[o.status] ?? []).map((s) => (
                <Pressable
                  key={s}
                  disabled={setStatus.isPending}
                  onPress={() =>
                    setStatus.mutate({ orderId: o.id, status: s })
                  }
                  style={[
                    styles.actionBtn,
                    s === "cancelled"
                      ? { borderColor: colors.destructive }
                      : { backgroundColor: colors.primary },
                  ]}
                >
                  <Text
                    style={{
                      color: s === "cancelled" ? colors.destructive : "#fff",
                      fontWeight: "600",
                      fontSize: 12.5,
                    }}
                  >
                    {ORDER_ACTION_LABELS[s] ?? s}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", paddingVertical: 50 },
  tabs: { flexDirection: "row", gap: 8, paddingHorizontal: 16, paddingTop: 12 },
  tab: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  card: { borderWidth: 1, borderRadius: 16, padding: 16, marginBottom: 14 },
  cardHead: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
  },
  table: { fontSize: 16, fontWeight: "700" },
  statusPill: { paddingHorizontal: 11, paddingVertical: 4, borderRadius: 999 },
  statusText: { color: "#fff", fontWeight: "700", fontSize: 12 },
  line: { flexDirection: "row", justifyContent: "space-between", paddingVertical: 3 },
  total: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
  },
  breakdownRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    paddingVertical: 2,
  },
  actions: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 12 },
  actionBtn: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "transparent",
  },
});
