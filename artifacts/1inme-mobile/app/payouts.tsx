import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as WebBrowser from "expo-web-browser";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import {
  getAdultContent,
  getPayouts,
  removeConnection,
  setDefaultConnection,
  startConnect,
  syncConnection,
  updateAdultContent,
  type PayoutConnection,
  type PayoutProvider,
} from "@/lib/api/payouts";
import { showAlert } from "@/lib/webAlert";

/**
 * Mobile parity for the "Earnings & Payouts" dashboard (Task #1208).
 * Mirrors the web at /user/payouts: lists providers, lets the creator
 * connect (opens the provider's hosted onboarding in the system
 * browser), pick a default, and refresh status.
 *
 * The 18+ adult-content toggle lives at the bottom of the same screen
 * — small enough to inline, and the consent dialog flows naturally
 * out of the rest of the listing.
 */
export default function PayoutsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const plan = usePlanFeatures();
  const sellLocked = plan.isFeatureLocked("ecommerce");
  const payouts = useQuery({ queryKey: ["payouts"], queryFn: getPayouts });
  const adult = useQuery({ queryKey: ["adult-content"], queryFn: getAdultContent });

  const promptSellUpgrade = () =>
    showUpgradePrompt({
      message:
        "Earning from your bio (subscriptions, tips and per-post unlocks) is a plan feature. Upgrade to start accepting payouts.",
    });

  const connect = useMutation({
    mutationFn: (slug: string) => startConnect(slug),
    onSuccess: async (r) => {
      qc.invalidateQueries({ queryKey: ["payouts"] });
      try {
        await WebBrowser.openBrowserAsync(r.onboarding_url);
      } catch {
        Linking.openURL(r.onboarding_url);
      }
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert("Connect failed", e?.message ?? "Unknown error");
    },
  });

  const refresh = useMutation({
    mutationFn: (id: number) => syncConnection(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["payouts"] }),
  });
  const makeDefault = useMutation({
    mutationFn: (id: number) => setDefaultConnection(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["payouts"] }),
    onError: (e: any) => showAlert("Could not set default", e?.message ?? "Unknown error"),
  });
  const remove = useMutation({
    mutationFn: (id: number) => removeConnection(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["payouts"] }),
  });

  const data = payouts.data;
  const connectionsByProvider = new Map<string, PayoutConnection>(
    (data?.connections ?? []).map((c) => [c.provider, c] as const),
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Earnings & Payouts" }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 60, gap: 12 }}>
        <Text style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}>
          Connect a payout provider to receive subscriptions, tips, and per-post unlocks.
          Sayzio takes 0% — the fee shown next to each provider is theirs.
        </Text>

        {sellLocked ? (
          <Pressable
            onPress={promptSellUpgrade}
            style={{
              flexDirection: "row",
              alignItems: "center",
              gap: 12,
              padding: 14,
              borderWidth: 1,
              borderRadius: colors.radius,
              backgroundColor: colors.primary + "12",
              borderColor: colors.primary + "44",
            }}
          >
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={{ color: colors.text, fontWeight: "700", fontSize: 14 }}>
                Payouts are a plan feature
              </Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12, lineHeight: 16 }}>
                Upgrade to start earning from your bio. Tap to see your options.
              </Text>
            </View>
            <UpgradeLockBadge />
          </Pressable>
        ) : null}

        {payouts.isLoading ? (
          <ActivityIndicator />
        ) : payouts.error ? (
          <Text style={{ color: colors.destructive }}>Could not load payouts.</Text>
        ) : (
          (data?.providers ?? []).map((p) => {
            const conn = connectionsByProvider.get(p.slug);
            const tint = providerTint(p.slug);
            return (
              <View
                key={p.slug}
                style={[
                  styles.card,
                  {
                    backgroundColor: colors.card,
                    borderColor: p.adult_friendly ? "#fda4af" : colors.border,
                  },
                ]}
              >
                <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 10 }}>
                  <View style={[styles.icon, { backgroundColor: tint }]}>
                    <Text style={{ color: "#fff", fontWeight: "700" }}>
                      {p.name.charAt(0)}
                    </Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
                      <Text style={{ color: colors.text, fontWeight: "700", fontSize: 15 }}>
                        {p.name}
                      </Text>
                      {p.adult_friendly && (
                        <Badge bg="#fee2e2" color="#9f1239" label="18+ OK" />
                      )}
                      {conn?.is_default && (
                        <Badge bg="#d1fae5" color="#065f46" label="Default" />
                      )}
                    </View>
                    <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}>{p.short}</Text>
                  </View>
                </View>

                <View style={{ marginTop: 12, gap: 4 }}>
                  <Row label="Countries" value={p.countries} colors={colors} />
                  <Row label="Payout speed" value={p.payout_speed} colors={colors} />
                  <Row label="Provider fees" value={p.fees} colors={colors} />
                </View>

                {conn && (
                  <View
                    style={{
                      marginTop: 10,
                      padding: 10,
                      borderRadius: 10,
                      backgroundColor: colors.background,
                    }}
                  >
                    <Text style={{ color: colors.text, fontSize: 12, fontWeight: "600" }}>
                      {conn.status_label}
                    </Text>
                    {conn.status_reason ? (
                      <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                        {conn.status_reason}
                      </Text>
                    ) : null}
                  </View>
                )}

                <View style={{ flexDirection: "row", gap: 8, flexWrap: "wrap", marginTop: 12 }}>
                  {(!conn || !conn.payouts_enabled) && (
                    <Button
                      label={
                        sellLocked
                          ? "Upgrade to connect"
                          : conn
                            ? "Resume"
                            : "Connect"
                      }
                      onPress={() =>
                        sellLocked
                          ? promptSellUpgrade()
                          : connect.mutate(p.slug)
                      }
                      disabled={connect.isPending}
                    />
                  )}
                  {conn && !conn.is_default && (!data?.adult_enabled || conn.adult_friendly) && (
                    <Button
                      label="Make default"
                      variant="secondary"
                      onPress={() => makeDefault.mutate(conn.id)}
                    />
                  )}
                  {conn && (
                    <Button
                      label="Refresh"
                      variant="secondary"
                      onPress={() => refresh.mutate(conn.id)}
                    />
                  )}
                  {conn && (
                    <Button
                      label="Remove"
                      variant="ghost"
                      onPress={() =>
                        showAlert("Disconnect", `Disconnect ${p.name}?`, [
                          { text: "Cancel", style: "cancel" },
                          {
                            text: "Disconnect",
                            style: "destructive",
                            onPress: () => remove.mutate(conn.id),
                          },
                        ])
                      }
                    />
                  )}
                </View>
              </View>
            );
          })
        )}

        {/* ── Adult content (18+) toggle ─────────────────────────── */}
        <View
          style={[
            styles.card,
            { backgroundColor: colors.card, borderColor: colors.border, marginTop: 14 },
          ]}
        >
          <Text style={{ color: colors.text, fontWeight: "700", fontSize: 15 }}>
            Adult content (18+)
          </Text>
          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}>
            Enable to publish 18+ content. Visitors will see an age-verification screen, the
            directory hides your profile by default, and your default payout will be locked to
            an adult-friendly processor (CCBill or Segpay).
          </Text>

          {adult.data?.flag_suspended && (
            <View
              style={{
                marginTop: 10,
                padding: 10,
                borderRadius: 10,
                backgroundColor: "#fee2e2",
              }}
            >
              <Text style={{ color: "#9f1239", fontWeight: "600", fontSize: 12 }}>
                Flag suspended by moderation
              </Text>
              {adult.data?.flag_suspended_reason ? (
                <Text style={{ color: "#9f1239", fontSize: 11, marginTop: 2 }}>
                  {adult.data.flag_suspended_reason}
                </Text>
              ) : null}
            </View>
          )}

          <AdultToggle state={adult.data} colors={colors} />
        </View>
      </ScrollView>
    </View>
  );
}

