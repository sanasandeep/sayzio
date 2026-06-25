import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
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
import { useCooldown } from "@/hooks/useCooldown";
import type { ApiError } from "@/lib/api";
import {
  mergeChallenge,
  mergeConfirm,
  mergeVerify,
  type MergeKind,
  type MergePreview,
} from "@/lib/api/accountMerge";

type Step = "identify" | "verify" | "preview" | "done";

export default function AccountMerge() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { user, refresh } = useAuth();

  const [step, setStep] = useState<Step>("identify");
  const [kind, setKind] = useState<MergeKind>("email");
  const [value, setValue] = useState("");
  const [code, setCode] = useState("");
  const [mergeToken, setMergeToken] = useState<string | null>(null);
  const [preview, setPreview] = useState<MergePreview | null>(null);
  const [keepPlan, setKeepPlan] = useState<"primary" | "secondary">("primary");
  const [movedCount, setMovedCount] = useState(0);
  const [busy, setBusy] = useState(false);
  const [resending, setResending] = useState(false);
  const [resentAt, setResentAt] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Admin "Demo mode" toggle: backend returns the live code so it can be shown
  // on screen; null when the toggle is off. Refreshed on each (re)send.
  const [demoReveal, setDemoReveal] = useState<string | null>(null);
  const cooldown = useCooldown(30);

  const webBottom = Platform.OS === "web" ? 34 : 0;
  const bottomPad = insets.bottom + 32 + webBottom;

  // The merge flow needs a signed-in "account to keep". When reached from
  // the social sign-in prompt the user may not be authenticated yet — guide
  // them to sign in to the survivor account first.
  if (!user) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "Merge accounts" }} />
        <ScrollView contentContainerStyle={[styles.scroll, { paddingBottom: bottomPad }]}>
          <Header
            colors={colors}
            title="Sign in to the account you'll keep"
            sub="Merging pulls a second account's data into this one. First sign in to the account you want to keep — then you can prove you own the other one and merge it in."
          />
          <View style={{ height: 20 }} />
          <Button
            label="Sign in"
            onPress={() => router.replace("/(auth)")}
          />
        </ScrollView>
      </View>
    );
  }

  const reset = () => {
    setStep("identify");
    setCode("");
    setMergeToken(null);
    setPreview(null);
    setKeepPlan("primary");
    setResentAt(null);
    setDemoReveal(null);
    cooldown.clear();
    setError(null);
  };

  const sendCode = async () => {
    const v = value.trim();
    if (!v) {
      setError(kind === "email" ? "Enter the other account's email" : "Enter the other account's phone number");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await mergeChallenge({ kind, value: v });
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
    const v = value.trim();
    if (!v) return;
    setResending(true);
    setError(null);
    try {
      const res = await mergeChallenge({ kind, value: v });
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
      const res = await mergeVerify({ kind, value: value.trim(), code: code.trim() });
      setMergeToken(res.merge_token);
      setPreview(res.preview);
      // Default the kept plan to whichever side actually has a paid plan.
      if (res.preview.secondary_has_paid_plan && !res.preview.primary_has_paid_plan) {
        setKeepPlan("secondary");
      } else {
        setKeepPlan("primary");
      }
      setStep("preview");
    } catch (e) {
      setError((e as ApiError)?.message ?? "That code didn't match. Try again.");
    } finally {
      setBusy(false);
    }
  };

  const confirmMerge = async () => {
    if (!mergeToken) return;
    setBusy(true);
    setError(null);
    try {
      const res = await mergeConfirm({ merge_token: mergeToken, keep_plan_from: keepPlan });
      setMovedCount(res.records_moved);
      setStep("done");
      // The survivor account's plan/identifiers may have changed — refresh.
      void refresh();
    } catch (e) {
      setError((e as ApiError)?.message ?? "We couldn't complete the merge. No changes were made.");
    } finally {
      setBusy(false);
    }
  };

  const bothPaid = preview?.primary_has_paid_plan && preview?.secondary_has_paid_plan;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Merge accounts" }} />
      <ScrollView
        contentContainerStyle={[styles.scroll, { paddingBottom: bottomPad }]}
        keyboardShouldPersistTaps="handled"
      >
        {step === "identify" ? (
          <>
            <Header
              colors={colors}
              title="Merge another account"
              sub={`Pull everything from another 1INME account you own into ${user.email ?? "this account"}. We'll send a code to the other account to confirm it's yours. This can't be undone.`}
            />
            <View style={{ height: 20 }} />

            <View style={styles.toggleRow}>
              <Segment
                colors={colors}
                label="Email"
                active={kind === "email"}
                onPress={() => {
                  setKind("email");
                  setError(null);
                }}
              />
              <Segment
                colors={colors}
                label="Phone"
                active={kind === "phone"}
                onPress={() => {
                  setKind("phone");
                  setError(null);
                }}
              />
            </View>

            <View style={{ height: 14 }} />
            <TextField
              label={kind === "email" ? "Other account's email" : "Other account's phone"}
              placeholder={kind === "email" ? "you@example.com" : "+1 555 123 4567"}
              keyboardType={kind === "email" ? "email-address" : "phone-pad"}
              autoCapitalize="none"
              autoCorrect={false}
              value={value}
              onChangeText={setValue}
              error={error ?? undefined}
            />
            <View style={{ height: 16 }} />
            <Button label="Send code" onPress={sendCode} loading={busy} />
          </>
        ) : null}

        {step === "verify" ? (
          <>
            <Header
              colors={colors}
              title="Enter the code"
              sub={`We sent a 6-digit code to ${value.trim()}. Enter it to prove you own that account.`}
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
                setStep("identify");
                setCode("");
                setResentAt(null);
                cooldown.clear();
                setError(null);
              }}
              disabled={busy || resending}
            />
          </>
        ) : null}

        {step === "preview" && preview ? (
          <>
            <Header
              colors={colors}
              title="Review the merge"
              sub={`Everything below moves from ${preview.secondary.email ?? "the other account"} into ${preview.primary.email ?? "your account"}, then the other account is deleted. This can't be undone.`}
            />

            <View style={{ height: 18 }} />
            <View
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                {preview.total_records} record{preview.total_records === 1 ? "" : "s"} will move
              </Text>
              {preview.items.length === 0 ? (
                <Text style={[styles.muted, { color: colors.mutedForeground }]}>
                  The other account has no content to move — merging will just
                  free up its email and phone for this account.
                </Text>
              ) : (
                preview.items.map((it) => (
                  <View key={it.key} style={styles.lineRow}>
                    <Text style={[styles.lineLabel, { color: colors.foreground }]}>
                      {it.label}
                    </Text>
                    <Text style={[styles.lineCount, { color: colors.mutedForeground }]}>
                      {it.count}
                    </Text>
                  </View>
                ))
              )}
            </View>

            {preview.identifiers.length > 0 ? (
              <>
                <View style={{ height: 12 }} />
                <View
                  style={[
                    styles.card,
                    { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                  ]}
                >
                  <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                    Sign-in methods you'll gain
                  </Text>
                  {preview.identifiers.map((idf, i) => (
                    <View key={`${idf.kind}-${i}`} style={styles.lineRow}>
                      <Feather
                        name={idf.kind === "social" ? "share-2" : idf.kind === "phone" ? "phone" : "mail"}
                        size={15}
                        color={colors.primary}
                      />
                      <Text style={[styles.lineLabel, { color: colors.foreground, flex: 1, marginLeft: 8 }]}>
                        {idf.label}
                      </Text>
                    </View>
                  ))}
                </View>
              </>
            ) : null}

            {bothPaid ? (
              <>
                <View style={{ height: 12 }} />
                <View
                  style={[
                    styles.card,
                    { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                  ]}
                >
                  <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                    Both accounts have a paid plan
                  </Text>
                  <Text style={[styles.muted, { color: colors.mutedForeground, marginBottom: 6 }]}>
                    You can only keep one. The other is cancelled with no refund.
                  </Text>
                  <View style={styles.toggleRow}>
                    <Segment
                      colors={colors}
                      label="Keep this account's plan"
                      active={keepPlan === "primary"}
                      onPress={() => setKeepPlan("primary")}
                    />
                    <Segment
                      colors={colors}
                      label="Keep the other plan"
                      active={keepPlan === "secondary"}
                      onPress={() => setKeepPlan("secondary")}
                    />
                  </View>
                </View>
              </>
            ) : null}

            {error ? (
              <Text style={[styles.errorText, { color: colors.destructive }]}>
                {error}
              </Text>
            ) : null}

            <View style={{ height: 18 }} />
            <Button label="Merge accounts" onPress={confirmMerge} loading={busy} />
            <View style={{ height: 8 }} />
            <Button label="Cancel" variant="ghost" onPress={reset} disabled={busy} />
          </>
        ) : null}

        {step === "done" ? (
          <>
            <View style={styles.doneIcon}>
              <Feather name="check-circle" size={48} color={colors.primary} />
            </View>
            <Header
              colors={colors}
              title="Accounts merged"
              sub={`${movedCount} record${movedCount === 1 ? "" : "s"} moved into your account, and the other account was deleted. You can now sign in with either of its contact methods.`}
            />
            <View style={{ height: 20 }} />
            <Button label="Done" onPress={() => router.replace("/(tabs)")} />
          </>
        ) : null}
      </ScrollView>
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

function Segment({
  colors,
  label,
  active,
  onPress,
}: {
  colors: ReturnType<typeof useColors>;
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Button
      label={label}
      variant={active ? "primary" : "outline"}
      onPress={onPress}
      style={{ flex: 1, minHeight: 44 }}
    />
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 24 },
  h1: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 26, letterSpacing: -0.4 },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
  },
  toggleRow: { flexDirection: "row", gap: 10 },
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
  card: { borderWidth: 1, padding: 16, gap: 10 },
  cardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  muted: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
  lineRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  lineLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  lineCount: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  errorText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    marginTop: 14,
  },
  doneIcon: { alignItems: "center", marginBottom: 16 },
});
