import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  getRestaurantMenu,
  placeRestaurantOrder,
  quoteRestaurantOrder,
  type GuestOrder,
  type RestaurantBill,
  type RestaurantMenuItem,
} from "@/lib/api/restaurant";

export default function RestaurantMenuScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ alias: string; t?: string }>();
  const alias = String(params.alias ?? "");
  const tableCode = params.t ? String(params.t) : null;

  const [cart, setCart] = useState<Record<number, number>>({});
  const [name, setName] = useState("");
  const [note, setNote] = useState("");
  const [couponInput, setCouponInput] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
  const [placed, setPlaced] = useState<GuestOrder | null>(null);

  const q = useQuery({
    queryKey: ["restaurant-menu", alias, tableCode],
    queryFn: () => getRestaurantMenu(alias, tableCode),
    enabled: alias.length > 0,
  });

  const quoteItems = useMemo(
    () =>
      Object.entries(cart)
        .filter(([, qty]) => qty > 0)
        .map(([id, qty]) => ({ item_id: Number(id), quantity: qty })),
    [cart],
  );

  const billQ = useQuery({
    queryKey: ["restaurant-quote", alias, quoteItems, appliedCoupon],
    queryFn: () =>
      quoteRestaurantOrder(alias, {
        coupon_code: appliedCoupon,
        items: quoteItems,
      }),
    enabled: alias.length > 0 && quoteItems.length > 0,
  });

  const place = useMutation({
    mutationFn: () =>
      placeRestaurantOrder(alias, {
        table_code: tableCode,
        customer_name: name || null,
        customer_note: note || null,
        coupon_code: appliedCoupon,
        items: Object.entries(cart)
          .filter(([, qty]) => qty > 0)
          .map(([id, qty]) => ({ item_id: Number(id), quantity: qty })),
      }),
    onSuccess: (order) => {
      setPlaced(order);
      setCart({});
      setNote("");
      setCouponInput("");
      setAppliedCoupon(null);
    },
  });

  const menu = q.data;
  const orderMode = menu?.menu.order_enabled ?? false;
  const currency = menu?.menu.currency ?? "USD";
  const accent = menu?.menu.accent_color || colors.primary;

  const itemsById = useMemo(() => {
    const map: Record<number, RestaurantMenuItem> = {};
    menu?.categories.forEach((c) => c.items.forEach((i) => (map[i.id] = i)));
    return map;
  }, [menu]);

  const cartLines = Object.entries(cart).filter(([, qty]) => qty > 0);
  const cartTotal = cartLines.reduce(
    (sum, [id, qty]) => sum + Number(itemsById[Number(id)]?.price ?? 0) * qty,
    0,
  );
  const cartCount = cartLines.reduce((n, [, qty]) => n + qty, 0);
  const bill: RestaurantBill | undefined = billQ.data;
  const fmt = (n: number) => `${currency} ${n.toFixed(2)}`;

  function setQty(id: number, qty: number) {
    setCart((c) => ({ ...c, [id]: Math.max(0, qty) }));
  }

  function applyCoupon() {
    setAppliedCoupon(couponInput.trim() ? couponInput.trim() : null);
  }

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Menu" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !menu) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Menu" }} />
        <Feather name="alert-circle" size={32} color={colors.mutedForeground} />
        <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
          This menu could not be loaded.
        </Text>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: menu.link.title ?? "Menu" }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 180 }}
        showsVerticalScrollIndicator={false}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>
          {menu.link.title}
        </Text>
        {menu.table ? (
          <View style={[styles.tablePill, { backgroundColor: accent }]}>
            <Feather name="map-pin" size={13} color="#fff" />
            <Text style={styles.tablePillText}>Table {menu.table.label}</Text>
          </View>
        ) : null}

        {menu.categories.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, marginTop: 24 }}>
            This menu has no items yet.
          </Text>
        ) : null}

        {menu.categories.map((cat) => (
          <View key={cat.id} style={{ marginTop: 22 }}>
            <Text style={[styles.catName, { color: colors.foreground }]}>
              {cat.name}
            </Text>
            {cat.description ? (
              <Text style={{ color: colors.mutedForeground, marginBottom: 6 }}>
                {cat.description}
              </Text>
            ) : null}
            {cat.items.map((item) => (
              <View
                key={item.id}
                style={[
                  styles.item,
                  { borderColor: colors.border, backgroundColor: colors.card },
                  item.is_sold_out && { opacity: 0.55 },
                ]}
              >
                {item.photo_url ? (
                  <Image
                    source={{ uri: item.photo_url }}
                    style={styles.itemImg}
                  />
                ) : null}
                <View style={{ flex: 1 }}>
                  <Text style={[styles.itemName, { color: colors.foreground }]}>
                    {item.name}
                  </Text>
                  {item.description ? (
                    <Text
                      style={{ color: colors.mutedForeground, fontSize: 13 }}
                    >
                      {item.description}
                    </Text>
                  ) : null}
                  <Text style={[styles.itemPrice, { color: accent }]}>
                    {currency} {Number(item.price).toFixed(2)}
                    {item.is_sold_out ? "  · Sold out" : ""}
                  </Text>
                </View>
                {orderMode && !item.is_sold_out ? (
                  <View style={styles.stepper}>
                    <Pressable
                      onPress={() => setQty(item.id, (cart[item.id] ?? 0) - 1)}
                      style={[styles.stepBtn, { borderColor: colors.border }]}
                    >
                      <Feather name="minus" size={15} color={colors.foreground} />
                    </Pressable>
                    <Text
                      style={[styles.stepQty, { color: colors.foreground }]}
                    >
                      {cart[item.id] ?? 0}
                    </Text>
                    <Pressable
                      onPress={() => setQty(item.id, (cart[item.id] ?? 0) + 1)}
                      style={[styles.stepBtn, { borderColor: colors.border }]}
                    >
                      <Feather name="plus" size={15} color={colors.foreground} />
                    </Pressable>
                  </View>
                ) : null}
              </View>
            ))}
          </View>
        ))}

        {orderMode && cartCount > 0 ? (
          <View style={{ marginTop: 26 }}>
            <Text style={[styles.catName, { color: colors.foreground }]}>
              Your order
            </Text>
            <TextInput
              placeholder="Your name (optional)"
              placeholderTextColor={colors.mutedForeground}
              value={name}
              onChangeText={setName}
              style={[
                styles.input,
                { borderColor: colors.border, color: colors.foreground },
              ]}
            />
            <TextInput
              placeholder="Note for the kitchen (optional)"
              placeholderTextColor={colors.mutedForeground}
              value={note}
              onChangeText={setNote}
              multiline
              style={[
                styles.input,
                {
                  borderColor: colors.border,
                  color: colors.foreground,
                  height: 70,
                },
              ]}
            />

            <View style={styles.couponRow}>
              <TextInput
                placeholder="Coupon code"
                placeholderTextColor={colors.mutedForeground}
                value={couponInput}
                onChangeText={setCouponInput}
                autoCapitalize="characters"
                autoCorrect={false}
                style={[
                  styles.input,
                  {
                    flex: 1,
                    marginTop: 0,
                    borderColor: colors.border,
                    color: colors.foreground,
                  },
                ]}
              />
              <Pressable
                onPress={applyCoupon}
                style={[styles.couponBtn, { borderColor: accent }]}
              >
                <Text style={{ color: accent, fontWeight: "700" }}>Apply</Text>
              </Pressable>
            </View>
            {bill?.coupon_error ? (
              <Text style={[styles.couponMsg, { color: colors.destructive }]}>
                {bill.coupon_error}
              </Text>
            ) : bill?.coupon_applied ? (
              <Text style={[styles.couponMsg, { color: accent }]}>
                Coupon {bill.coupon_code} applied
              </Text>
            ) : null}

            {bill ? (
              <View
                style={[
                  styles.billCard,
                  { borderColor: colors.border, backgroundColor: colors.card },
                ]}
              >
                <View style={styles.billRow}>
                  <Text style={{ color: colors.mutedForeground }}>Subtotal</Text>
                  <Text style={{ color: colors.foreground }}>
                    {fmt(bill.subtotal)}
                  </Text>
                </View>
                {bill.discount_amount > 0 ? (
                  <View style={styles.billRow}>
                    <Text style={{ color: colors.mutedForeground }}>
                      Discount{bill.coupon_code ? ` (${bill.coupon_code})` : ""}
                    </Text>
                    <Text style={{ color: accent }}>
                      −{fmt(bill.discount_amount)}
                    </Text>
                  </View>
                ) : null}
                {bill.tax_enabled ? (
                  <View style={styles.billRow}>
                    <Text style={{ color: colors.mutedForeground }}>
                      {bill.tax_label}
                      {bill.tax_inclusive ? " (incl.)" : ""}
                    </Text>
                    <Text style={{ color: colors.foreground }}>
                      {bill.tax_inclusive ? "incl." : fmt(bill.tax_amount)}
                    </Text>
                  </View>
                ) : null}
                <View style={[styles.billRow, styles.billTotalRow]}>
                  <Text style={[styles.billTotal, { color: colors.foreground }]}>
                    Estimated total
                  </Text>
                  <Text style={[styles.billTotal, { color: colors.foreground }]}>
                    {fmt(bill.total)}
                  </Text>
                </View>
                <Text style={[styles.disclaimer, { color: colors.mutedForeground }]}>
                  This is an estimated bill, not the actual bill. Final amount is
                  confirmed by the restaurant.
                </Text>
              </View>
            ) : null}
          </View>
        ) : null}
      </ScrollView>

      {orderMode && cartCount > 0 ? (
        <View
          style={[
            styles.bar,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              paddingBottom: insets.bottom + 12,
            },
          ]}
        >
          <View>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {cartCount} item(s) · est.
            </Text>
            <Text style={[styles.barTotal, { color: colors.foreground }]}>
              {fmt(bill ? bill.total : cartTotal)}
            </Text>
          </View>
          <Pressable
            disabled={place.isPending}
            onPress={() => place.mutate()}
            style={[styles.placeBtn, { backgroundColor: accent }]}
          >
            {place.isPending ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.placeBtnText}>Send order</Text>
            )}
          </Pressable>
        </View>
      ) : null}

      {placed ? (
        <View style={styles.modalBg}>
          <View style={[styles.modal, { backgroundColor: colors.card }]}>
            <Feather name="check-circle" size={42} color={accent} />
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Order sent!
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                textAlign: "center",
                marginTop: 6,
              }}
            >
              {placed.table_label ? `Table ${placed.table_label} · ` : ""}
              Estimated total {placed.currency}{" "}
              {Number(placed.total ?? placed.subtotal).toFixed(2)}. Pay your
              server directly.
            </Text>
            {placed.whatsapp?.url ? (
              <Pressable
                onPress={() => Linking.openURL(placed.whatsapp!.url)}
                style={[styles.placeBtn, styles.waBtn, { marginTop: 18 }]}
              >
                <Feather name="message-circle" size={16} color="#fff" />
                <Text style={styles.placeBtnText}>Send order via WhatsApp</Text>
              </Pressable>
            ) : null}
            <Text
              style={[
                styles.disclaimer,
                { color: colors.mutedForeground, textAlign: "center" },
              ]}
            >
              This is an estimated bill, not the actual bill.
            </Text>
            <Pressable
              onPress={() => setPlaced(null)}
              style={[styles.placeBtn, { backgroundColor: accent, marginTop: 12 }]}
            >
              <Text style={styles.placeBtnText}>Done</Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      {place.isError ? (
        <View
          style={[
            styles.errToast,
            { backgroundColor: colors.destructive, bottom: insets.bottom + 90 },
          ]}
        >
          <Text style={{ color: "#fff" }}>
            {(place.error as Error)?.message ?? "Could not place order"}
          </Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  title: { fontSize: 24, fontWeight: "800" },
  tablePill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
    paddingHorizontal: 11,
    paddingVertical: 5,
    borderRadius: 999,
    marginTop: 8,
  },
  tablePillText: { color: "#fff", fontWeight: "700", fontSize: 13 },
  catName: { fontSize: 18, fontWeight: "700", marginBottom: 8 },
  item: {
    flexDirection: "row",
    gap: 12,
    borderWidth: 1,
    borderRadius: 14,
    padding: 12,
    marginBottom: 10,
    alignItems: "center",
  },
  itemImg: { width: 56, height: 56, borderRadius: 10 },
  itemName: { fontSize: 15, fontWeight: "600" },
  itemPrice: { fontSize: 13, fontWeight: "700", marginTop: 4 },
  stepper: { flexDirection: "row", alignItems: "center", gap: 8 },
  stepBtn: {
    width: 30,
    height: 30,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  stepQty: { fontSize: 15, fontWeight: "700", minWidth: 18, textAlign: "center" },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    marginTop: 10,
  },
  couponRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginTop: 10,
  },
  couponBtn: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 11,
    alignItems: "center",
    justifyContent: "center",
  },
  couponMsg: { fontSize: 12, fontWeight: "600", marginTop: 6 },
  billCard: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginTop: 14,
  },
  billRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 6,
  },
  billTotalRow: {
    marginTop: 4,
    marginBottom: 0,
    paddingTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(127,127,127,0.4)",
  },
  billTotal: { fontSize: 16, fontWeight: "800" },
  disclaimer: { fontSize: 11, marginTop: 10, lineHeight: 15 },
  bar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 18,
    paddingTop: 12,
    borderTopWidth: 1,
  },
  barTotal: { fontSize: 18, fontWeight: "800" },
  placeBtn: {
    paddingHorizontal: 22,
    paddingVertical: 13,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  placeBtnText: { color: "#fff", fontWeight: "700", fontSize: 15 },
  waBtn: {
    backgroundColor: "#25D366",
    flexDirection: "row",
    gap: 8,
    alignSelf: "stretch",
  },
  modalBg: {
    position: "absolute",
    inset: 0,
    backgroundColor: "rgba(0,0,0,0.5)",
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  modal: {
    width: "100%",
    maxWidth: 360,
    borderRadius: 20,
    padding: 28,
    alignItems: "center",
  },
  modalTitle: { fontSize: 20, fontWeight: "800", marginTop: 12 },
  errToast: {
    position: "absolute",
    left: 16,
    right: 16,
    padding: 14,
    borderRadius: 12,
  },
});
