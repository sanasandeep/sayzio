import { Feather } from "@expo/vector-icons";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { RsvpWebViewModal } from "@/components/RsvpWebViewModal";
import { useColors } from "@/hooks/useColors";
import { errorStatus } from "@/lib/api";
import {
  buyEventTicket,
  type EventInterestStatus,
  type EventItem,
  type EventTier,
  getEvent,
  listEvents,
  setEventInterest,
} from "@/lib/api/events";
import { useAuth } from "@/contexts/AuthContext";

export default function EventDetailScreen() {
  const { alias } = useLocalSearchParams<{ alias: string }>();
  const colors = useColors();
  const router = useRouter();
  const { user } = useAuth();
  const [event, setEvent] = useState<EventItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [tier, setTier] = useState<EventTier | null>(null);
  const [name, setName] = useState(user?.display_name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [submitting, setSubmitting] = useState(false);
  const [myInterest, setMyInterest] = useState<EventInterestStatus | null>(null);
  const [interestBusy, setInterestBusy] = useState(false);
  const [rsvpOpen, setRsvpOpen] = useState(false);
  const [similarEvents, setSimilarEvents] = useState<EventItem[]>([]);

  useEffect(() => {
    if (!alias) return;
    getEvent(alias)
      .then((e) => {
        setEvent(e);
        setTier(e.tiers.find((t) => t.is_active && !t.is_sold_out) ?? e.tiers[0] ?? null);
        return e;
      })
      .then((e) => {
        // "Similar events" — matched by shared hashtag via the existing
        // directory tag filter, since there's no dedicated similar-events
        // endpoint. Mirrors the web page's hashtag-based fallback.
        const firstTag = e.hashtags[0];
        if (!firstTag) return;
        listEvents({ tag: firstTag })
          .then((res) => setSimilarEvents(res.items.filter((it) => it.alias !== e.alias).slice(0, 4)))
          .catch(() => setSimilarEvents([]));
      })
      .catch(() => Alert.alert("Not found", "This event could not be loaded."))
      .finally(() => setLoading(false));
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

  const toggleInterest = useCallback(
    async (status: EventInterestStatus) => {
      if (!event) return;
      const next = myInterest === status ? null : status;
      setInterestBusy(true);
      try {
        const res = await setEventInterest(event.alias, status);
        setMyInterest(next);
        setEvent((prev) =>
          prev
            ? {
                ...prev,
                interested_count: res.counts.interested,
                not_interested_count: res.counts.not_interested,
              }
            : prev,
        );
      } catch (err) {
        if (errorStatus(err) === 401) {
          Alert.alert("Sign in required", "Please sign in to mark your interest.", [
            { text: "OK", onPress: () => router.push("/(auth)") },
          ]);
        } else {
          Alert.alert("Could not update", (err as Error)?.message ?? "Try again.");
        }
      } finally {
        setInterestBusy(false);
      }
    },
    [event, myInterest, router],
  );

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
      {event.cover_image_url ? (
        <Image source={{ uri: event.cover_image_url }} style={styles.cover} />
      ) : null}

      <Text style={[styles.title, { color: colors.foreground }]}>{event.title}</Text>
      {event.start_date ? (
        <Text style={{ color: colors.mutedForeground, marginTop: 4 }}>
          {new Date(event.start_date).toLocaleString()}
        </Text>
      ) : null}
      {event.location ? (
        <Text style={{ color: colors.mutedForeground, marginTop: 2 }}>📍 {event.location}</Text>
      ) : null}

      {event.hashtags.length > 0 ? (
        <View style={styles.tagRow}>
          {event.hashtags.map((tag) => (
            <View key={tag} style={[styles.tagChip, { borderColor: colors.border }]}>
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>#{tag}</Text>
            </View>
          ))}
        </View>
      ) : null}

      <View style={styles.interestRow}>
        <Pressable
          disabled={interestBusy}
          onPress={() => toggleInterest("interested")}
          style={[
            styles.interestBtn,
            {
              borderColor: myInterest === "interested" ? colors.primary : colors.border,
              backgroundColor: myInterest === "interested" ? colors.primary : "transparent",
            },
          ]}
        >
          <Feather
            name="star"
            size={14}
            color={myInterest === "interested" ? colors.primaryForeground : colors.foreground}
          />
          <Text
            style={{
              color: myInterest === "interested" ? colors.primaryForeground : colors.foreground,
              fontWeight: "600",
              fontSize: 13,
            }}
          >
            Interested{event.interested_count > 0 ? ` (${event.interested_count})` : ""}
          </Text>
        </Pressable>
        <Pressable
          disabled={interestBusy}
          onPress={() => toggleInterest("not_interested")}
          style={[
            styles.interestBtn,
            {
              borderColor: myInterest === "not_interested" ? colors.destructive : colors.border,
              backgroundColor: myInterest === "not_interested" ? colors.destructive : "transparent",
            },
          ]}
        >
          <Feather
            name="x-circle"
            size={14}
            color={myInterest === "not_interested" ? "#fff" : colors.foreground}
          />
          <Text
            style={{
              color: myInterest === "not_interested" ? "#fff" : colors.foreground,
              fontWeight: "600",
              fontSize: 13,
            }}
          >
            Not interested
          </Text>
        </Pressable>
      </View>

      {event.description ? (
        <Text style={{ color: colors.foreground, marginTop: 12, lineHeight: 20 }}>
          {event.description}
        </Text>
      ) : null}

      {event.info_sections.length > 0 ? (
        <View style={{ marginTop: 20, gap: 14 }}>
          {event.info_sections.map((section, i) => (
            <View key={i}>
              {section.heading ? (
                <Text style={[styles.section, { color: colors.foreground }]}>
                  {section.heading}
                </Text>
              ) : null}
              {section.body ? (
                <Text style={{ color: colors.mutedForeground, marginTop: 4, lineHeight: 19 }}>
                  {section.body}
                </Text>
              ) : null}
            </View>
          ))}
        </View>
      ) : null}

      {event.gallery.length > 0 ? (
        <View style={{ marginTop: 20 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Gallery</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
            {event.gallery.map((url) => (
              <Image key={url} source={{ uri: url }} style={styles.galleryImg} />
            ))}
          </ScrollView>
        </View>
      ) : null}

      {similarEvents.length > 0 ? (
        <View style={{ marginTop: 20 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Similar events</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
            {similarEvents.map((e) => (
              <Pressable
                key={e.id}
                onPress={() => router.push(`/events/${e.alias}`)}
                style={[styles.similarCard, { borderColor: colors.border, backgroundColor: colors.card }]}
              >
                {e.cover_image_url ? (
                  <Image source={{ uri: e.cover_image_url }} style={styles.similarCover} />
                ) : null}
                <Text
                  numberOfLines={2}
                  style={{ color: colors.foreground, fontWeight: "600", fontSize: 13 }}
                >
                  {e.title}
                </Text>
                {e.start_date ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                    {new Date(e.start_date).toLocaleDateString()}
                  </Text>
                ) : null}
              </Pressable>
            ))}
          </ScrollView>
        </View>
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
        <View style={{ marginTop: 20 }}>
          <Pressable
            onPress={() => setRsvpOpen(true)}
            style={[styles.buyBtn, { backgroundColor: colors.primary }]}
          >
            <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>
              RSVP
            </Text>
          </Pressable>
        </View>
      )}

      <RsvpWebViewModal
        visible={rsvpOpen}
        alias={event.alias}
        onClose={() => setRsvpOpen(false)}
        onSubmitted={() => {
          setRsvpOpen(false);
          Alert.alert("You're on the list!", "Your RSVP has been recorded.");
        }}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  wrap: { padding: 20, paddingBottom: 60 },
  cover: { width: "100%", height: 180, borderRadius: 14, marginBottom: 14 },
  title: { fontSize: 22, fontWeight: "800" },
  section: { fontSize: 16, fontWeight: "700" },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 10 },
  tagChip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  interestRow: { flexDirection: "row", gap: 10, marginTop: 14 },
  interestBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1.5,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  galleryImg: { width: 220, height: 140, borderRadius: 12, marginRight: 10 },
  similarCard: {
    width: 160,
    borderWidth: 1,
    borderRadius: 12,
    padding: 10,
    marginRight: 10,
  },
  similarCover: { width: "100%", height: 80, borderRadius: 8, marginBottom: 6 },
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
