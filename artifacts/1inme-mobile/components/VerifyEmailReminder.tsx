import { Feather } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  Text,
  TextInput,
  View,
} from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import {
  confirmEmailVerifyCode,
  sendEmailVerifyCode,
} from "@/lib/api/emailVerification";

// In-app reminder nudging signed-in users who skipped email verification at
// sign-up (mobile parity with the web banner, Task #1863). Tapping "Send
// code" emails a 6-digit code; entering it verifies the email and stamps
// email_verified_at server-side, after which the reminder disappears.
//
// Visibility mirrors the web rule: only when the user's email is unverified,
// they have an email on file, and email verification is meaningful under the
// current login policy (email_verification_meaningful !== false — older
// cached users without the flag still get the nudge).
//
// Dismissal is per app session (in-memory module flag, like the web banner's
// sessionStorage): once dismissed it stays hidden until the app is relaunched.
let dismissedThisSession = false;

export function VerifyEmailReminder() {
  const colors = useColors();
  const { user, refresh } = useAuth();
  const [dismissed, setDismissed] = useState(dismissedThisSession);
  const [showCode, setShowCode] = useState(false);
  const [code, setCode] = useState("");
  const [sending, setSending] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Pull a fresh user once so a stale cached session (verified elsewhere, or
  // missing the new fields) resolves before we decide whether to show.
  useEffect(() => {
    void refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const shouldShow =
    !!user &&
    !user.email_verified_at &&
    !!user.email &&
    user.email_verification_meaningful !== false;

  if (!shouldShow || dismissed) return null;

  const dismiss = () => {
    dismissedThisSession = true;
    setDismissed(true);
  };

  const send = async () => {
    if (sending) return;
    setSending(true);
    setError(null);
    setStatus(null);
    try {
      const res = await sendEmailVerifyCode();
      if (res.already_verified) {
        await refresh();
        return;
      }
      setShowCode(true);
      setStatus(`We sent a 6-digit code to ${user?.email}. Enter it below.`);
    } catch (e) {
      setError(
        (e as { message?: string })?.message ??
          "Couldn't send the code. Please try again.",
      );
    } finally {
      setSending(false);
    }
  };

  const confirm = async () => {
    if (confirming) return;
    if (code.trim().length !== 6) {
      setError("Enter the 6-digit code from your email.");
      return;
    }
    setConfirming(true);
    setError(null);
    try {
      await confirmEmailVerifyCode(code.trim());
      // Success — pull the freshly-verified user so this reminder unmounts.
      await refresh();
    } catch (e) {
      setError(
        (e as { message?: string })?.message ??
          "Invalid or expired code. Please request a new one.",
      );
    } finally {
      setConfirming(false);
    }
  };

  const amber = "#f59e0b";

  return (
    <View
      style={{
        padding: 14,
        borderRadius: 14,
        borderWidth: 1,
        borderColor: "rgba(245,158,11,0.3)",
        backgroundColor: "rgba(245,158,11,0.1)",
        gap: 10,
      }}
    >
      <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 10 }}>
        <Feather name="mail" size={18} color={amber} style={{ marginTop: 1 }} />
        <Text
          style={{
            flex: 1,
            color: colors.foreground,
            fontSize: 13,
            lineHeight: 18,
          }}
        >
          Verify your email{" "}
          <Text style={{ fontWeight: "700" }}>{user?.email}</Text> to keep your
          account secure and your links deliverable.
        </Text>
        <Pressable
          onPress={dismiss}
          hitSlop={8}
          accessibilityLabel="Dismiss this reminder"
        >
          <Feather name="x" size={16} color={colors.mutedForeground} />
        </Pressable>
      </View>

      {!showCode ? (
        <Pressable
          onPress={send}
          disabled={sending}
          style={{
            alignSelf: "flex-start",
            flexDirection: "row",
            alignItems: "center",
            gap: 6,
            paddingHorizontal: 14,
            paddingVertical: 8,
            borderRadius: 999,
            borderWidth: 1,
            borderColor: "rgba(245,158,11,0.5)",
            backgroundColor: "rgba(245,158,11,0.14)",
          }}
        >
          {sending ? (
            <ActivityIndicator size="small" color={amber} />
          ) : (
            <Feather name="send" size={13} color={amber} />
          )}
          <Text style={{ color: amber, fontWeight: "700", fontSize: 12 }}>
            Send code
          </Text>
        </Pressable>
      ) : (
        <View style={{ gap: 8 }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <TextInput
              value={code}
              onChangeText={(t) => setCode(t.replace(/[^0-9]/g, "").slice(0, 6))}
              placeholder="6-digit code"
              placeholderTextColor={colors.mutedForeground}
              keyboardType="number-pad"
              inputMode="numeric"
              autoComplete="one-time-code"
              textContentType="oneTimeCode"
              maxLength={6}
              style={{
                flex: 1,
                paddingHorizontal: 14,
                paddingVertical: 10,
                borderRadius: 10,
                borderWidth: 1,
                borderColor: "rgba(245,158,11,0.4)",
                backgroundColor: colors.card,
                color: colors.foreground,
                fontSize: 16,
                letterSpacing: 6,
                textAlign: "center",
              }}
            />
            <Pressable
              onPress={confirm}
              disabled={confirming}
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 6,
                paddingHorizontal: 16,
                paddingVertical: 11,
                borderRadius: 10,
                backgroundColor: colors.success,
              }}
            >
              {confirming ? (
                <ActivityIndicator size="small" color={colors.successForeground} />
              ) : (
                <Feather name="check" size={14} color={colors.successForeground} />
              )}
              <Text style={{ color: colors.successForeground, fontWeight: "700", fontSize: 13 }}>
                Verify
              </Text>
            </Pressable>
          </View>
          <Pressable onPress={send} disabled={sending} hitSlop={6}>
            <Text
              style={{
                color: amber,
                fontSize: 12,
                fontWeight: "600",
                textDecorationLine: "underline",
              }}
            >
              Resend code
            </Text>
          </Pressable>
        </View>
      )}

      {status ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
          {status}
        </Text>
      ) : null}
      {error ? (
        <Text style={{ color: colors.destructive, fontSize: 12 }}>{error}</Text>
      ) : null}
    </View>
  );
}
