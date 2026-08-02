import { Feather } from "@expo/vector-icons";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  createTier,
  deleteTier,
  type EventTicket,
  type EventTier,
  getOwnerTickets,
  getOwnerTiers,
  type OwnerTicketingTotals,
  refundEventTicket,
  updateTier,
} from "@/lib/api/events";
import { showAlert } from "@/lib/webAlert";

function money(cents: number): string {
  return (cents / 100).toFixed(2);
}

export default function OwnerTiersScreen() {
  const { linkId } = useLocalSearchParams<{ linkId: string }>();
  const colors = useColors();
  const id = Number(linkId);
  const [tiers, setTiers] = useState<EventTier[]>([]);
  const [tickets, setTickets] = useState<EventTicket[]>([]);
  const [totals, setTotals] = useState<OwnerTicketingTotals | null>(null);
  const [loading, setLoading] = useState(true);
  const [refunding, setRefunding] = useState<number | null>(null);
  const [name, setName] = useState("");
  const [price, setPrice] = useState("");
  const [capacity, setCapacity] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    const [res, tix] = await Promise.all([
      getOwnerTiers(id),
      getOwnerTickets(id).catch(() => null),
    ]);
    setTiers(res.tiers);
    setTotals(res.totals);
    if (tix) setTickets(tix.items);
  }, [id]);

  const refundTicket = useCallback(
    (ticket: EventTicket) => {
      showAlert(
        "Refund ticket",
        `Refund ${ticket.attendee_name ?? "this attendee"}'s ticket? This frees the seat and notifies them by email. This can't be undone.`,
        [
          { text: "Cancel", style: "cancel" },
          {
            text: "Refund",
            style: "destructive",
            onPress: async () => {
              setRefunding(ticket.id);
              try {
                await refundEventTicket(id, ticket.id);
                await load();
              } catch (err) {
                showAlert(
                  "Could not refund",
                  (err as Error)?.message ?? "Try again.",
                );
              } finally {
                setRefunding(null);
              }
            },
          },
        ],
      );
    },
    [id, load],
  );

  useEffect(() => {
    if (!id) return;
    load().finally(() => setLoading(false));
  }, [id, load]);

  const addTier = useCallback(async () => {
    if (!name.trim() || !price.trim()) {
      showAlert("Missing info", "Enter a name and price.");
      return;
    }
    setSaving(true);
    try {
      await createTier(id, {
        name: name.trim(),
        price: Number(price) || 0,
        capacity: capacity.trim() ? Number(capacity) : null,
      });
      setName("");
      setPrice("");
      setCapacity("");
      await load();
    } catch (err) {
      showAlert("Could not add tier", (err as Error)?.message ?? "Try again.");
    } finally {
      setSaving(false);
    }
  }, [id, name, price, capacity, load]);

  const toggleActive = useCallback(
    async (tier: EventTier) => {
      try {
        await updateTier(id, tier.id, {
          name: tier.name,
          price: tier.price_cents / 100,
          capacity: tier.capacity,
          is_active: !tier.is_active,
        });
        await load();
      } catch (err) {
        showAlert("Could not update tier", (err as Error)?.message ?? "Try again.");
      }
    },
    [id, load],
  );

  const removeTier = useCallback(
    (tier: EventTier) => {
      showAlert("Delete tier", `Delete "${tier.name}"?`, [
        { text: "Cancel", style: "cancel" },
        {
          text: "Delete",
          style: "destructive",
          onPress: async () => {
            try {
              await deleteTier(id, tier.id);
              await load();
            } catch (err) {
              showAlert("Could not delete", (err as Error)?.message ?? "Try again.");
            }
          },
        },
      ]);
    },
    [id, load],
  );

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <ScrollView style={{ backgroundColor: colors.background }} contentContainerStyle={styles.wrap}>
      <Stack.Screen
        options={{
          headerRight: () => (
            <Pressable
              onPress={() => router.push(`/events/edit/${id}`)}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Edit event details"
              style={{ flexDirection: "row", alignItems: "center", gap: 4 }}
            >
              <Feather name="edit-2" size={16} color={colors.primary} />
              <Text style={{ color: colors.primary, fontWeight: "600" }}>
                Edit details
              </Text>
            </Pressable>
          ),
        }}
      />
      {totals ? (
        <View style={[styles.statsRow]}>
          <View style={[styles.stat, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>Gross</Text>
            <Text style={{ color: colors.foreground, fontWeight: "700" }}>
              ${money(totals.gross_cents)}
            </Text>
          </View>
          <View style={[styles.stat, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>Sold</Text>
            <Text style={{ color: colors.foreground, fontWeight: "700" }}>{totals.sold}</Text>
          </View>
          <View style={[styles.stat, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>Checked in</Text>
            <Text style={{ color: colors.foreground, fontWeight: "700" }}>{totals.checked_in}</Text>
          </View>
        </View>
      ) : null}

      <Pressable
        onPress={() => router.push(`/events/checkin/${id}`)}
        style={[styles.checkinBtn, { backgroundColor: colors.primary }]}
      >
        <Feather name="camera" size={16} color={colors.primaryForeground} />
        <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>Open door scanner</Text>
      </Pressable>

      <Pressable
        onPress={() => router.push(`/events/broadcast/${id}`)}
        style={[styles.checkinBtn, { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border }]}
      >
        <Feather name="send" size={16} color={colors.foreground} />
        <Text style={{ color: colors.foreground, fontWeight: "700" }}>Message guests</Text>
      </Pressable>

      <Text style={[styles.section, { color: colors.foreground }]}>Ticket tiers</Text>
      {tiers.map((t) => (
        <View key={t.id} style={[styles.tierCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <View style={{ flex: 1 }}>
            <Text style={{ color: colors.foreground, fontWeight: "600" }}>{t.name}</Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {t.price_label} · sold {t.sold_count}
              {t.capacity != null ? ` / ${t.capacity}` : ""}
            </Text>
          </View>
          <Switch value={t.is_active} onValueChange={() => toggleActive(t)} />
          <Pressable onPress={() => removeTier(t)} style={{ marginLeft: 10 }}>
            <Feather name="trash-2" size={18} color={colors.destructive} />
          </Pressable>
        </View>
      ))}

      {tickets.length > 0 ? (
        <>
          <Text style={[styles.section, { color: colors.foreground, marginTop: 20 }]}>
            Recent tickets
          </Text>
          {tickets.map((t) => {
            const refundable = t.status === "valid" || t.status === "checked_in";
            return (
              <View
                key={t.id}
                style={[styles.tierCard, { backgroundColor: colors.card, borderColor: colors.border }]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                    {t.attendee_name ?? "Guest"}
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {t.tier?.name ?? "—"} · qty {t.quantity} · {t.status}
                  </Text>
                </View>
                {refundable ? (
                  <Pressable
                    onPress={() => refundTicket(t)}
                    disabled={refunding === t.id}
                    style={{ opacity: refunding === t.id ? 0.5 : 1 }}
                  >
                    <Text style={{ color: colors.destructive, fontWeight: "700", fontSize: 13 }}>
                      {refunding === t.id ? "…" : "Refund"}
                    </Text>
                  </Pressable>
                ) : (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>—</Text>
                )}
              </View>
            );
          })}
        </>
      ) : null}

      <Text style={[styles.section, { color: colors.foreground, marginTop: 20 }]}>Add a tier</Text>
      <TextInput
        value={name}
        onChangeText={setName}
        placeholder="Tier name (e.g. General admission)"
        placeholderTextColor={colors.mutedForeground}
        style={[styles.input, { borderColor: colors.border, color: colors.foreground }]}
      />
      <TextInput
        value={price}
        onChangeText={setPrice}
        placeholder="Price (0 for free)"
        keyboardType="decimal-pad"
        placeholderTextColor={colors.mutedForeground}
        style={[styles.input, { borderColor: colors.border, color: colors.foreground }]}
      />
      <TextInput
        value={capacity}
        onChangeText={setCapacity}
        placeholder="Capacity (optional)"
        keyboardType="number-pad"
        placeholderTextColor={colors.mutedForeground}
        style={[styles.input, { borderColor: colors.border, color: colors.foreground }]}
      />
      <Pressable
        onPress={addTier}
        disabled={saving}
        style={[styles.buyBtn, { backgroundColor: colors.primary, opacity: saving ? 0.6 : 1 }]}
      >
        <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>
          {saving ? "Adding..." : "Add tier"}
        </Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  wrap: { padding: 20, paddingBottom: 60, gap: 10 },
  statsRow: { flexDirection: "row", gap: 8 },
  stat: { flex: 1, borderWidth: 1, borderRadius: 12, padding: 10, alignItems: "center" },
  checkinBtn: {
    flexDirection: "row",
    gap: 8,
    height: 48,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 4,
  },
  section: { fontSize: 16, fontWeight: "700", marginTop: 10 },
  tierCard: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 14,
    padding: 12,
  },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 14,
    height: 46,
    fontSize: 15,
  },
  buyBtn: {
    height: 50,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 4,
  },
});
