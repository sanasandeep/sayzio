import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
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
import { useCooldown } from "@/hooks/useCooldown";
import type { ApiError } from "@/lib/api";
import {
  getIdentifiers,
  promoteIdentifier,
  removeIdentifier,
  sendIdentifierCode,
  verifyIdentifierCode,
  type LinkedIdentifier,
} from "@/lib/api/identifiers";
import { showAlert } from "@/lib/webAlert";

type AddKind = "email" | "phone";
type Step = "list" | "enter" | "verify" | "done";

export default function Identifiers() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const { refresh } = useAuth();

  const list = useQuery({
    queryKey: ["identifiers"],
    queryFn: getIdentifiers,
  });

  const [step, setStep] = useState<Step>("list");
  const [addKind, setAddKind] = useState<AddKind>("email");
  const [value, setValue] = useState("");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [resending, setResending] = useState(false);
  const [resentAt, setResentAt] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [demoReveal, setDemoReveal] = useState<string | null>(null);
  // Per-row in-flight action id so only the tapped row spins.
  const [actingId, setActingId] = useState<number | null>(null);
  const cooldown = useCooldown(30);

  const webBottom = Platform.OS === "web" ? 34 : 0;
  const bottomPad = insets.bottom + 32 + webBottom;

  // Keep the primary email/phone shown on the profile header aligned with the
  // change the moment the user returns, no manual refresh needed.
  const afterChange = () => {
    void qc.invalidateQueries({ queryKey: ["identifiers"] });
    void qc.invalidateQueries({ queryKey: ["whatsapp-status"] });
    void refresh();
  };

  const startAdd = (kind: AddKind) => {
    setAddKind(kind);
    setValue("");
    setCode("");
    setDemoReveal(null);
    setResentAt(null);
    cooldown.clear();
    setError(null);
    setStep("enter");
  };

  const sendCode = async () => {
    const v = value.trim();
    if (!v) {
      setError(
        addKind === "email"
          ? "Enter the email address you want to add."
          : "Enter the phone number with country code (e.g. +1 555 123 4567).",
      );
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await sendIdentifierCode(addKind, v);
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
      const res = await sendIdentifierCode(addKind, v);
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
      await verifyIdentifierCode(addKind, value.trim(), code.trim());
      afterChange();
      setStep("done");
    } catch (e) {
      setError((e as ApiError)?.message ?? "That code didn't match. Try again.");
    } finally {
      setBusy(false);
    }
  };

  const onPromote = (row: LinkedIdentifier) => {
    showAlert(
      "Make primary?",
      `${row.value} will become your primary ${row.kind === "email" ? "email" : "phone"}; it's what shows on your account and receives key notifications.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Make primary",
          onPress: async () => {
            setActingId(row.id);
            try {
              await promoteIdentifier(row.id);
              afterChange();
            } catch (e) {
              showAlert("Couldn't update", (e as ApiError)?.message ?? "Please try again.");
            } finally {
              setActingId(null);
            }
          },
        },
      ],
    );
  };

  const onRemove = (row: LinkedIdentifier) => {
    showAlert(
      "Remove identifier?",
      `${row.value} will be unlinked from your account. You'll no longer be able to sign in with it.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Remove",
          style: "destructive",
          onPress: async () => {
            setActingId(row.id);
            try {
              await removeIdentifier(row.id);
              afterChange();
            } catch (e) {
              showAlert("Couldn't remove", (e as ApiError)?.message ?? "Please try again.");
            } finally {
              setActingId(null);
            }
          },
        },
      ],
    );
  };

  const loading = list.isLoading && !list.data;
  const rows = list.data?.identifiers ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Linked emails & phones" }} />
      {loading ? (
        <View style={styles.loading}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={[styles.scroll, { paddingBottom: bottomPad }]}
          keyboardShouldPersistTaps="handled"
        >
          {step === "list" ? (
            <>
              <Header
                colors={colors}
                title="Linked emails & phones"
                sub="These are the verified identifiers on your account. Any of them can sign you in. Your primary one shows on your account and receives key notifications."
              />

              {list.isError ? (
                <View style={{ marginTop: 20 }}>
                  <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                    Couldn't load your identifiers.
                  </Text>
                  <View style={{ height: 12 }} />
                  <Button label="Retry" variant="ghost" onPress={() => void list.refetch()} />
                </View>
              ) : null}

              <View style={{ height: 20 }} />
              <View style={{ gap: 12 }}>
                {rows.map((row) => (
                  <IdentifierCard
                    key={row.id}
                    colors={colors}
                    row={row}
                    busy={actingId === row.id}
                    onPromote={() => onPromote(row)}
                    onRemove={() => onRemove(row)}
                  />
                ))}
              </View>

              <View style={{ height: 24 }} />
              <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                Add an identifier
              </Text>
              <View style={{ height: 12 }} />
              <Button
                label="Add an email"
                variant="secondary"
                onPress={() => startAdd("email")}
              />
              <View style={{ height: 12 }} />
              <Button
                label="Add a phone"
                variant="secondary"
                onPress={() => startAdd("phone")}
              />
            </>
          ) : null}

          {step === "enter" ? (
            <>
              <Header
                colors={colors}
                title={addKind === "email" ? "Add an email" : "Add a phone"}
                sub={
                  addKind === "email"
                    ? "We'll send a 6-digit code to confirm the email is yours, then link it to your account."
                    : "We'll text a 6-digit code to confirm the number is yours, then link it to your account."
                }
              />
              <View style={{ height: 20 }} />
              <TextField
                label={addKind === "email" ? "Email address" : "Phone number"}
                placeholder={addKind === "email" ? "you@example.com" : "+1 555 123 4567"}
                keyboardType={addKind === "email" ? "email-address" : "phone-pad"}
                autoCapitalize="none"
                autoCorrect={false}
                value={value}
                onChangeText={setValue}
                error={error ?? undefined}
              />
              <View style={{ height: 16 }} />
              <Button label="Send code" onPress={sendCode} loading={busy} />
              <View style={{ height: 12 }} />
              <Button
                label="Cancel"
                variant="ghost"
                onPress={() => {
                  setError(null);
                  setStep("list");
                }}
                disabled={busy}
              />
            </>
          ) : null}

          {step === "verify" ? (
            <>
              <Header
                colors={colors}
                title="Enter the code"
                sub={`We sent a 6-digit code to ${value.trim()}. Enter it to confirm.`}
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
                  setStep("enter");
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
                title={addKind === "email" ? "Email linked" : "Phone linked"}
                sub="It's verified and attached to your account. You can now sign in with it or make it your primary identifier."
              />
              <View style={{ height: 20 }} />
              <Button label="Done" onPress={() => setStep("list")} />
            </>
          ) : null}
        </ScrollView>
      )}
    </View>
  );
}

