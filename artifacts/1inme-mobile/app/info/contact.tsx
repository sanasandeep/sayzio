import { Feather, FontAwesome } from "@expo/vector-icons";
import { useMutation } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { Button } from "@/components/Button";
import {
  ScrollReveal,
  ScrollRevealCtx,
  useScrollRevealRegistry,
} from "@/components/ScrollReveal";
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useReducedMotion } from "@/hooks/useReducedMotion";
import type { ApiError } from "@/lib/api";
import {
  sendQuickContact,
  type QuickContactChannel,
} from "@/lib/api/assistant";
import {
  DEFAULT_CONTACT_CONTENT,
  fetchContactContent,
  type ContactContent,
} from "@/lib/api/siteContent";

// Social channels shown in the "Follow us" row, in the same order as the web
// /contact card. Each maps to a FontAwesome brand glyph.
const SOCIAL_LINKS: {
  key: keyof ContactContent["social"];
  icon: keyof typeof FontAwesome.glyphMap;
  label: string;
}[] = [
  { key: "twitter", icon: "twitter", label: "X (Twitter)" },
  { key: "instagram", icon: "instagram", label: "Instagram" },
  { key: "linkedin", icon: "linkedin", label: "LinkedIn" },
  { key: "youtube", icon: "youtube-play", label: "YouTube" },
  { key: "facebook", icon: "facebook", label: "Facebook" },
];

function asApiError(err: unknown): ApiError | null {
  if (err && typeof err === "object" && "status" in err && "message" in err) {
    return err as ApiError;
  }
  return null;
}

const CHANNELS: {
  value: QuickContactChannel;
  label: string;
  icon: keyof typeof Feather.glyphMap;
  blurb: string;
}[] = [
  {
    value: "callback",
    label: "Call back",
    icon: "phone-call",
    blurb: "We'll ring you back on an Indian mobile number.",
  },
  {
    value: "whatsapp",
    label: "WhatsApp call",
    icon: "message-circle",
    blurb: "Add your number with its country code (e.g. +1 555 123 4567).",
  },
  {
    value: "email",
    label: "Email",
    icon: "mail",
    blurb: "We'll reply by email — no phone needed.",
  },
];

