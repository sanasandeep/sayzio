import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Platform,
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
import { useCooldown } from "@/hooks/useCooldown";
import type { ApiError } from "@/lib/api";
import {
  disconnectWhatsapp,
  getWhatsappStatus,
  sendWhatsappCode,
  verifyWhatsappCode,
} from "@/lib/api/whatsapp";

type Step = "manage" | "enter" | "verify" | "done";

export default function WhatsappVerify() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const { refresh } = useAuth();

  const status = useQuery({
    queryKey: ["whatsapp-status"],
    queryFn: getWhatsappStatus,
  });

  const [step, setStep] = useState<Step>("enter");
  const [mobile, setMobile] = useState("");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [removing, setRemoving] = useState(false);
  const [resending, setResending] = useState(false);
  const [resentAt, setResentAt] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Admin "Demo mode" toggle: backend returns the live code so it can be shown
  // on screen; null when the toggle is off. Refreshed on each (re)send.
  const [demoReveal, setDemoReveal] = useState<string | null>(null);
  const cooldown = useCooldown(30);

  // Land on the management view when a number is already connected; otherwise
  // start on the add flow. Only steers the *initial* step so an in-progress
  // add/verify isn't yanked back when the status query refetches.
  const [steered, setSteered] = useState(false);
  useEffect(() => {
    if (steered || !status.data) return;
    setStep(status.data.has_whatsapp_number ? "manage" : "enter");
    setSteered(true);
  }, [status.data, steered]);

  const webBottom = Platform.OS === "web" ? 34 : 0;
  const bottomPad = insets.bottom + 32 + webBottom;

  // The alert toggles read off these queries — invalidate so they reflect the
  // new connect/disconnect state the moment the user returns, no manual refresh.
  const invalidateDependents = () => {
    void qc.invalidateQueries({ queryKey: ["whatsapp-status"] });
    void qc.invalidateQueries({ queryKey: ["whatsapp-payment-alerts"] });
    void qc.invalidateQueries({
      predicate: (query) =>
        query.queryKey[0] === "form" &&
        query.queryKey.includes("whatsapp-alert"),
    });
  };

  const sendCode = async () => {
    const v = mobile.trim();
    if (!v) {
      setError("Enter your WhatsApp number with country code (e.g. +1 555 123 4567)");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await sendWhatsappCode(v);
      setDemoReveal(res.demo_reveal ?? null);
      setResentAt(null);
      cooldown.start();
      setStep("verify");
    } catch (e) {
      setError((e as ApiError)?.message ?? "Could not send a code. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  const resendCode = async () => {
    const v = mobile.trim();
    if (!v) return;
    setResending(true);
    setError(null);
    try {
      const res = await sendWhatsappCode(v);
      setDemoReveal(res.demo_reveal ?? null);
      setResentAt(Date.now());
      cooldown.start();
    } catch (e) {
      setError((e as ApiError)?.message ?? "Could not resend the code. Please try again.");
    } finally {
      setResending(false);
    }
  };

  const verifyCode = async () => {
    if (code.trim().length !== 6) {
      setError("Enter the 6-digit code we sent.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await verifyWhatsappCode(mobile.trim(), code.trim());
      invalidateDependents();
      void refresh();
      setStep("done");
    } catch (e) {
      setError((e as ApiError)?.message ?? "That code didn't match. Try again.");
    } finally {
      setBusy(false);
    }
  };

  const removeNumber = () => {
    Alert.alert(
      "Remove WhatsApp number?",
      "Your WhatsApp alerts will be turned off until you connect a new number.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Remove",
          style: "destructive",
          onPress: async () => {
            setRemoving(true);
            try {
              await disconnectWhatsapp();
              invalidateDependents();
              void refresh();
              // Drop straight into the add flow so a user who changed phones can
              // connect the new number right away.
              setMobile("");
              setCode("");
              setDemoReveal(null);
              setResentAt(null);
              cooldown.clear();
              setError(null);
              setStep("enter");
            } catch (e) {
              Alert.alert(
                "Couldn't remove",
                (e as ApiError)?.message ?? "Please try again.",
              );
            } finally {
              setRemoving(false);
            }
          },
        },
      ],
    );
  };

  const startAdd = () => {
    setMobile("");
    setCode("");
    setDemoReveal(null);
    setResentAt(null);
    cooldown.clear();
    setError(null);
    setStep("enter");
  };

  const loading = status.isLoading && !status.data;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "WhatsApp number" }} />
      {loading ? (
        <View style={styles.loading}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={[styles.scroll, { paddingBottom: bottomPad }]}
          keyboardShouldPersistTaps="handled"
        >
          {step === "manage" ? (
            <>
              <Header
                colors={colors}
                title="Your WhatsApp number"
                sub="This verified number receives your one-way WhatsApp alerts for new form submissions, subscribers, tips, unlocks and paid-form payments."
              />
              <View style={{ height: 20 }} />
              <View
                style={[
                  styles.numberCard,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={styles.numberRow}>
                  <Feather name="message-circle" size={18} color={colors.primary} />
                  <Text style={[styles.numberText, { color: colors.foreground }]}>
                    {status.data?.mobile_masked ?? "Connected"}
                  </Text>
                </View>
                <View style={styles.connectedBadge}>
                  <Feather name="check-circle" size={14} color={colors.primary} />
                  <Text style={[styles.connectedText, { color: colors.primary }]}>
                    Verified
                  </Text>
                </View>
              </View>
              <View style={{ height: 20 }} />
              <Button label="Change number" onPress={startAdd} disabled={removing} />
              <View style={{ height: 12 }} />
              <Button
                label="Remove number"
                variant="ghost"
                onPress={removeNumber}
                loading={removing}
                disabled={!status.data?.can_remove}
              />
              {!status.data?.can_remove && status.data?.remove_blocked_reason ? (
                <Text style={[styles.blockedNote, { color: colors.mutedForeground }]}>
                  {status.data.remove_blocked_reason}
                </Text>
              ) : null}
            </>
          ) : null}

          {step === "enter" ? (
            <>
              <Header
                colors={colors}
                title="Add your WhatsApp number"
                sub="Verify a WhatsApp number to turn on one-way alerts for new form submissions, subscribers, tips, unlocks and paid-form payments. We'll send a 6-digit code to confirm it's yours."
              />
              <View style={{ height: 20 }} />
              <TextField
                label="WhatsApp number"
                placeholder="+1 555 123 4567"
                keyboardType="phone-pad"
                autoCapitalize="none"
                autoCorrect={false}
                value={mobile}
                onChangeText={setMobile}
                error={error ?? undefined}
              />
              <View style={{ height: 16 }} />
              <Button label="Send code" onPress={sendCode} loading={busy} />
              {status.data?.has_whatsapp_number ? (
                <>
                  <View style={{ height: 12 }} />
                  <Button
                    label="Cancel"
                    variant="ghost"
                    onPress={() => {
                      setError(null);
                      setStep("manage");
                    }}
                    disabled={busy}
                  />
                </>
              ) : null}
            </>
          ) : null}

          {step === "verify" ? (
            <>
              <Header
                colors={colors}
                title="Enter the code"
                sub={`We sent a 6-digit code to ${mobile.trim()} on WhatsApp. Enter it to confirm your number.`}
              />
              <View style={{ height: 20 }} />
              {demoReveal ? (
                <View
                  style={[
                    styles.demoBanner,
                    {
                      backgroundColor: colors.primary + "14",
                      borderColor: colors.primary + "55",
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  <Text style={[styles.demoBannerText, { color: colors.foreground }]}>
                    {demoReveal}
                  </Text>
                </View>
              ) : null}
              <TextField
                label="Verification code"
                placeholder="123456"
                keyboardType="number-pad"
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete={Platform.select({ ios: "one-time-code", android: "sms-otp" })}
                textContentType="oneTimeCode"
                maxLength={6}
                value={code}
                onChangeText={setCode}
                error={error ?? undefined}
              />
              <View style={{ height: 16 }} />
              <Button label="Verify" onPress={verifyCode} loading={busy} disabled={resending} />
              <View style={{ height: 12 }} />
              <Button
                label={
                  cooldown.active
                    ? `Resend in ${cooldown.remaining}s`
                    : resentAt
                      ? "Code sent again"
                      : "Resend code"
                }
                variant="ghost"
                onPress={resendCode}
                loading={resending}
                disabled={busy || cooldown.active}
              />
              <View style={{ height: 8 }} />
              <Button
                label="Back"
                variant="ghost"
                onPress={() => {
                  setStep(status.data?.has_whatsapp_number ? "manage" : "enter");
                  setCode("");
                  setResentAt(null);
                  cooldown.clear();
                  setError(null);
                }}
                disabled={busy || resending}
              />
            </>
          ) : null}

          {step === "done" ? (
            <>
              <View style={styles.doneIcon}>
                <Feather name="check-circle" size={48} color={colors.primary} />
              </View>
              <Header
                colors={colors}
                title="WhatsApp number connected"
                sub="You can now turn on WhatsApp alerts for form submissions and payment events."
              />
              <View style={{ height: 20 }} />
              <Button label="Done" onPress={() => router.back()} />
            </>
          ) : null}
        </ScrollView>
      )}
    </View>
  );
}

function Header({
  colors,
  title,
  sub,
}: {
  colors: ReturnType<typeof useColors>;
  title: string;
  sub: string;
}) {
  return (
    <View style={{ gap: 6 }}>
      <Text style={[styles.h1, { color: colors.foreground }]}>{title}</Text>
      <Text style={[styles.sub, { color: colors.mutedForeground }]}>{sub}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 24 },
  loading: { flex: 1, alignItems: "center", justifyContent: "center" },
  h1: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 26, letterSpacing: -0.4 },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
  },
  numberCard: {
    borderWidth: 1,
    paddingVertical: 16,
    paddingHorizontal: 16,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  numberRow: { flexDirection: "row", alignItems: "center", gap: 10, flexShrink: 1 },
  numberText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 17,
    letterSpacing: 0.5,
  },
  connectedBadge: { flexDirection: "row", alignItems: "center", gap: 4 },
  connectedText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
  },
  blockedNote: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
    textAlign: "center",
    marginTop: 12,
  },
  demoBanner: {
    borderWidth: 1,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 16,
  },
  demoBannerText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    lineHeight: 20,
  },
  doneIcon: { alignItems: "center", marginBottom: 16 },
});