function IdentifierCard({
  colors,
  row,
  busy,
  onPromote,
  onRemove,
}: {
  colors: ReturnType<typeof useColors>;
  row: LinkedIdentifier;
  busy: boolean;
  onPromote: () => void;
  onRemove: () => void;
}) {
  const icon =
    row.kind === "email" ? "mail" : row.kind === "phone" ? "phone" : "share-2";

  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={styles.cardTop}>
        <View style={styles.cardIdentity}>
          <Feather name={icon} size={18} color={colors.primary} />
          <View style={{ flexShrink: 1 }}>
            <Text style={[styles.cardValue, { color: colors.foreground }]} numberOfLines={1}>
              {row.value}
            </Text>
            <Text style={[styles.cardKind, { color: colors.mutedForeground }]}>
              {row.kind_label}
            </Text>
          </View>
        </View>
        {row.is_primary ? (
          <View style={[styles.primaryBadge, { backgroundColor: colors.primary + "1a" }]}>
            <Feather name="star" size={12} color={colors.primary} />
            <Text style={[styles.primaryBadgeText, { color: colors.primary }]}>Primary</Text>
          </View>
        ) : null}
      </View>

      {busy ? (
        <View style={styles.cardBusy}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <View style={styles.cardActions}>
          {row.can_promote ? (
            <CardAction
              colors={colors}
              icon="star"
              label="Make primary"
              onPress={onPromote}
            />
          ) : null}
          {row.can_remove ? (
            <CardAction
              colors={colors}
              icon="trash-2"
              label="Remove"
              destructive
              onPress={onRemove}
            />
          ) : null}
        </View>
      )}

      {!row.can_remove && row.remove_blocked_reason && !row.is_primary ? (
        <Text style={[styles.blockedNote, { color: colors.mutedForeground }]}>
          {row.remove_blocked_reason}
        </Text>
      ) : null}
    </View>
  );
}

function CardAction({
  colors,
  icon,
  label,
  onPress,
  destructive,
}: {
  colors: ReturnType<typeof useColors>;
  icon: keyof typeof Feather.glyphMap;
  label: string;
  onPress: () => void;
  destructive?: boolean;
}) {
  const tint = destructive ? colors.destructive : colors.primary;
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.actionBtn,
        {
          borderColor: colors.border,
          borderRadius: colors.radius - 4,
          opacity: pressed ? 0.7 : 1,
        },
      ]}
    >
      <Feather name={icon} size={15} color={tint} />
      <Text style={[styles.actionText, { color: tint }]}>{label}</Text>
    </Pressable>
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
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 15, lineHeight: 22 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    textTransform: "uppercase",
    letterSpacing: 0.6,
  },
  card: { borderWidth: 1, padding: 16, gap: 14 },
  cardTop: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10 },
  cardIdentity: { flexDirection: "row", alignItems: "center", gap: 10, flexShrink: 1 },
  cardValue: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  cardKind: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, marginTop: 1 },
  primaryBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  primaryBadgeText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  cardActions: { flexDirection: "row", gap: 10, flexWrap: "wrap" },
  cardBusy: { alignItems: "flex-start" },
  actionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  actionText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  blockedNote: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
  demoBanner: { borderWidth: 1, paddingVertical: 12, paddingHorizontal: 14, marginBottom: 16 },
  demoBannerText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, lineHeight: 20 },
  doneIcon: { alignItems: "center", marginBottom: 16 },
});
