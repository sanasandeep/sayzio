import { LinearGradient } from "expo-linear-gradient";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { Button } from "@/components/Button";
import { MandatoryNameModal } from "@/components/MandatoryNameModal";
import { RegistrationPausedNotice } from "@/components/RegistrationPausedNotice";
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useCooldown } from "@/hooks/useCooldown";
import { redirectAfterAuth, touchPendingPostAuthNext } from "@/lib/authNext";
import type { ApiError } from "@/lib/api";
import { verifyBackupCode } from "@/lib/api/security";
import { maybeOfferBiometricEnrollment } from "@/lib/biometricsPrompt";

type Mode = "otp" | "backup";

export default function Verify() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const auth = useAuth();
  const { verifyOtp, sendOtp, applySession } = auth;

  const params = useLocalSearchParams<{
    channel?: string;
    identifier?: string;
    challenge_token?: string;
    demo_reveal?: string;
  }>();
  const channel = (params.channel === "mobile" ? "mobile" : "email") as
    | "email"
    | "mobile";
  const identifier = String(params.identifier ?? "");
  // When the prior auth step requires a 2FA challenge, it forwards a
  // short-lived `challenge_token` here. If we have it, the user can opt
  // into the "use a backup code" branch instead of waiting for the OTP.
  const challengeToken = String(params.challenge_token ?? "");

  const [mode, setMode] = useState<Mode>("otp");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState<null | "verify" | "resend">(null);
  const [error, setError] = useState<string | null>(null);
  // Set to the backend message when an account-creation attempt is rejected
  // because an admin has paused new sign-ups (`registration_paused`, HTTP
  // 403). Only new-account verify/resend paths return this — existing users
  // sign in normally.
  const [pausedMessage, setPausedMessage] = useState<string | null>(null);
  const [showNameModal, setShowNameModal] = useState(false);
  const [resentAt, setResentAt] = useState<number | null>(null);
  // Admin "Demo mode" toggle: when on, the backend returns the actual code so
  // it can be shown on screen (no real inbox/phone needed). Seeded from the
  // send screen and refreshed on every resend.
  const [demoReveal, setDemoReveal] = useState<string | null>(
    params.demo_reveal ? String(params.demo_reveal) : null,
  );
  const cooldown = useCooldown(30);

  // Reaching the OTP entry screen is an active step of the sign-up flow, so
  // slide any stashed post-auth pairing's freshness window forward. A guest
  // interrupted here (waiting on the email, a distraction) then completing
  // past the initial 10-minute window still lands where they intended.
  useEffect(() => {
    touchPendingPostAuthNext();
  }, []);

  const verify = async () => {
    if (code.trim().length < 4) {
      setError(
        mode === "backup"
          ? "Enter one of your backup codes"
          : "Enter the code we sent you",
      );
      return;
    }
    setBusy("verify");
    setError(null);
    try {
      if (mode === "backup") {
        // Trade the prior step's challenge_token + a single backup code
        // for a real session. If we don't have a challenge token, fall
        // back to the identifier so a backend that keys the challenge
        // by user can still resolve the account.
        const session = await verifyBackupCode({
          challenge_token: challengeToken || identifier,
          code: code.trim(),
        });
        await applySession(session.token, session.user as never);
      } else {
        const result = await verifyOtp({ channel, identifier, code: code.trim() });
        if (result.needsName) {
          setShowNameModal(true);
          return;
        }
      }
      await redirectAfterAuth(router);
      maybeOfferBiometricEnrollment(auth);
    } catch (e) {
      const err = e as ApiError;
      // New sign-ups paused by an admin — show the branded notice rather
      // than a generic "code did not match" error.
      if (err?.code === "registration_paused") {
        setPausedMessage(err?.message ?? "");
        return;
      }
      let msg = err?.message ?? "Code did not match";
      if (mode === "backup" && err?.status === 400) {
        msg = "That backup code isn't valid (or has already been used).";
      }
      if (mode === "backup" && err?.status === 410) {
        msg = "Sign-in expired. Start again from the login screen.";
      }
      setError(msg);
    } finally {
      setBusy(null);
    }
  };

  const resend = async () => {
    setBusy("resend");
    try {
      const { demoReveal: revealed } = await sendOtp({ channel, identifier });
      setDemoReveal(revealed);
      setResentAt(Date.now());
      cooldown.start();
    } catch (e) {
      const err = e as ApiError;
      if (err?.code === "registration_paused") {
        setPausedMessage(err?.message ?? "");
        return;
      }
      setError(err?.message ?? "Could not resend");
    } finally {
      setBusy(null);
    }
  };

  const webBottom = Platform.OS === "web" ? 34 : 0;

  if (pausedMessage !== null) {
    return (
      <RegistrationPausedNotice
        message={pausedMessage}
        onBack={() => router.replace("/(auth)")}
      />
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: "#0b0e1a" }}>
      <LinearGradient
        colors={["#0b0e1a", "#080b14", "#070a12"]}
        style={StyleSheet.absoluteFill}
      />
      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: insets.bottom + 32 + webBottom },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={[styles.h1, { color: colors.foreground }]}>
          {mode === "backup"
            ? "Use a backup code"
            : `Check your ${channel === "email" ? "inbox" : "messages"}`}
        </Text>
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          {mode === "backup"
            ? "Type one of the single-use backup codes you saved when you turned on two-factor authentication."
            : `We sent a code to ${identifier}. Enter it below to sign in.`}
        </Text>

        <View style={{ height: 24 }} />

        {mode === "otp" && demoReveal ? (
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
          label={mode === "backup" ? "Backup code" : "Verification code"}
          placeholder={mode === "backup" ? "abcd-efgh-ijkl" : "123456"}
          keyboardType={mode === "backup" ? "default" : "number-pad"}
          autoCapitalize={mode === "backup" ? "none" : "none"}
          autoCorrect={false}
          autoComplete={
            mode === "backup"
              ? "off"
              : Platform.select({ ios: "one-time-code", android: "sms-otp" })
          }
          textContentType={mode === "backup" ? "none" : "oneTimeCode"}
          maxLength={mode === "backup" ? 32 : 8}
          value={code}
          onChangeText={setCode}
          error={error ?? undefined}
        />

        <View style={{ height: 16 }} />
        <Button
          label="Verify and sign in"
          variant="cta"
          onPress={verify}
          loading={busy === "verify"}
          disabled={!!busy && busy !== "verify"}
        />

        {mode === "otp" ? (
          <>
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
              onPress={resend}
              loading={busy === "resend"}
              disabled={(!!busy && busy !== "resend") || cooldown.active}
            />
          </>
        ) : null}

        <View style={{ height: 8 }} />
        <Button
          label={
            mode === "backup"
              ? `Use the ${channel === "email" ? "email" : "WhatsApp"} code instead`
              : "Use a backup code instead"
          }
          variant="ghost"
          onPress={() => {
            setMode((m) => (m === "backup" ? "otp" : "backup"));
            setCode("");
            setError(null);
          }}
          disabled={!!busy}
        />
      </ScrollView>

      <MandatoryNameModal
        visible={showNameModal}
        onSaved={async () => {
          setShowNameModal(false);
          await redirectAfterAuth(router);
          maybeOfferBiometricEnrollment(auth);
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 24 },
  h1: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    letterSpacing: -0.4,
  },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
    marginTop: 6,
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
});
