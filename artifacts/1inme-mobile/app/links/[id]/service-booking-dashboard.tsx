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
  BOOKING_ACTION_LABELS,
  BOOKING_STATUS_FLOW,
  getOwnerBookings,
  OPEN_BOOKING_STATUSES,
  pollOwnerBookings,
  updateOwnerBookingStatus,
  type OwnerBooking,
} from "@/lib/api/service-booking";

const statusColors = (
  colors: ReturnType<typeof useColors>,
): Record<string, string> => ({
  pending: colors.warning,
  confirmed: "#3b82f6",
  completed: colors.success,
  cancelled: "#9ca3af",
  declined: colors.destructive,
});

function formatSlot(iso: string | null): string {
  if (!iso) return "Time TBD";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

export default function ServiceBookingDashboardScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ id: string }>();
  const linkId = String(params.id ?? "");
  const qc = useQueryClient();

  const [filter, setFilter] = useState<"open" | "all">("open");
  const [bookings, setBookings] = useState<OwnerBooking[]>([]);
  const [openCount, setOpenCount] = useState(0);
  const cursor = useRef<string | null>(null);

  const initial = useQuery({
    queryKey: ["service-booking-owner-bookings", linkId],
    queryFn: () => getOwnerBookings(linkId),
    enabled: linkId.length > 0,
  });

  const merge = useCallback((incoming: OwnerBooking[]) => {
    setBookings((prev) => {
      const map = new Map(prev.map((b) => [b.id, b]));
      incoming.forEach((b) => map.set(b.id, b));
      return Array.from(map.values()).sort((a, b) => b.id - a.id);
    });
  }, []);

  useEffect(() => {
    if (initial.data) {
      merge(initial.data.bookings);
      setOpenCount(initial.data.open_count);
      cursor.current = initial.data.server_time;
    }
  }, [initial.data, merge]);

  const pollNow = useCallback(async () => {
    if (linkId.length === 0) return;
    try {
      const res = await pollOwnerBookings(linkId, cursor.current);
      cursor.current = res.server_time;
      setOpenCount(res.open_count);
      if (res.bookings.length) merge(res.bookings);
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
    mutationFn: ({
      bookingId,
      status,
    }: {
      bookingId: number;
      status: string;
    }) => updateOwnerBookingStatus(linkId, bookingId, status),
    onSuccess: (booking) => {
      merge([booking]);
      qc.invalidateQueries({
        queryKey: ["service-booking-owner-bookings", linkId],
      });
    },
  });

  const visible =
    filter === "open"
      ? bookings.filter((b) => OPEN_BOOKING_STATUSES.includes(b.status))
      : bookings;

  if (initial.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Bookings" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Bookings" }} />
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
            <Feather name="calendar" size={32} color={colors.mutedForeground} />
            <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
              No booking requests yet. New requests appear here automatically.
            </Text>
          </View>
        ) : null}

        {visible.map((b) => (
          <View
            key={b.id}
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.cardHead}>
              <View style={{ flex: 1, paddingRight: 8 }}>
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {b.customer_name || "Guest"}
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {formatSlot(b.slot_start)} · #{b.id}
                  {b.staff_name ? ` · ${b.staff_name}` : ""}
                </Text>
              </View>
              <View
                style={[
                  styles.statusPill,
                  {
                    backgroundColor:
                      statusColors(colors)[b.status] ?? "#6b7280",
                  },
                ]}
              >
                <Text style={styles.statusText}>{b.status_label}</Text>
              </View>
            </View>

            {b.customer_email || b.customer_phone ? (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12.5,
                  marginTop: 4,
                }}
              >
                {[b.customer_phone, b.customer_email]
                  .filter(Boolean)
                  .join(" · ")}
              </Text>
            ) : null}

            <View style={{ marginTop: 10 }}>
              {b.items.map((it) => (
                <View key={it.id} style={styles.line}>
                  <Text style={{ color: colors.foreground, fontSize: 13.5 }}>
                    {it.quantity}× {it.name}
                  </Text>
                  <Text style={{ color: colors.foreground, fontSize: 13.5 }}>
                    {b.currency} {Number(it.line_total).toFixed(2)}
                  </Text>
                </View>
              ))}
            </View>

            {b.customer_note ? (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontStyle: "italic",
                  marginTop: 6,
                  fontSize: 12.5,
                }}
              >
                “{b.customer_note}”
              </Text>
            ) : null}

            <View style={[styles.total, { borderTopColor: colors.border }]}>
              <View style={styles.breakdownRow}>
                <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                  Subtotal
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
                  {b.currency} {Number(b.subtotal ?? 0).toFixed(2)}
                </Text>
              </View>
              {Number(b.tax_rate ?? 0) > 0 ? (
                <View style={styles.breakdownRow}>
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 12.5 }}
                  >
                    Tax ({Number(b.tax_rate).toFixed(2)}%)
                    {b.tax_inclusive ? " incl." : ""}
                  </Text>
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 12.5 }}
                  >
                    {b.tax_inclusive
                      ? "incl."
                      : `${b.currency} ${Number(b.tax_amount ?? 0).toFixed(2)}`}
                  </Text>
                </View>
              ) : null}
              <View style={[styles.breakdownRow, { marginTop: 4 }]}>
                <Text style={{ color: colors.foreground, fontWeight: "700" }}>
                  Estimated total
                </Text>
                <Text style={{ color: colors.foreground, fontWeight: "700" }}>
                  {b.currency} {Number(b.total ?? b.subtotal ?? 0).toFixed(2)}
                </Text>
              </View>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 11,
                  marginTop: 6,
                }}
              >
                Estimated price, not the final bill. No payment is collected.
              </Text>
            </View>

            <View style={styles.actions}>
              {(BOOKING_STATUS_FLOW[b.status] ?? []).map((s) => (
                <Pressable
                  key={s}
                  disabled={setStatus.isPending}
                  onPress={() =>
                    setStatus.mutate({ bookingId: b.id, status: s })
                  }
                  style={[
                    styles.actionBtn,
                    s === "cancelled" || s === "declined"
                      ? { borderColor: colors.destructive }
                      : { backgroundColor: colors.primary },
                  ]}
                >
                  <Text
                    style={{
                      color:
                        s === "cancelled" || s === "declined"
                          ? colors.destructive
                          : "#fff",
                      fontWeight: "600",
                      fontSize: 12.5,
                    }}
                  >
                    {BOOKING_ACTION_LABELS[s] ?? s}
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
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 50,
  },
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
  title: { fontSize: 16, fontWeight: "700" },
  statusPill: { paddingHorizontal: 11, paddingVertical: 4, borderRadius: 999 },
  statusText: { color: "#fff", fontWeight: "700", fontSize: 12 },
  line: {
    flexDirection: "row",
    justifyContent: "space-between",
    paddingVertical: 3,
  },
  total: { marginTop: 8, paddingTop: 8, borderTopWidth: 1 },
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