function AdultToggle({
  state,
  colors,
}: {
  state: ReturnType<typeof getAdultContent> extends Promise<infer T> ? T | undefined : never;
  colors: any;
}) {
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [age, setAge] = useState(false);
  const [legal, setLegal] = useState(false);
  const [processor, setProcessor] = useState(false);
  const enabled = !!state?.enabled;

  const m = useMutation({
    mutationFn: updateAdultContent,
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ["adult-content"] });
      qc.invalidateQueries({ queryKey: ["payouts"] });
      setOpen(false);
      if (r.enabled && r.needs_adult_provider) {
        showAlert(
          "Connect an adult-friendly processor",
          "Your previous default isn't adult-friendly. Connect CCBill or Segpay to receive payouts.",
        );
      }
    },
    onError: (e: any) => showAlert("Could not save", e?.message ?? "Unknown error"),
  });

  return (
    <View style={{ marginTop: 12 }}>
      <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
        <Text style={{ color: colors.text, fontWeight: "600" }}>Enable 18+ on my profile</Text>
        <Switch
          value={enabled || open}
          onValueChange={(v) => {
            if (enabled && !v) m.mutate({ enable: false });
            else setOpen(v);
          }}
          disabled={state?.flag_suspended || m.isPending}
        />
      </View>

      {open && !enabled && (
        <View
          style={{
            marginTop: 10,
            padding: 12,
            borderRadius: 10,
            backgroundColor: "#fff1f2",
            gap: 10,
          }}
        >
          <Text style={{ color: "#9f1239", fontWeight: "700", fontSize: 12 }}>
            Please confirm all three statements:
          </Text>
          <ConsentRow
            value={age}
            onChange={setAge}
            label="I am at least 18 years old (or the age of majority in my jurisdiction)."
          />
          <ConsentRow
            value={legal}
            onChange={setLegal}
            label="My content does NOT include illegal material or minors and complies with my jurisdiction's laws."
          />
          <ConsentRow
            value={processor}
            onChange={setProcessor}
            label="I understand my default payout will be locked to an adult-friendly processor (CCBill or Segpay)."
          />
          <Button
            label="Enable 18+"
            onPress={() =>
              m.mutate({
                enable: true,
                confirm_age: age,
                confirm_legal: legal,
                confirm_processor: processor,
              })
            }
            disabled={!age || !legal || !processor || m.isPending}
          />
        </View>
      )}
    </View>
  );
}

