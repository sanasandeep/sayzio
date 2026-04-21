import { useLocalSearchParams, useRouter } from "expo-router";
import { useState } from "react";
import {
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
import type { ApiError } from "@/lib/api";

export default function Verify() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { verifyOtp, sendOtp } = useAuth();

  const params = useLocalSearchParams<{ channel?: string; identifier?: string }>();
  const channel = (params.channel === "mobile" ? "mobile" : "email") as
    | "email"
    | "mobile";
  const identifier = String(params.identifier ?? "");

  const [code, setCode] = useState("");
  const [busy, setBusy] = useState<null | "verify" | "resend">(null);
  const [error, setError] = useState<string | null>(null);
  const [resentAt, setResentAt] = useState<number | null>(null);

  const verify = async () => {
    if (code.trim().length < 4) {
      setError("Enter the code we sent you");
      return;
    }
    setBusy("verify");
    setError(null);
    try {
      await verifyOtp({ channel, identifier, code: code.trim() });
      router.replace("/(tabs)");
    } catch (e) {
      setError((e as ApiError)?.message ?? "Code did not match");
    } finally {
      setBusy(null);
    }
  };

  const resend = async () => {
    setBusy("resend");
    try {
      await sendOtp({ channel, identifier });
      setResentAt(Date.now());
    } catch (e) {
      setError((e as ApiError)?.message ?? "Could not resend");
    } finally {
      setBusy(null);
    }
  };

  const webBottom = Platform.OS === "web" ? 34 : 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: insets.bottom + 32 + webBottom },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={[styles.h1, { color: colors.foreground }]}>
          Check your {channel === "email" ? "inbox" : "messages"}
        </Text>
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          We sent a code to {identifier}. Enter it below to sign in.
        </Text>

        <View style={{ height: 24 }} />

        <TextField
          label="Verification code"
          placeholder="123456"
          keyboardType="number-pad"
          autoComplete={Platform.select({ ios: "one-time-code", android: "sms-otp" })}
          textContentType="oneTimeCode"
          maxLength={8}
          value={code}
          onChangeText={setCode}
          error={error ?? undefined}
        />

        <View style={{ height: 16 }} />
        <Button
          label="Verify and sign in"
          onPress={verify}
          loading={busy === "verify"}
          disabled={!!busy && busy !== "verify"}
        />

        <View style={{ height: 12 }} />
        <Button
          label={resentAt ? "Code sent again" : "Resend code"}
          variant="ghost"
          onPress={resend}
          loading={busy === "resend"}
          disabled={!!busy && busy !== "resend"}
        />
      </ScrollView>
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
});
