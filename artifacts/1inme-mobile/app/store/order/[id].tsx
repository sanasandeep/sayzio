import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import {
  ActivityIndicator,
  Alert,
  Image,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getOrder, type ProductOrder } from "@/lib/api/store";

function fmtMoney(cents: number, currency: string): string {
  const amount = (cents / 100).toFixed(cents % 100 ? 2 : 0);
  const cur = (currency || "USD").toUpperCase();
  const symbol = cur === "USD" ? "$" : cur === "EUR" ? "€" : cur === "GBP" ? "£" : "";
  return symbol ? `${symbol}${amount}` : `${amount} ${cur}`;
}

/**
 * Buyer order / thank-you screen (Task #1763). After returning from the
 * hosted-checkout browser, this polls the order status until it flips to
 * paid, then shows the creator's thank-you message and — for digital
 * items — download buttons. Mirrors the web BiolinkStoreController::thankYou.
 */
export default function OrderScreen() {
  const colors = useColors();
  const router = useRouter();
  const { id = "" } = useLocalSearchParams<{ id?: string }>();
  const orderId = Number(id);

  const q = useQuery<ProductOrder>({
    queryKey: ["store-order", orderId],
    queryFn: () => getOrder(orderId),
    enabled: Number.isFinite(orderId) && orderId > 0,
    // Poll while still pending so the screen flips to "paid" once the
    // provider confirms (the return handler stamps the order paid).
    refetchInterval: (query) =>
      query.state.data && !query.state.data.is_paid ? 4000 : false,
  });

  const order = q.data;
  const paid = !!order?.is_paid;

  const openDownload = async (url: string) => {
    try {
      await WebBrowser.openBrowserAsync(url);
    } catch {
      Linking.openURL(url);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: paid ? "Thank you" : "Your order" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 16 }}>
        {q.isLoading && (
          <View style={{ alignItems: "center", paddingVertical: 48, gap: 12 }}>
            <ActivityIndicator color={colors.primary} />
            <Text style={{ color: colors.mutedForeground }}>Loading your order…</Text>
          </View>
        )}

        {q.error && (
          <View style={{ alignItems: "center", paddingVertical: 48, gap: 12 }}>
            <Feather name="alert-circle" size={36} color={colors.mutedForeground} />
            <Text style={{ color: colors.foreground, textAlign: "center" }}>
              We couldn&apos;t load this order.
            </Text>
            <Text style={{ color: colors.mutedForeground, textAlign: "center" }}>
              {(q.error as Error).message}
            </Text>
          </View>
        )}

        {order && (
          <>
            <View style={{ alignItems: "center", gap: 8, paddingVertical: 12 }}>
              <View
                style={{
                  width: 64,
                  height: 64,
                  borderRadius: 999,
                  alignItems: "center",
                  justifyContent: "center",
                  backgroundColor: paid ? colors.success + "26" : "rgba(61,107,255,0.15)",
                }}
              >
                <Feather
                  name={paid ? "check-circle" : "clock"}
                  size={34}
                  color={paid ? colors.success : colors.primary}
                />
              </View>
              <Text style={{ color: colors.foreground, fontSize: 22, fontWeight: "800" }}>
                {paid ? "Payment complete" : "Waiting for payment"}
              </Text>
              <Text style={{ color: colors.mutedForeground, textAlign: "center" }}>
                {paid
                  ? order.thank_you_message
                  : "Complete the checkout in your browser. This page updates automatically once your payment is confirmed."}
              </Text>
              {!paid && <ActivityIndicator color={colors.primary} style={{ marginTop: 4 }} />}
            </View>

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 10 }}>
                <Text style={{ color: colors.foreground, fontWeight: "800", fontSize: 16, flex: 1 }}>
                  Order #{order.id}
                </Text>
                <View
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 3,
                    borderRadius: 999,
                    backgroundColor: paid ? colors.success + "26" : "rgba(148,163,184,0.18)",
                  }}
                >
                  <Text style={{ fontSize: 11, fontWeight: "700", color: paid ? colors.success : colors.mutedForeground }}>
                    {order.status_label}
                  </Text>
                </View>
              </View>

              {order.items.map((it) => (
                <View
                  key={it.id}
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 12,
                    paddingVertical: 10,
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                  }}
                >
                  {it.image_url ? (
                    <Image source={{ uri: it.image_url }} style={{ width: 44, height: 44, borderRadius: 8 }} />
                  ) : (
                    <View
                      style={{
                        width: 44,
                        height: 44,
                        borderRadius: 8,
                        backgroundColor: colors.background,
                        alignItems: "center",
                        justifyContent: "center",
                      }}
                    >
                      <Feather name="box" size={18} color={colors.mutedForeground} />
                    </View>
                  )}
                  <View style={{ flex: 1 }}>
                    <Text style={{ color: colors.foreground, fontWeight: "600" }} numberOfLines={1}>
                      {it.name}
                    </Text>
                    <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                      {it.quantity} × {fmtMoney(it.unit_price_cents, it.currency)}
                      {it.product_type === "physical" ? " · Ships" : " · Digital"}
                    </Text>
                    {paid && it.download_url ? (
                      <Button
                        label="Download"
                        variant="outline"
                        style={{ marginTop: 8, alignSelf: "flex-start" }}
                        onPress={() => openDownload(it.download_url as string)}
                      />
                    ) : null}
                  </View>
                  <Text style={{ color: colors.foreground, fontWeight: "700" }}>
                    {fmtMoney(it.line_total_cents, it.currency)}
                  </Text>
                </View>
              ))}

              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  marginTop: 12,
                  paddingTop: 12,
                  borderTopWidth: StyleSheet.hairlineWidth,
                  borderTopColor: colors.border,
                }}
              >
                <Text style={{ color: colors.mutedForeground, flex: 1, fontWeight: "600" }}>Total</Text>
                <Text style={{ color: colors.foreground, fontWeight: "800", fontSize: 18 }}>
                  {fmtMoney(order.subtotal_cents, order.currency)}
                </Text>
              </View>
            </View>

            {paid && order.contains_physical ? (
              <Text style={{ color: colors.mutedForeground, textAlign: "center", fontSize: 13 }}>
                The creator will ship your physical item(s) and update the status when fulfilled.
              </Text>
            ) : null}

            <Button label="Done" onPress={() => router.back()} />
            {!paid && (
              <Button
                label="Check again"
                variant="outline"
                onPress={() => {
                  q.refetch().catch(() =>
                    Alert.alert("Couldn't refresh", "Please try again in a moment."),
                  );
                }}
              />
            )}
          </>
        )}
      </ScrollView>
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
