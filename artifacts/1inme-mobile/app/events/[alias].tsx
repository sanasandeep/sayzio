import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { buyEventTicket, type EventItem, type EventTier, getEvent } from "@/lib/api/events";
import { errorStatus } from "@/lib/api";
import { getProfile } from "@/lib/api/profile";

export default function EventDetailScreen() {
  const { alias } = useLocalSearchParams<{ alias: string }>();
  const colors = useColors();
  const router = useRouter();
  const [event, setEvent] = useState<EventItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [tier, setTier] = useState<EventTier | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!alias) return;
    getEvent(alias)
      .then((e) => {
        setEvent(e);
        setTier(e.tiers.find((t) => t.is_active && !t.is_sold_out) ?? e.tiers[0] ?? null);
      })
      .catch(() => Alert.alert("Not found", "This event could not be loaded."))
      .finally(() => setLoading(false));
    getProfile()
      .then((p) => {
        setName(p.name ?? "");
        setEmail(p.email ?? "");
      })
      .catch(() => {});
  }, [alias]);

  const buy = useCallback(async () => {
    if (!event || !tier) return;
    if (!name.trim() || !email.trim()) {
      Alert.alert("Missing info", "Please enter your name and email.");
      return;
    }
    setSubmitting(true);
    try {
      const res = await buyEventTicket(event.alias, {
        tier_id: tier.id,
        quantity: 1,
        name,
        email,
      });
      await Linking.openURL(res.checkout_url);
    } catch (err) {
      if (errorStatus(err) === 401) {
        Alert.alert("Sign in required", "Please sign in to buy a ticket.", [
          { text: "OK", onPress: () => router.push("/(auth)") },
        ]);
      } else {
        Alert.alert("Could not start checkout", (err as Error)?.message ?? "Try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }, [event, tier, name, email, router]);

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (!event) return null;

  return (
    <ScrollView style={{ backgroundColor: colors.background }} contentContainerStyle={styles.wrap}>
      <Text style={[styles.title, { color: colors.foreground }]}>{event.title}</Text>
      {event.start_date ? (
        <Text style={{ color: colors.mutedForeground, marginTop: 4 }}>
          {new Date(event.start_date).toLocaleString()}
        </Text>
      ) : null}
      {event.location ? (
        <Text style={{ color: colors.mutedForeground, marginTop: 2 }}>📍 {event.location}</Text>
      ) : null}
      {event.description ? (
        <Text style={{ color: colors.foreground, marginTop: 12, lineHeight: 20 }}>
          {event.description}
        </Text>
      ) : null}

      {event.ticketing_enabled && event.tiers.length > 0 ? (
        <View style={{ marginTop: 20, gap: 10 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Tickets</Text>
          {event.tiers.map((t) => (
            <Pressable
              key={t.id}
              disabled={t.is_sold_out || !t.is_on_sale}
              onPress={() => setTier(t)}
              style={[
                styles.tierRow,
                {
                  borderColor: tier?.id === t.id ? colors.primary : colors.border,
                  backgroundColor: colors.card,
                  opacity: t.is_sold_out || !t.is_on_sale ? 0.5 : 1,
                },
              ]}
            >
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.foreground, fontWeight: "600" }}>{t.name}</Text>
                {t.description ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {t.description}
                  </Text>
                ) : null}
                {t.is_sold_out ? (
                  <Text style={{ color: colors.destructive, fontSize: 12 }}>Sold out</Text>
                ) : t.remaining != null ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {t.remaining} left
                  </Text>
                ) : null}
              </View>
              <Text style={{ color: colors.primary, fontWeight: "700" }}>{t.price_label}</Text>
            </Pressable>
          ))}

          <TextInput
            value={name}
            onChangeText={setName}
            placeholder="Your name"
            placeholderTextColor={colors.mutedForeground}
            style={[styles.input, { borderColor: colors.border, color: colors.foreground }]}
          />
          <TextInput
            value={email}
            onChangeText={setEmail}
            placeholder="Your email"
            keyboardType="email-address"
            autoCapitalize="none"
            placeholderTextColor={colors.mutedForeground}
            style={[styles.input, { borderColor: colors.border, color: colors.foreground }]}
          />

          <Pressable
            onPress={buy}
            disabled={submitting || !tier}
            style={[styles.buyBtn, { backgroundColor: colors.primary, opacity: submitting ? 0.6 : 1 }]}
          >
            <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>
              {submitting ? "Starting checkout..." : tier?.is_free ? "Get free ticket" : "Buy ticket"}
            </Text>
          </Pressable>
        </View>
      ) : (
        <Text style={{ color: colors.mutedForeground, marginTop: 20 }}>
          This event doesn't sell tickets — open the page on the web to RSVP.
        </Text>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  wrap: { padding: 20, paddingBottom: 60 },
  title: { fontSize: 22, fontWeight: "800" },
  section: { fontSize: 16, fontWeight: "700" },
  tierRow: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1.5,
    borderRadius: 14,
    padding: 14,
    gap: 10,
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