// Mobile parity for the web's standalone multi-channel quick-contact widget.
// Lets a user request a call back / WhatsApp call / email reply; the request
// posts to the same /assistant/quick-contact contract and lands in the admin
// Contact Inbox. Reachable from the profile menu + Help & Support page.
export default function QuickContactScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const reduceMotion = useReducedMotion();
  const [registry, notifyScroll] = useScrollRevealRegistry();

  const [channel, setChannel] = useState<QuickContactChannel>("callback");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState(user?.email ?? "");
  const [name, setName] = useState(user?.display_name ?? "");
  const [message, setMessage] = useState("");
  // Honeypot decoy: a real user never sees or fills this (rendered off-screen,
  // not focusable, no autofill). The server silently drops any submission whose
  // `website` is non-empty.
  const [website, setWebsite] = useState("");
  const [sent, setSent] = useState<string | null>(null);
  // Time-trap: stamp when the screen mounted so the server can quarantine a
  // submission posted implausibly fast (a bot signal). A same-clock delta,
  // immune to clock skew.
  const openedAtRef = useRef<number>(Date.now());

  // Brand contact details (address, support email, phone, hours, social, map),
  // fetched at runtime from the same admin-editable source as the web /contact
  // card. Seeded with the correct brand defaults so the first paint — and any
  // offline / failed fetch — shows real details (EEFind, Banjara Hills,
  // hello@sayzio.app, no fake phone) rather than a blank card.
  const [details, setDetails] = useState<ContactContent>(
    DEFAULT_CONTACT_CONTENT,
  );
  useEffect(() => {
    let alive = true;
    fetchContactContent()
      .then((c) => {
        if (alive) setDetails(c);
      })
      .catch(() => {});
    return () => {
      alive = false;
    };
  }, []);

  const active = CHANNELS.find((c) => c.value === channel)!;

  const submit = useMutation({
    mutationFn: () =>
      sendQuickContact({
        channel,
        name,
        email,
        phone: channel === "email" ? null : phone,
        message,
        website,
        elapsedMs: Date.now() - openedAtRef.current,
      }),
    onSuccess: (res) => {
      setSent(res.message);
      setPhone("");
      setMessage("");
    },
  });

  const canSubmit =
    channel === "email" ? email.trim().length > 0 : phone.trim().length > 0;

  const apiError = submit.isError ? asApiError(submit.error) : null;
  const fieldError = apiError?.status === 422 ? apiError.message : null;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Contact us",
          headerStyle: { backgroundColor: colors.background },
          headerTitleStyle: {
            color: colors.foreground,
            fontFamily: "SpaceGrotesk_600SemiBold",
          },
          headerTintColor: colors.primary,
        }}
      />
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollRevealCtx.Provider value={registry}>
        <ScrollView
          scrollEventThrottle={16}
          onScroll={(e) => notifyScroll(e.nativeEvent.contentOffset.y)}
          contentContainerStyle={[
            styles.content,
            { paddingBottom: insets.bottom + 32 },
          ]}
          keyboardShouldPersistTaps="handled"
        >
          {sent ? (
            <View
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.success + "66",
                  alignItems: "center",
                  gap: 12,
                },
              ]}
            >
              <Feather name="check-circle" size={36} color={colors.success} />
              <Text style={[styles.successTitle, { color: colors.foreground }]}>
                Request sent
              </Text>
              <Text
                style={[styles.body, { color: colors.mutedForeground, textAlign: "center" }]}
              >
                {sent}
              </Text>
              <Button
                label="Send another request"
                variant="outline"
                onPress={() => setSent(null)}
              />
            </View>
          ) : (
            <>
              <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
                {() => (
                  <ContactDetailsCard details={details} colors={colors} />
                )}
              </ScrollReveal>

              <ScrollReveal
                delay={60}
                direction="up"
                reduceMotion={reduceMotion}
              >
                {() => (
                  <View style={{ gap: 16 }}>
                    <Text style={[styles.title, { color: colors.foreground }]}>
                      Request a callback
                    </Text>
                    <Text
                      style={[styles.intro, { color: colors.mutedForeground }]}
                    >
                      Tell us how you'd like to be reached and our team will get
                      back to you soon.
                    </Text>
                  </View>
                )}
              </ScrollReveal>

              <ScrollReveal
                delay={120}
                direction="up"
                reduceMotion={reduceMotion}
              >
                {() => (
                  <View style={{ gap: 16 }}>
                    <View style={styles.channelRow}>
                      {CHANNELS.map((c) => {
                        const selected = channel === c.value;
                        return (
                          <Pressable
                            key={c.value}
                            onPress={() => {
                              setChannel(c.value);
                              submit.reset();
                            }}
                            style={[
                              styles.channelPill,
                              {
                                backgroundColor: selected
                                  ? colors.primary + "1a"
                                  : colors.card,
                                borderColor: selected
                                  ? colors.primary + "88"
                                  : colors.border,
                              },
                            ]}
                          >
                            <Feather
                              name={c.icon}
                              size={18}
                              color={
                                selected ? colors.primary : colors.mutedForeground
                              }
                            />
                            <Text
                              style={{
                                color: selected
                                  ? colors.primary
                                  : colors.mutedForeground,
                                fontFamily: "SpaceGrotesk_600SemiBold",
                                fontSize: 12,
                                marginTop: 6,
                                textAlign: "center",
                              }}
                            >
                              {c.label}
                            </Text>
                          </Pressable>
                        );
                      })}
                    </View>

                    <Text
                      style={[styles.blurb, { color: colors.mutedForeground }]}
                    >
                      {active.blurb}
                    </Text>

                    <View style={{ gap: 14, marginTop: 4 }}>
                      <TextField
                        label="Your name (optional)"
                        placeholder="Jane Doe"
                        value={name}
                        onChangeText={setName}
                      />

                      {/* Honeypot decoy — off-screen, not focusable, no autofill.
                          Real users never reach it; scripted fillers tend to. */}
                      <TextInput
                        style={styles.honeypot}
                        value={website}
                        onChangeText={setWebsite}
                        autoComplete="off"
                        autoCorrect={false}
                        autoCapitalize="none"
                        textContentType="none"
                        importantForAutofill="no"
                        accessible={false}
                        accessibilityElementsHidden
                        importantForAccessibility="no-hide-descendants"
                        focusable={false}
                        pointerEvents="none"
                        aria-hidden
                      />

                      {channel === "email" ? (
                        <TextField
                          label="Email address"
                          placeholder="you@example.com"
                          autoCapitalize="none"
                          autoCorrect={false}
                          keyboardType="email-address"
                          value={email}
                          onChangeText={setEmail}
                          error={fieldError ?? undefined}
                        />
                      ) : (
                        <>
                          <TextField
                            label={
                              channel === "callback"
                                ? "Phone number"
                                : "WhatsApp number (with country code)"
                            }
                            placeholder={
                              channel === "callback"
                                ? "+91 98765 43210"
                                : "+1 555 123 4567"
                            }
                            keyboardType="phone-pad"
                            value={phone}
                            onChangeText={setPhone}
                            error={fieldError ?? undefined}
                          />
                          <TextField
                            label="Email address (optional)"
                            placeholder="you@example.com"
                            autoCapitalize="none"
                            autoCorrect={false}
                            keyboardType="email-address"
                            value={email}
                            onChangeText={setEmail}
                          />
                        </>
                      )}

                      <TextField
                        label="Message (optional)"
                        placeholder="How can we help?"
                        value={message}
                        onChangeText={setMessage}
                        multiline
                        numberOfLines={4}
                        style={{ minHeight: 100, paddingTop: 14 }}
                      />

                      {submit.isError && !fieldError ? (
                        <Text
                          style={[
                            styles.errorText,
                            { color: colors.destructive },
                          ]}
                        >
                          {apiError?.message ??
                            "Something went wrong. Please try again."}
                        </Text>
                      ) : null}

                      <Button
                        label="Send request"
                        onPress={() => submit.mutate()}
                        loading={submit.isPending}
                        disabled={!canSubmit}
                      />
                    </View>
                  </View>
                )}
              </ScrollReveal>
            </>
          )}
        </ScrollView>
        </ScrollRevealCtx.Provider>
      </KeyboardAvoidingView>
    </View>
  );
}

