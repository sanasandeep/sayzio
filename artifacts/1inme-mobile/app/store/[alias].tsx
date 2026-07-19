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

import { LinkTypePairings } from "@/components/LinkTypePairings";
import { useColors } from "@/hooks/useColors";
import {
  getStore,
  placeStoreOrder,
  type GuestOrder,
  type StoreProduct,
} from "@/lib/api/storeMenu";

export default function StoreScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ alias: string }>();
  const alias = String(params.alias ?? "");

  const [cart, setCart] = useState<Record<number, number>>({});
  const [name, setName] = useState("");
  const [contact, setContact] = useState("");
  const [note, setNote] = useState("");
  const [placed, setPlaced] = useState<GuestOrder | null>(null);

  const q = useQuery({
    queryKey: ["store", alias],
    queryFn: () => getStore(alias),
    enabled: alias.length > 0,
  });

  const place = useMutation({
    mutationFn: () =>
      placeStoreOrder(alias, {
        customer_name: name || null,
        customer_contact: contact || null,
        customer_note: note || null,
        items: Object.entries(cart)
          .filter(([, qty]) => qty > 0)
          .map(([id, qty]) => ({ product_id: Number(id), quantity: qty })),
      }),
    onSuccess: (order) => {
      setPlaced(order);
      setCart({});
      setNote("");
      setContact("");
    },
  });

  const menu = q.data;
  const orderMode = menu?.menu.order_enabled ?? false;
  const accepting = menu?.menu.accepting_orders ?? false;
  const currency = menu?.menu.currency ?? "USD";
  const accent = menu?.menu.accent_color || colors.primary;

  const productsById = useMemo(() => {
    const map: Record<number, StoreProduct> = {};
    menu?.categories.forEach((c) => c.products.forEach((p) => (map[p.id] = p)));
    return map;
  }, [menu]);

  const cartLines = Object.entries(cart).filter(([, qty]) => qty > 0);
  const cartTotal = cartLines.reduce(
    (sum, [id, qty]) => sum + Number(productsById[Number(id)]?.price ?? 0) * qty,
    0,
  );
  const cartCount = cartLines.reduce((n, [, qty]) => n + qty, 0);
  const canOrder = orderMode && accepting;
  const fmt = (n: number) => `${currency} ${n.toFixed(2)}`;

  function setQty(id: number, qty: number) {
    setCart((c) => ({ ...c, [id]: Math.max(0, qty) }));
  }

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Store" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !menu) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Store" }} />
        <Feather name="alert-circle" size={32} color={colors.mutedForeground} />
        <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
          This store could not be loaded.
        </Text>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: menu.link.title ?? "Store" }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 180 }}
        showsVerticalScrollIndicator={false}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>
          {menu.link.title}
        </Text>
        {orderMode && !accepting ? (
          <View style={[styles.pausePill, { borderColor: colors.border }]}>
            <Feather name="pause-circle" size={13} color={colors.mutedForeground} />
            <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
              Not accepting requests right now
            </Text>
          </View>
        ) : null}

        {menu.categories.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, marginTop: 24 }}>
            This store has no products yet.
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
            {cat.products.map((product) => (
              <View
                key={product.id}
                style={[
                  styles.item,
                  { borderColor: colors.border, backgroundColor: colors.card },
                  product.is_out_of_stock && { opacity: 0.55 },
                ]}
              >
                {product.photo_url ? (
                  <Image
                    source={{ uri: product.photo_url }}
                    style={styles.itemImg}
                  />
                ) : null}
                <View style={{ flex: 1 }}>
                  <Text style={[styles.itemName, { color: colors.foreground }]}>
                    {product.name}
                  </Text>
                  {product.description ? (
                    <Text
                      style={{ color: colors.mutedForeground, fontSize: 13 }}
                    >
                      {product.description}
                    </Text>
                  ) : null}
                  <Text style={[styles.itemPrice, { color: accent }]}>
                    {currency} {Number(product.price).toFixed(2)}
                    {product.is_out_of_stock ? "  · Out of stock" : ""}
                  </Text>
                </View>
                {canOrder && !product.is_out_of_stock ? (
                  <View style={styles.stepper}>
                    <Pressable
                      onPress={() =>
                        setQty(product.id, (cart[product.id] ?? 0) - 1)
                      }
                      style={[styles.stepBtn, { borderColor: colors.border }]}
                    >
                      <Feather name="minus" size={15} color={colors.foreground} />
                    </Pressable>
                    <Text
                      style={[styles.stepQty, { color: colors.foreground }]}
                    >
                      {cart[product.id] ?? 0}
                    </Text>
                    <Pressable
                      onPress={() =>
                        setQty(product.id, (cart[product.id] ?? 0) + 1)
                      }
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

        {canOrder && cartCount > 0 ? (
          <View style={{ marginTop: 26 }}>
            <Text style={[styles.catName, { color: colors.foreground }]}>
              Your request
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
              placeholder="Phone or email (optional)"
              placeholderTextColor={colors.mutedForeground}
              value={contact}
              onChangeText={setContact}
              autoCapitalize="none"
              autoCorrect={false}
              style={[
                styles.input,
                { borderColor: colors.border, color: colors.foreground },
              ]}
            />
            <TextInput
              placeholder="Note for the seller (optional)"
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

            <View
              style={[
                styles.billCard,
                { borderColor: colors.border, backgroundColor: colors.card },
              ]}
            >
              <View style={[styles.billRow, styles.billTotalRow]}>
                <Text style={[styles.billTotal, { color: colors.foreground }]}>
                  Estimated total
                </Text>
                <Text style={[styles.billTotal, { color: colors.foreground }]}>
                  {fmt(cartTotal)}
                </Text>
              </View>
              <Text style={[styles.disclaimer, { color: colors.mutedForeground }]}>
                This is an estimated total, not the actual bill. The seller
                confirms the final amount; no payment is collected here.
              </Text>
            </View>
          </View>
        ) : null}

        <LinkTypePairings pairings={menu?.pairings} theme="light" />
      </ScrollView>

      {canOrder && cartCount > 0 ? (
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
              {fmt(cartTotal)}
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
              <Text style={styles.placeBtnText}>Send request</Text>
            )}
          </Pressable>
        </View>
      ) : null}

      {placed ? (
        <View style={styles.modalBg}>
          <View style={[styles.modal, { backgroundColor: colors.card }]}>
            <Feather name="check-circle" size={42} color={accent} />
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Request sent!
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                textAlign: "center",
                marginTop: 6,
              }}
            >
              Estimated total {placed.currency}{" "}
              {Number(placed.total ?? placed.subtotal).toFixed(2)}. The seller
              will confirm your request.
            </Text>
            {placed.whatsapp?.url ? (
              <Pressable
                onPress={() => Linking.openURL(placed.whatsapp!.url)}
                style={[styles.placeBtn, styles.waBtn, { marginTop: 18 }]}
              >
                <Feather name="message-circle" size={16} color="#fff" />
                <Text style={styles.placeBtnText}>Send request via WhatsApp</Text>
              </Pressable>
            ) : null}
            <Text
              style={[
                styles.disclaimer,
                { color: colors.mutedForeground, textAlign: "center" },
              ]}
            >
              This is an estimated total, not the actual bill.
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
            {(place.error as Error)?.message ?? "Could not send request"}
          </Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  title: { fontSize: 24, fontWeight: "800" },
  pausePill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
    paddingHorizontal: 11,
    paddingVertical: 5,
    borderRadius: 999,
    borderWidth: 1,
    marginTop: 8,
  },
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
