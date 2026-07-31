import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Image,
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
  cancelGuestBooking,
  getGuestRescheduleSlots,
  getServiceBookingPage,
  getServiceBookingSlots,
  placeServiceBooking,
  quoteServiceBooking,
  rescheduleGuestBooking,
  type GuestBooking,
  type ServiceBookingService,
} from "@/lib/api/service-booking";

export default function ServiceBookingScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ alias: string }>();
  const alias = String(params.alias ?? "");

  const [cart, setCart] = useState<Record<number, number>>({});
  const [slotStart, setSlotStart] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [note, setNote] = useState("");
  const [placed, setPlaced] = useState<GuestBooking | null>(null);
  const [staffId, setStaffId] = useState<number | null>(null);
  const [rescheduling, setRescheduling] = useState(false);

  const q = useQuery({
    queryKey: ["service-booking-page", alias],
    queryFn: () => getServiceBookingPage(alias),
    enabled: alias.length > 0,
  });

  const cartServices = useMemo(
    () =>
      Object.entries(cart)
        .filter(([, qty]) => qty > 0)
        .map(([id, qty]) => ({ service_id: Number(id), quantity: qty })),
    [cart],
  );

  const slotsQ = useQuery({
    queryKey: ["service-booking-slots", alias, cartServices, staffId],
    queryFn: () =>
      getServiceBookingSlots(alias, {
        services: cartServices,
        staff_id: staffId,
      }),
    enabled: alias.length > 0 && cartServices.length > 0,
  });

  const billQ = useQuery({
    queryKey: ["service-booking-quote", alias, cartServices],
    queryFn: () => quoteServiceBooking(alias, { services: cartServices }),
    enabled: alias.length > 0 && cartServices.length > 0,
  });

  const place = useMutation({
    mutationFn: () =>
      placeServiceBooking(alias, {
        customer_name: name,
        customer_email: email || null,
        customer_phone: phone || null,
        customer_note: note || null,
        slot_start: slotStart as string,
        services: cartServices,
        staff_id: staffId,
      }),
    onSuccess: (booking) => {
      setPlaced(booking);
      setCart({});
      setSlotStart(null);
      setNote("");
    },
  });

  const rescheduleSlotsQ = useQuery({
    queryKey: ["guest-reschedule-slots", placed?.public_token],
    queryFn: () => getGuestRescheduleSlots(placed?.public_token as string),
    enabled: rescheduling && !!placed?.public_token,
  });

  const cancelBooking = useMutation({
    mutationFn: () => cancelGuestBooking(placed?.public_token as string),
    onSuccess: (booking) => {
      setPlaced(booking);
      setRescheduling(false);
    },
  });

  const rescheduleBooking = useMutation({
    mutationFn: (start: string) =>
      rescheduleGuestBooking(placed?.public_token as string, start),
    onSuccess: (booking) => {
      setPlaced(booking);
      setRescheduling(false);
    },
  });

  const page = q.data;
  const bookingMode = page?.config.booking_enabled ?? false;
  const currency = page?.config.currency ?? "USD";
  const accent = page?.config.accent_color || colors.primary;
  const fmt = (n: number) => `${currency} ${n.toFixed(2)}`;

  const allServices = useMemo(() => {
    const list: ServiceBookingService[] = [];
    page?.categories.forEach((c) => list.push(...c.services));
    if (page?.uncategorized) list.push(...page.uncategorized);
    return list;
  }, [page]);

  const servicesById = useMemo(() => {
    const map: Record<number, ServiceBookingService> = {};
    allServices.forEach((s) => (map[s.id] = s));
    return map;
  }, [allServices]);

  const cartLines = Object.entries(cart).filter(([, qty]) => qty > 0);
  const cartCount = cartLines.reduce((n, [, qty]) => n + qty, 0);
  const bill = billQ.data?.bill;
  const days = slotsQ.data?.days ?? [];

  function setQty(id: number, qty: number) {
    setCart((c) => ({ ...c, [id]: Math.max(0, qty) }));
    // Picking different services changes the slot grid, so clear the choice.
    setSlotStart(null);
  }

  const canSubmit =
    bookingMode &&
    cartCount > 0 &&
    !!slotStart &&
    name.trim().length > 0 &&
    !place.isPending;

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Book" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !page) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Book" }} />
        <Feather name="alert-circle" size={32} color={colors.mutedForeground} />
        <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
          This booking page could not be loaded.
        </Text>
      </View>
    );
  }

  const renderService = (item: ServiceBookingService) => (
    <View
      key={item.id}
      style={[
        styles.item,
        { borderColor: colors.border, backgroundColor: colors.card },
        item.is_unavailable && { opacity: 0.55 },
      ]}
    >
      {item.photo_url ? (
        <Image source={{ uri: item.photo_url }} style={styles.itemImg} />
      ) : null}
      <View style={{ flex: 1 }}>
        <Text style={[styles.itemName, { color: colors.foreground }]}>
          {item.name}
        </Text>
        {item.description ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
            {item.description}
          </Text>
        ) : null}
        <Text style={[styles.itemMeta, { color: accent }]}>
          {currency} {Number(item.price).toFixed(2)} · {item.duration_minutes}{" "}
          min
          {item.is_unavailable ? "  · Unavailable" : ""}
        </Text>
      </View>
      {bookingMode && !item.is_unavailable ? (
        <View style={styles.stepper}>
          <Pressable
            onPress={() => setQty(item.id, (cart[item.id] ?? 0) - 1)}
            style={[styles.stepBtn, { borderColor: colors.border }]}
          >
            <Feather name="minus" size={15} color={colors.foreground} />
          </Pressable>
          <Text style={[styles.stepQty, { color: colors.foreground }]}>
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
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: page.link.title ?? "Book" }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 180 }}
        showsVerticalScrollIndicator={false}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>
          {page.link.title}
        </Text>
        {page.link.description ? (
          <Text style={{ color: colors.mutedForeground, marginTop: 6 }}>
            {page.link.description}
          </Text>
        ) : null}

        {!bookingMode ? (
          <View
            style={[
              styles.noticeCard,
              { borderColor: colors.border, backgroundColor: colors.card },
            ]}
          >
            <Text style={{ color: colors.mutedForeground }}>
              This is a service menu. Booking requests are not enabled right now.
            </Text>
          </View>
        ) : null}

        {allServices.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, marginTop: 24 }}>
            No services listed yet.
          </Text>
        ) : null}

        {page.categories.map((cat) => (
          <View key={cat.id} style={{ marginTop: 22 }}>
            <Text style={[styles.catName, { color: colors.foreground }]}>
              {cat.name}
            </Text>
            {cat.description ? (
              <Text style={{ color: colors.mutedForeground, marginBottom: 6 }}>
                {cat.description}
              </Text>
            ) : null}
            {cat.services.map(renderService)}
          </View>
        ))}
        {page.uncategorized.length > 0 ? (
          <View style={{ marginTop: 22 }}>
            {page.categories.length > 0 ? (
              <Text style={[styles.catName, { color: colors.foreground }]}>
                More services
              </Text>
            ) : null}
            {page.uncategorized.map(renderService)}
          </View>
        ) : null}

        {bookingMode && cartCount > 0 && (page.staff ?? []).length > 0 ? (
          <View style={{ marginTop: 26 }}>
            <Text style={[styles.catName, { color: colors.foreground }]}>
              Who would you like?
            </Text>
            <View style={styles.slotWrap}>
              <Pressable
                onPress={() => {
                  setStaffId(null);
                  setSlotStart(null);
                }}
                style={[
                  styles.slotChip,
                  { borderColor: colors.border },
                  staffId === null && {
                    backgroundColor: accent,
                    borderColor: accent,
                  },
                ]}
              >
                <Text
                  style={{
                    color: staffId === null ? "#fff" : colors.foreground,
                    fontWeight: "600",
                    fontSize: 13,
                  }}
                >
                  Any available
                </Text>
              </Pressable>
              {(page.staff ?? [])
                .filter(
                  (m) =>
                    m.service_ids.length === 0 ||
                    cartServices.every((cs) =>
                      m.service_ids.includes(cs.service_id),
                    ),
                )
                .map((m) => {
                  const active = staffId === m.id;
                  return (
                    <Pressable
                      key={m.id}
                      onPress={() => {
                        setStaffId(active ? null : m.id);
                        setSlotStart(null);
                      }}
                      style={[
                        styles.slotChip,
                        { borderColor: colors.border },
                        active && {
                          backgroundColor: accent,
                          borderColor: accent,
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: active ? "#fff" : colors.foreground,
                          fontWeight: "600",
                          fontSize: 13,
                        }}
                      >
                        {m.name}
                        {m.title ? ` · ${m.title}` : ""}
                      </Text>
                    </Pressable>
                  );
                })}
            </View>
          </View>
        ) : null}

        {bookingMode && cartCount > 0 ? (
          <View style={{ marginTop: 26 }}>
            <Text style={[styles.catName, { color: colors.foreground }]}>
              Pick a time
            </Text>
            {slotsQ.isLoading ? (
              <ActivityIndicator color={accent} style={{ marginVertical: 16 }} />
            ) : days.length === 0 ? (
              <Text style={{ color: colors.mutedForeground }}>
                No free slots in the booking window for this selection.
              </Text>
            ) : (
              days.map((day) => (
                <View key={day.date} style={{ marginTop: 12 }}>
                  <Text
                    style={{
                      color: colors.foreground,
                      fontWeight: "700",
                      marginBottom: 8,
                    }}
                  >
                    {day.label}
                  </Text>
                  <View style={styles.slotWrap}>
                    {day.slots.map((slot) => {
                      const active = slotStart === slot.start;
                      return (
                        <Pressable
                          key={slot.start}
                          onPress={() => setSlotStart(slot.start)}
                          style={[
                            styles.slotChip,
                            { borderColor: colors.border },
                            active && { backgroundColor: accent, borderColor: accent },
                          ]}
                        >
                          <Text
                            style={{
                              color: active ? "#fff" : colors.foreground,
                              fontWeight: "600",
                              fontSize: 13,
                            }}
                          >
                            {slot.label}
                            {typeof slot.remaining === "number" &&
                            slot.remaining > 1
                              ? ` · ${slot.remaining} spots`
                              : ""}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </View>
                </View>
              ))
            )}

            <Text
              style={[
                styles.catName,
                { color: colors.foreground, marginTop: 22 },
              ]}
            >
              Your details
            </Text>
            <TextInput
              placeholder="Your name"
              placeholderTextColor={colors.mutedForeground}
              value={name}
              onChangeText={setName}
              style={[
                styles.input,
                { borderColor: colors.border, color: colors.foreground },
              ]}
            />
            <TextInput
              placeholder="Email (optional)"
              placeholderTextColor={colors.mutedForeground}
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              keyboardType="email-address"
              style={[
                styles.input,
                { borderColor: colors.border, color: colors.foreground },
              ]}
            />
            <TextInput
              placeholder="Phone (optional)"
              placeholderTextColor={colors.mutedForeground}
              value={phone}
              onChangeText={setPhone}
              keyboardType="phone-pad"
              style={[
                styles.input,
                { borderColor: colors.border, color: colors.foreground },
              ]}
            />
            <TextInput
              placeholder="Anything we should know? (optional)"
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
                <Text
                  style={[styles.disclaimer, { color: colors.mutedForeground }]}
                >
                  This is an estimated price, not the final bill. No payment is
                  collected here; settle directly with the provider.
                </Text>
              </View>
            ) : null}
          </View>
        ) : null}
      </ScrollView>

      {bookingMode && cartCount > 0 ? (
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
          <View style={{ flex: 1 }}>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {cartCount} service(s) · est.
            </Text>
            <Text style={[styles.barTotal, { color: colors.foreground }]}>
              {fmt(bill ? bill.total : 0)}
            </Text>
          </View>
          <Pressable
            disabled={!canSubmit}
            onPress={() => place.mutate()}
            style={[
              styles.placeBtn,
              { backgroundColor: accent },
              !canSubmit && { opacity: 0.5 },
            ]}
          >
            {place.isPending ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.placeBtnText}>Request booking</Text>
            )}
          </Pressable>
        </View>
      ) : null}

      {placed ? (
        <View style={styles.modalBg}>
          <View style={[styles.modal, { backgroundColor: colors.card }]}>
            <Feather name="check-circle" size={42} color={accent} />
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Booking requested!
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                textAlign: "center",
                marginTop: 6,
              }}
            >
              {placed.status_label}. Estimated total {placed.currency}{" "}
              {Number(placed.total ?? placed.subtotal ?? 0).toFixed(2)}. The
              provider will confirm your request.
            </Text>
            {placed.staff ? (
              <Text
                style={{
                  color: colors.mutedForeground,
                  marginTop: 6,
                  textAlign: "center",
                }}
              >
                With {placed.staff.name}
              </Text>
            ) : null}
            <Text
              style={[
                styles.disclaimer,
                { color: colors.mutedForeground, textAlign: "center" },
              ]}
            >
              This is an estimated price, not the final bill. No payment was
              taken.
            </Text>
            {rescheduling ? (
              <View style={{ alignSelf: "stretch", marginTop: 12 }}>
                <Text
                  style={{
                    color: colors.foreground,
                    fontWeight: "700",
                    marginBottom: 8,
                  }}
                >
                  Pick a new time
                </Text>
                {rescheduleSlotsQ.isLoading ? (
                  <ActivityIndicator color={accent} />
                ) : (rescheduleSlotsQ.data?.days ?? []).length === 0 ? (
                  <Text style={{ color: colors.mutedForeground }}>
                    No alternative slots are free right now.
                  </Text>
                ) : (
                  <ScrollView style={{ maxHeight: 220 }}>
                    {(rescheduleSlotsQ.data?.days ?? []).map((day) => (
                      <View key={day.date} style={{ marginBottom: 10 }}>
                        <Text
                          style={{
                            color: colors.mutedForeground,
                            fontSize: 12,
                            marginBottom: 6,
                          }}
                        >
                          {day.label}
                        </Text>
                        <View style={styles.slotWrap}>
                          {day.slots.map((slot) => (
                            <Pressable
                              key={slot.start}
                              disabled={rescheduleBooking.isPending}
                              onPress={() => rescheduleBooking.mutate(slot.start)}
                              style={[
                                styles.slotChip,
                                { borderColor: colors.border },
                              ]}
                            >
                              <Text
                                style={{
                                  color: colors.foreground,
                                  fontWeight: "600",
                                  fontSize: 13,
                                }}
                              >
                                {slot.label}
                              </Text>
                            </Pressable>
                          ))}
                        </View>
                      </View>
                    ))}
                  </ScrollView>
                )}
                <Pressable
                  onPress={() => setRescheduling(false)}
                  style={[
                    styles.placeBtn,
                    { backgroundColor: colors.muted, marginTop: 10 },
                  ]}
                >
                  <Text
                    style={[styles.placeBtnText, { color: colors.foreground }]}
                  >
                    Keep current time
                  </Text>
                </Pressable>
              </View>
            ) : (
              <View style={{ flexDirection: "row", gap: 10, marginTop: 4 }}>
                {placed.can_reschedule ? (
                  <Pressable
                    onPress={() => setRescheduling(true)}
                    style={[
                      styles.placeBtn,
                      { backgroundColor: colors.muted, marginTop: 12 },
                    ]}
                  >
                    <Text
                      style={[
                        styles.placeBtnText,
                        { color: colors.foreground },
                      ]}
                    >
                      Reschedule
                    </Text>
                  </Pressable>
                ) : null}
                {placed.can_cancel ? (
                  <Pressable
                    disabled={cancelBooking.isPending}
                    onPress={() => cancelBooking.mutate()}
                    style={[
                      styles.placeBtn,
                      { backgroundColor: colors.destructive, marginTop: 12 },
                    ]}
                  >
                    {cancelBooking.isPending ? (
                      <ActivityIndicator color="#fff" />
                    ) : (
                      <Text style={styles.placeBtnText}>Cancel booking</Text>
                    )}
                  </Pressable>
                ) : null}
              </View>
            )}
            <Pressable
              onPress={() => setPlaced(null)}
              style={[
                styles.placeBtn,
                { backgroundColor: accent, marginTop: 12 },
              ]}
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
            {(place.error as Error)?.message ?? "Could not request booking"}
          </Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  title: { fontSize: 24, fontWeight: "800" },
  noticeCard: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginTop: 16,
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
  itemMeta: { fontSize: 13, fontWeight: "700", marginTop: 4 },
  stepper: { flexDirection: "row", alignItems: "center", gap: 8 },
  stepBtn: {
    width: 30,
    height: 30,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  stepQty: {
    fontSize: 15,
    fontWeight: "700",
    minWidth: 18,
    textAlign: "center",
  },
  slotWrap: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  slotChip: {
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 999,
    borderWidth: 1,
  },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    marginTop: 10,
  },
  billCard: { borderWidth: 1, borderRadius: 14, padding: 14, marginTop: 14 },
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
