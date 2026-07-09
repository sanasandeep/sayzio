import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  fulfillOrder,
  getOwnerOrders,
  type OwnerOrder,
} from "@/lib/api/store";
import { showAlert } from "@/lib/webAlert";

function fmtMoney(cents: number, currency: string): string {
  const amount = (cents / 100).toFixed(cents % 100 ? 2 : 0);
  const cur = (currency || "USD").toUpperCase();
  const symbol = cur === "USD" ? "$" : cur === "EUR" ? "€" : cur === "GBP" ? "£" : "";
  return symbol ? `${symbol}${amount}` : `${amount} ${cur}`;
}

type Filter = "all" | "paid" | "fulfilled" | "cancelled";

const FILTERS: { key: Filter; label: string }[] = [
  { key: "all", label: "All" },
  { key: "paid", label: "Paid" },
  { key: "fulfilled", label: "Fulfilled" },
  { key: "cancelled", label: "Cancelled" },
];

/**
 * Owner Orders dashboard (Task #1763). Lists product orders for the
 * signed-in creator and lets them mark a paid physical order fulfilled.
 * Consumes /me/creator/orders and /me/creator/orders/{id}/fulfill.
 */
export default function OrdersScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [filter, setFilter] = useState<Filter>("all");

  const q = useQuery({
    queryKey: ["owner-orders", filter],
    queryFn: () => getOwnerOrders(filter === "all" ? undefined : filter),
  });

  const fulfill = useMutation({
    mutationFn: (orderId: number) => fulfillOrder(orderId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["owner-orders"] });
    },
    onError: (e: Error) => showAlert("Couldn't update", e.message || "Try again"),
  });

  const orders = q.data?.items ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Orders" }} />

      <View style={{ flexDirection: "row", gap: 8, padding: 16, paddingBottom: 8, flexWrap: "wrap" }}>
        {FILTERS.map((f) => {
          const active = filter === f.key;
          return (
            <Pressable
              key={f.key}
              onPress={() => setFilter(f.key)}
              style={{
                paddingHorizontal: 14,
                paddingVertical: 7,
                borderRadius: 999,
                backgroundColor: active ? colors.primary : colors.card,
                borderWidth: 1,
                borderColor: active ? colors.primary : colors.border,
              }}
            >
              <Text
                style={{
                  color: active ? colors.primaryForeground ?? "#fff" : colors.foreground,
                  fontWeight: "700",
                  fontSize: 13,
                }}
              >
                {f.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <ScrollView contentContainerStyle={{ padding: 16, paddingTop: 8, gap: 12 }}>
        {q.isLoading && (
          <View style={{ alignItems: "center", paddingVertical: 48 }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        )}

        {q.error && (
          <View style={{ alignItems: "center", paddingVertical: 48, gap: 12 }}>
            <Feather name="alert-circle" size={36} color={colors.mutedForeground} />
            <Text style={{ color: colors.foreground }}>{(q.error as Error).message}</Text>
          </View>
        )}

        {q.data && orders.length === 0 && (
          <View style={{ alignItems: "center", paddingVertical: 48, gap: 12 }}>
            <Feather name="shopping-bag" size={40} color={colors.mutedForeground} />
            <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 16 }}>No orders yet</Text>
            <Text style={{ color: colors.mutedForeground, textAlign: "center", paddingHorizontal: 24 }}>
              When someone buys a product from your page, it&apos;ll show up here.
            </Text>
          </View>
        )}

        {orders.map((o) => (
          <OrderCard
            key={o.id}
            order={o}
            colors={colors}
            onFulfill={() => fulfill.mutate(o.id)}
            fulfilling={fulfill.isPending && fulfill.variables === o.id}
          />
        ))}
      </ScrollView>
    </View>
  );
}

function OrderCard({
  order,
  colors,
  onFulfill,
  fulfilling,
}: {
  order: OwnerOrder;
  colors: ReturnType<typeof useColors>;
  onFulfill: () => void;
  fulfilling: boolean;
}) {
  const statusColor =
    order.status === "fulfilled"
      ? colors.success
      : order.status === "cancelled"
        ? colors.destructive
        : colors.primary;
  const canFulfill = order.status === "paid" && order.contains_physical;

  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 8 }}>
        <Text style={{ color: colors.foreground, fontWeight: "800", flex: 1 }}>Order #{order.id}</Text>
        <View
          style={{
            paddingHorizontal: 10,
            paddingVertical: 3,
            borderRadius: 999,
            backgroundColor: statusColor + "22",
          }}
        >
          <Text style={{ fontSize: 11, fontWeight: "700", color: statusColor }}>{order.status_label}</Text>
        </View>
      </View>

      <View style={{ flexDirection: "row", alignItems: "center", gap: 10, marginBottom: 10 }}>
        {order.buyer?.avatar ? (
          <Image source={{ uri: order.buyer.avatar }} style={{ width: 32, height: 32, borderRadius: 999 }} />
        ) : (
          <View
            style={{
              width: 32,
              height: 32,
              borderRadius: 999,
              backgroundColor: colors.background,
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <Feather name="user" size={16} color={colors.mutedForeground} />
          </View>
        )}
        <Text style={{ color: colors.mutedForeground, flex: 1 }} numberOfLines={1}>
          {order.buyer?.name ?? "Customer"}
          {order.buyer?.handle ? ` · @${order.buyer.handle}` : ""}
        </Text>
      </View>

      {order.items.map((it) => (
        <View key={it.id} style={{ flexDirection: "row", alignItems: "center", gap: 8, paddingVertical: 4 }}>
          <Feather
            name={it.product_type === "physical" ? "package" : "download"}
            size={14}
            color={colors.mutedForeground}
          />
          <Text style={{ color: colors.foreground, flex: 1 }} numberOfLines={1}>
            {it.quantity} × {it.name}
          </Text>
          <Text style={{ color: colors.mutedForeground }}>
            {fmtMoney(it.line_total_cents, it.currency)}
          </Text>
        </View>
      ))}

      <View
        style={{
          flexDirection: "row",
          alignItems: "center",
          marginTop: 10,
          paddingTop: 10,
          borderTopWidth: StyleSheet.hairlineWidth,
          borderTopColor: colors.border,
        }}
      >
        <Text style={{ color: colors.mutedForeground, flex: 1, fontWeight: "600" }}>Total</Text>
        <Text style={{ color: colors.foreground, fontWeight: "800" }}>
          {fmtMoney(order.subtotal_cents, order.currency)}
        </Text>
      </View>

      {canFulfill ? (
        <Button
          label="Mark fulfilled"
          variant="outline"
          loading={fulfilling}
          style={{ marginTop: 12 }}
          onPress={onFulfill}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 16,
  },
});
