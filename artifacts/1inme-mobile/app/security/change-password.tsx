import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import {
  changePassword,
  sendSetPasswordCode,
  setFirstPassword,
} from "@/lib/api/security";
import { showAlert } from "@/lib/webAlert";

/**
 * Change (or set for the first time) the account password.
 *
 * Accounts that signed up with a one-time code or a social provider never
 * chose a password — the backend reports that via `password_set_at` on the
 * profile. Those accounts verify a 6-digit code (sent to their email or
 * WhatsApp) instead of confirming a current password.
 */
export default function ChangePasswordScreen() {
  const colors = useColors();
  const router = useRouter();
  const { user, refresh } = useAuth();

  const hasChosenPassword = !!user?.password_set_at;

  const [currentPassword, setCurrentPassword] = useState("");
  const [code, setCode] = useState("");
  const [codeSentTo, setCodeSentTo] = useState<string | null>(null);
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState<"send" | "save" | null>(null);
  const [error, setError] = useState<string | null>(null);

  const onSendCode = async () => {
    setError(null);
    setBusy("send");
    try {
      const res = await sendSetPasswordCode();
      setCodeSentTo(res.channel === "email" ? "email" : "WhatsApp");
    } catch (e) {
      setError((e as { message?: string }).message ?? "Couldn't send the code");
    } finally {
      setBusy(null);
    }
  };

  const onSave = async () => {
    setError(null);
    if (password.length < 8) {
      setError("Password must be at least 8 characters.");
      return;
    }
    if (password !== confirm) {
      setError("Passwords don't match.");
      return;
    }
    setBusy("save");
    try {
      if (hasChosenPassword) {
        await changePassword({
          current_password: currentPassword,
          password,
          password_confirmation: confirm,
        });
      } else {
        await setFirstPassword({
          code,
          password,
          password_confirmation: confirm,
        });
      }
      await refresh();
      showAlert(
        "Password saved",
        "Every other device has been signed out for safety.",
      );
      router.back();
    } catch (e) {
      setError(
        (e as { message?: string }).message ?? "Couldn't save the password",
      );
    } finally {
      setBusy(null);
    }
  };

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={styles.content}
    >
      <Stack.Screen options={{ title: "Password" }} />

      <Text style={[styles.lede, { color: colors.mutedForeground }]}>
        {hasChosenPassword
          ? "Change the password you use to sign in. Every other device will be signed out."
          : "Your account signs in with one-time codes. Set a password to also enable password sign-in."}
      </Text>

      {hasChosenPassword ? (
        <TextField
          label="Current password"
          value={currentPassword}
          onChangeText={setCurrentPassword}
          secureTextEntry
          autoComplete="current-password"
          placeholder="Your current password"
        />
      ) : (
        <View style={styles.codeBlock}>
          <Button
            label={
              codeSentTo ? `Code sent to your ${codeSentTo}` : "Send me a code"
            }
            variant="secondary"
            onPress={onSendCode}
            loading={busy === "send"}
            disabled={busy === "save"}
          />
          <TextField
            label="Verification code"
            value={code}
            onChangeText={setCode}
            keyboardType="number-pad"
            maxLength={6}
            placeholder="6-digit code"
          />
        </View>
      )}

      <TextField
        label="New password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        autoComplete="new-password"
        placeholder="At least 8 characters"
      />
      <TextField
        label="Confirm new password"
        value={confirm}
        onChangeText={setConfirm}
        secureTextEntry
        autoComplete="new-password"
        placeholder="Repeat the password"
      />

      {error ? (
        <Text style={[styles.error, { color: colors.destructive }]}>
          {error}
        </Text>
      ) : null}

      <Button
        label={hasChosenPassword ? "Change password" : "Set password"}
        variant="cta"
        onPress={onSave}
        loading={busy === "save"}
        disabled={busy === "send"}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: { padding: 16, gap: 14, paddingBottom: 40 },
  lede: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  codeBlock: { gap: 14 },
  error: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
});
