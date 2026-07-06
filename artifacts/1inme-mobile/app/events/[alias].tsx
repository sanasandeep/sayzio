import { Feather } from "@expo/vector-icons";
import { Image } from "expo-image";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
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

import { EmbedModal } from "@/components/EmbedModal";
import { EventConnectionTips } from "@/components/EventConnectionTips";
import { HostAvatarPlaceholder } from "@/components/HostAvatarPlaceholder";
import { LinkTypePairings } from "@/components/LinkTypePairings";
import { MapPreview } from "@/components/MapPreview";
import { useColors } from "@/hooks/useColors";
import {
  buyEventTicket,
  type EventInterestStatus,
  type EventItem,
  type EventTier,
  getEvent,
  setEventInterest,
} from "@/lib/api/events";
import { errorStatus, getBaseUrl } from "@/lib/api";
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
  const [myInterest, setMyInterest] = useState<EventInterestStatus | null>(null);
  const [interestBusy, setInterestBusy] = useState(false);
  const [rsvpModalUrl, setRsvpModalUrl] = useState<string | null>(null);

  // Task #3674: free events have no RSVP JSON API (session/CSRF form only),
  // so mobile embeds the existing public RSVP page in a WebView rather than
  // duplicating its business rules (capacity, waitlist, custom questions).
  const rsvpUrl = useMemo(() => {
    if (!event) return null;
    const webBase = getBaseUrl().replace(/\/?api\/?$/, "").replace(/\/+$/, "");
    return `${webBase}/${event.alias}/rsvp`;
  }, [event]);

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
      {event.cover_image_url ? (
        <Image source={{ uri: event.cover_image_url }} style={styles.cover} contentFit="cover" />
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

      {/* Task #3736: map thumbnail when the event has geocoded coordinates.
          Tapping opens the location in the device's Maps app. */}
      {event.latitude != null && event.longitude != null ? (
        <Pressable
          onPress={() => {
            const q =
              event.location != null && event.location !== ""
                ? encodeURIComponent(event.location)
                : `${event.latitude},${event.longitude}`;
            Linking.openURL(`https://www.google.com/maps/search/?api=1&query=${q}`);
          }}
          style={styles.mapWrap}
        >
          <MapPreview lat={event.latitude} lng={event.longitude} height={150} />
        </Pressable>
      ) : null}

      {event.organizer ? (
        <View style={[styles.organizerCard, { borderColor: colors.border }]}>
          <Pressable
            disabled={!event.organizer.handle}
            onPress={() => {
              if (event.organizer?.handle) {
                router.push(`/profile/${event.organizer.handle}` as never);
              }
            }}
            style={styles.organizerHead}
          >
            {event.organizer.avatar ? (
              <Image source={{ uri: event.organizer.avatar }} style={styles.organizerAvatar} contentFit="cover" />
            ) : (
              <View style={[styles.organizerAvatar, styles.organizerAvatarFallback]}>
                <HostAvatarPlaceholder size={36} />
              </View>
            )}
            <View style={{ flex: 1 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Hosted by</Text>
              <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 14 }}>
                {event.organizer.name ?? "Organizer"}
              </Text>
            </View>
            {event.organizer.handle ? (
              <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
            ) : null}
          </Pressable>

          {/* Task #3736: rich host details from the reusable organizer
              profile. `filled` is the single source of truth for whether the
              extra rows render — mirrors the web event-host-card partial. */}
          {event.organizer.filled ? (
            <View style={styles.organizerDetails}>
              {event.organizer.description ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}>
                  {event.organizer.description}
                </Text>
              ) : null}
              {event.organizer.website ? (
                <Pressable
                  onPress={() => Linking.openURL(event.organizer!.website!)}
                  style={styles.organizerDetailRow}
                >
                  <Feather name="globe" size={13} color={colors.primary} />
                  <Text style={{ color: colors.primary, fontSize: 13 }} numberOfLines={1}>
                    {event.organizer.website}
                  </Text>
                </Pressable>
              ) : null}
              {event.organizer.contact_email ? (
                <Pressable
                  onPress={() => Linking.openURL(`mailto:${event.organizer!.contact_email!}`)}
                  style={styles.organizerDetailRow}
                >
                  <Feather name="mail" size={13} color={colors.primary} />
                  <Text style={{ color: colors.primary, fontSize: 13 }} numberOfLines={1}>
                    {event.organizer.contact_name ?? event.organizer.contact_email}
                  </Text>
                </Pressable>
              ) : null}
              {event.organizer.contact_phone ? (
                <Pressable
                  onPress={() => Linking.openURL(`tel:${event.organizer!.contact_phone!}`)}
                  style={styles.organizerDetailRow}
                >
                  <Feather name="phone" size={13} color={colors.primary} />
                  <Text style={{ color: colors.primary, fontSize: 13 }}>
                    {event.organizer.contact_phone}
                  </Text>
                </Pressable>
              ) : null}
              {event.organizer.address ? (
                <View style={styles.organizerDetailRow}>
                  <Feather name="map-pin" size={13} color={colors.mutedForeground} />
                  <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                    {event.organizer.address}
                  </Text>
                </View>
              ) : null}
              {Object.keys(event.organizer.socials).length > 0 ? (
                <View style={styles.socialRow}>
                  {Object.entries(event.organizer.socials).map(([platform, value]) => (
                    <Pressable
                      key={platform}
                      onPress={() => {
                        const isEmail = platform === "email";
                        const isUrl = value.startsWith("http://") || value.startsWith("https://");
                        const href = isEmail
                          ? `mailto:${value}`
                          : isUrl
                            ? value
                            : `https://${value.replace(/^@/, "")}`;
                        Linking.openURL(href);
                      }}
                      style={[styles.socialChip, { borderColor: colors.border }]}
                    >
                      <Text style={{ color: colors.foreground, fontSize: 12 }}>
                        {platform.charAt(0).toUpperCase() + platform.slice(1)}
                      </Text>
                    </Pressable>
                  ))}
                </View>
              ) : null}
            </View>
          ) : null}
        </View>
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

      {event.same_host_events.length > 0 ? (
        <View style={{ marginTop: 20 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>More from this host</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
            {event.same_host_events.map((rec) => (
              <Pressable
                key={rec.alias}
                onPress={() => router.push(`/events/${rec.alias}` as never)}
                style={[styles.hostEventCard, { borderColor: colors.border, backgroundColor: colors.card }]}
              >
                <Text numberOfLines={2} style={{ color: colors.foreground, fontWeight: "600", fontSize: 13 }}>
                  {rec.title}
                </Text>
                {rec.start_date ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 6 }}>
                    {new Date(rec.start_date).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
                  </Text>
                ) : null}
              </Pressable>
            ))}
          </ScrollView>
        </View>
      ) : null}

      {event.gallery.length > 0 ? (
        <View style={{ marginTop: 20 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Gallery</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
            {event.gallery.map((url) => (
              <Image key={url} source={{ uri: url }} style={styles.galleryImg} contentFit="cover" />
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
      ) : event.rsvp_available && rsvpUrl ? (
        <View style={{ marginTop: 20 }}>
          <Pressable
            onPress={() => setRsvpModalUrl(rsvpUrl)}
            style={[styles.buyBtn, { backgroundColor: colors.primary }]}
          >
            <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>RSVP now</Text>
          </Pressable>
        </View>
      ) : (
        <Text style={{ color: colors.mutedForeground, marginTop: 20 }}>
          RSVPs are closed for this event.
        </Text>
      )}

      <EmbedModal
        visible={!!rsvpModalUrl}
        url={rsvpModalUrl}
        title="RSVP"
        onClose={() => setRsvpModalUrl(null)}
      />
      <LinkTypePairings pairings={event?.pairings} theme="dark" />
      <EventConnectionTips tips={event?.connection_tips} compact />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  wrap: { padding: 20, paddingBottom: 60 },
  cover: { width: "100%", height: 180, borderRadius: 14, marginBottom: 14 },
  title: { fontSize: 22, fontWeight: "800" },
  section: { fontSize: 16, fontWeight: "700" },
  mapWrap: { marginTop: 12, borderRadius: 12, overflow: "hidden" },
  organizerCard: {
    marginTop: 12,
    padding: 10,
    borderWidth: 1,
    borderRadius: 12,
  },
  organizerHead: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  organizerDetails: { marginTop: 10, gap: 6 },
  organizerDetailRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  socialRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 2 },
  socialChip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  organizerAvatar: { width: 36, height: 36, borderRadius: 18 },
  organizerAvatarFallback: { alignItems: "center", justifyContent: "center", overflow: "hidden" },
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
  hostEventCard: {
    width: 160,
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginRight: 10,
  },
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