function ConsentRow({
  value,
  onChange,
  label,
}: {
  value: boolean;
  onChange: (v: boolean) => void;
  label: string;
}) {
  return (
    <TouchableOpacity
      onPress={() => onChange(!value)}
      style={{ flexDirection: "row", alignItems: "flex-start", gap: 10 }}
    >
      <View
        style={{
          width: 18,
          height: 18,
          borderRadius: 4,
          borderWidth: 1.5,
          borderColor: "#9f1239",
          backgroundColor: value ? "#9f1239" : "transparent",
          alignItems: "center",
          justifyContent: "center",
          marginTop: 1,
        }}
      >
        {value ? <Text style={{ color: "#fff", fontSize: 12, fontWeight: "800" }}>✓</Text> : null}
      </View>
      <Text style={{ color: "#7f1d1d", fontSize: 12, flex: 1, lineHeight: 17 }}>{label}</Text>
    </TouchableOpacity>
  );
}

function Row({ label, value, colors }: { label: string; value: string; colors: any }) {
  return (
    <View style={{ flexDirection: "row", justifyContent: "space-between", gap: 8 }}>
      <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>{label}</Text>
      <Text style={{ color: colors.text, fontSize: 12, flex: 1, textAlign: "right" }}>{value}</Text>
    </View>
  );
}

function Badge({ bg, color, label }: { bg: string; color: string; label: string }) {
  return (
    <View
      style={{
        backgroundColor: bg,
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 999,
      }}
    >
      <Text style={{ color, fontSize: 10, fontWeight: "800", letterSpacing: 0.5 }}>{label}</Text>
    </View>
  );
}

function providerTint(slug: string): string {
  switch (slug) {
    case "stripe":
      return "#635bff";
    case "paypal":
      return "#0070ba";
    case "razorpay":
      return "#3395ff";
    case "ccbill":
      return "#e63946";
    case "segpay":
      return "#7b1fa2";
    default:
      return "#475569";
  }
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
  },
  icon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
});
