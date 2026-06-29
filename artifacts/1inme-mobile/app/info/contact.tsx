import { Feather } from "@expo/vector-icons";
import { useMutation } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import type { ApiError } from "@/lib/api";
import {
  sendQuickContact,
  type QuickContactChannel,
} from "@/lib/api/assistant";

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

  const [channel, setChannel] = useState<QuickContactChannel>("callback");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState(user?.email ?? "");
  const [name, setName] = useState(user?.display_name ?? "");
  const [message, setMessage] = useState("");
  const [sent, setSent] = useState<string | null>(null);

  const active = CHANNELS.find((c) => c.value === channel)!;

  const submit = useMutation({
    mutationFn: () =>
      sendQuickContact({
        channel,
        name,
        email,
        phone: channel === "email" ? null : phone,
        message,
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
        <ScrollView
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
              <Text style={[styles.title, { color: colors.foreground }]}>
                Request a callback
              </Text>
              <Text style={[styles.intro, { color: colors.mutedForeground }]}>
                Tell us how you'd like to be reached and our team will get back
                to you soon.
              </Text>

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
                        color={selected ? colors.primary : colors.mutedForeground}
                      />
                      <Text
                        style={{
                          color: selected ? colors.primary : colors.mutedForeground,
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

              <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
                {active.blurb}
              </Text>

              <View style={{ gap: 14, marginTop: 4 }}>
                <TextField
                  label="Your name (optional)"
                  placeholder="Jane Doe"
                  value={name}
                  onChangeText={setName}
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
                  <Text style={[styles.errorText, { color: colors.destructive }]}>
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
            </>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
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
});