type Palette = ReturnType<typeof useColors>;

// The brand's real contact details — address, support email, phone, hours,
// social links and a map — mirroring the web /contact "Contact details" card.
// Every row is guarded so a blank field (e.g. phone) renders no empty row.
function ContactDetailsCard({
  details,
  colors,
}: {
  details: ContactContent;
  colors: Palette;
}) {
  const { address, email, phone, hours, social, map } = details;
  const socials = SOCIAL_LINKS.filter((s) => social[s.key] !== "");
  const mapUrl = `https://www.openstreetmap.org/?mlat=${map.lat}&mlon=${map.lng}#map=${map.zoom}/${map.lat}/${map.lng}`;
  const mapCaption = map.label || "Find us on OpenStreetMap";

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border, gap: 18 },
      ]}
    >
      <Text style={[styles.detailsHeading, { color: colors.foreground }]}>
        Contact details
      </Text>

      {address !== "" ? (
        <View style={styles.detailRow}>
          <Feather
            name="map-pin"
            size={16}
            color={colors.primary}
            style={styles.detailIcon}
          />
          <View style={{ flex: 1 }}>
            <Text style={[styles.detailLabel, { color: colors.mutedForeground }]}>
              Address
            </Text>
            <Text style={[styles.detailValue, { color: colors.foreground }]}>
              {address}
            </Text>
          </View>
        </View>
      ) : null}

      {email !== "" ? (
        <View style={styles.detailRow}>
          <Feather
            name="mail"
            size={16}
            color={colors.primary}
            style={styles.detailIcon}
          />
          <View style={{ flex: 1 }}>
            <Text style={[styles.detailLabel, { color: colors.mutedForeground }]}>
              Email
            </Text>
            <Pressable onPress={() => Linking.openURL(`mailto:${email}`)}>
              <Text style={[styles.detailLink, { color: colors.primary }]}>
                {email}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      {phone !== "" ? (
        <View style={styles.detailRow}>
          <Feather
            name="phone"
            size={16}
            color={colors.primary}
            style={styles.detailIcon}
          />
          <View style={{ flex: 1 }}>
            <Text style={[styles.detailLabel, { color: colors.mutedForeground }]}>
              Phone
            </Text>
            <Pressable
              onPress={() =>
                Linking.openURL(`tel:${phone.replace(/[^0-9+]/g, "")}`)
              }
            >
              <Text style={[styles.detailLink, { color: colors.primary }]}>
                {phone}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      {hours !== "" ? (
        <View style={styles.detailRow}>
          <Feather
            name="clock"
            size={16}
            color={colors.primary}
            style={styles.detailIcon}
          />
          <View style={{ flex: 1 }}>
            <Text style={[styles.detailLabel, { color: colors.mutedForeground }]}>
              Hours
            </Text>
            <Text style={[styles.detailValue, { color: colors.foreground }]}>
              {hours}
            </Text>
          </View>
        </View>
      ) : null}

      {socials.length > 0 ? (
        <View>
          <Text style={[styles.detailLabel, { color: colors.mutedForeground }]}>
            Follow us
          </Text>
          <View style={styles.socialRow}>
            {socials.map((s) => (
              <Pressable
                key={s.key}
                accessibilityLabel={s.label}
                onPress={() => Linking.openURL(social[s.key])}
                style={[
                  styles.socialPill,
                  { backgroundColor: colors.muted, borderColor: colors.border },
                ]}
              >
                <FontAwesome name={s.icon} size={18} color={colors.foreground} />
              </Pressable>
            ))}
          </View>
        </View>
      ) : null}

      <Pressable
        onPress={() => Linking.openURL(mapUrl)}
        style={[
          styles.mapCard,
          { backgroundColor: colors.muted, borderColor: colors.border },
        ]}
      >
        <View style={styles.mapPin}>
          <Feather name="map" size={22} color={colors.primary} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={[styles.detailValue, { color: colors.foreground }]}>
            {mapCaption}
          </Text>
          <Text style={[styles.detailLink, { color: colors.primary }]}>
            View on the map
          </Text>
        </View>
        <Feather name="external-link" size={16} color={colors.mutedForeground} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  content: { padding: 24, gap: 16 },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    letterSpacing: -0.5,
  },
  intro: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16, lineHeight: 24 },
  channelRow: { flexDirection: "row", gap: 10, marginTop: 8 },
  channelPill: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 14,
    paddingHorizontal: 6,
    borderRadius: 16,
    borderWidth: StyleSheet.hairlineWidth,
  },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 21 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16, lineHeight: 25 },
  successTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  errorText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  card: {
    padding: 20,
    borderRadius: 20,
    borderWidth: StyleSheet.hairlineWidth,
  },
  honeypot: {
    position: "absolute",
    left: -9999,
    top: -9999,
    width: 1,
    height: 1,
    opacity: 0,
  },
  detailsHeading: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  detailRow: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  detailIcon: { marginTop: 2 },
  detailLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.8,
    textTransform: "uppercase",
    marginBottom: 3,
  },
  detailValue: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
  },
  detailLink: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    lineHeight: 22,
  },
  socialRow: { flexDirection: "row", gap: 10, marginTop: 8 },
  socialPill: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
    alignItems: "center",
    justifyContent: "center",
  },
  mapCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    padding: 14,
    borderRadius: 16,
    borderWidth: StyleSheet.hairlineWidth,
  },
  mapPin: {
    width: 44,
    height: 44,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
});
