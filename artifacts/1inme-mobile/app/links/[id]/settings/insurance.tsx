import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import type { ApiError } from "@/lib/api";
import {
  getLinkInsurance,
  probeLinkInsurance,
  restoreLinkInsurance,
  updateLinkInsurance,
  type InsuranceSettings,
  type InsuranceState,
} from "@/lib/api/insurance";

function cadenceLabel(min: number): string {
  if (min >= 60) {
    const h = min / 60;
    return `${h} hour${h >= 2 ? "s" : ""}`;
  }
  return `${min} min`;
}

function stateColor(
  state: InsuranceState,
  colors: ReturnType<typeof useColors>,
): string {
  if (state === "primary") return colors.success;
  if (state === "failover") return colors.warning;
  return colors.destructive;
}

function timeAgo(iso: string | null): string {
  if (!iso) return "Never";
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return "Never";
  const diff = Date.now() - then;
  const m = Math.round(diff / 60000);
  if (m < 1) return "just now";
  if (m < 60) return `${m}m ago`;
  const h = Math.round(m / 60);
  if (h < 24) return `${h}h ago`;
  const d = Math.round(h / 24);
  return `${d}d ago`;
}

type BackupRow = { url: string; label: string };

export default function InsuranceSettingsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const linkId = Number(id);
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["link-insurance", linkId],
    queryFn: () => getLinkInsurance(linkId),
    enabled: Number.isFinite(linkId),
  });

  const [enabled, setEnabled] = useState(false);
  const [cadence, setCadence] = useState(15);
  const [failureThreshold, setFailureThreshold] = useState("2");
  const [recoveryThreshold, setRecoveryThreshold] = useState("3");
  const [autoRestore, setAutoRestore] = useState(true);
  const [fallbackMessage, setFallbackMessage] = useState("");
  const [backups, setBackups] = useState<BackupRow[]>([]);
  const maxBackups = q.data?.options.max_backups ?? 3;
  const cadences = q.data?.options.cadences ?? [5, 15, 30, 60, 240];

  useEffect(() => {
    if (!q.data) return;
    const s = q.data.settings;
    setEnabled(s.insurance_enabled);
    setCadence(s.insurance_cadence_minutes);
    setFailureThreshold(String(s.insurance_failure_threshold));
    setRecoveryThreshold(String(s.insurance_recovery_threshold));
    setAutoRestore(s.insurance_auto_restore);
    setFallbackMessage(s.insurance_fallback_message ?? "");
    const rows: BackupRow[] = [];
    const max = q.data.options.max_backups ?? 3;
    for (let i = 0; i < max; i++) {
      const b = q.data.backups[i];
      rows.push({ url: b?.url ?? "", label: b?.label ?? "" });
    }
    setBackups(rows);
  }, [q.data]);

  const onSaved = (data: InsuranceSettings) => {
    qc.setQueryData(["link-insurance", linkId], data);
    qc.invalidateQueries({ queryKey: ["insurance-dashboard"] });
  };

  const save = useMutation({
    mutationFn: () =>
      updateLinkInsurance(linkId, {
        insurance_enabled: enabled,
        insurance_cadence_minutes: cadence,
        insurance_failure_threshold: Math.max(
          1,
          Math.min(10, Number(failureThreshold) || 2),
        ),
        insurance_recovery_threshold: Math.max(
          1,
          Math.min(10, Number(recoveryThreshold) || 3),
        ),
        insurance_auto_restore: autoRestore,
        insurance_fallback_message: fallbackMessage.trim() || null,
        backups: backups
          .filter((b) => b.url.trim())
          .map((b) => ({ url: b.url.trim(), label: b.label.trim() || null })),
      }),
    onSuccess: onSaved,
  });

  const restore = useMutation({
    mutationFn: () => restoreLinkInsurance(linkId),
    onSuccess: (r) => onSaved(r.link),
  });

  const probe = useMutation({
    mutationFn: () => probeLinkInsurance(linkId),
    onSuccess: (r) => onSaved(r.link),
  });

  if (q.isLoading) {
    return (
      <>
        <Stack.Screen options={{ headerShown: true, title: "Link Insurance" }} />
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      </>
    );
  }

  if (q.isError || !q.data) {
    return (
      <>
        <Stack.Screen options={{ headerShown: true, title: "Link Insurance" }} />
        <View style={styles.center}>
          <Text style={{ color: colors.mutedForeground }}>
            {(q.error as unknown as ApiError)?.message ?? "Could not load insurance settings."}
          </Text>
        </View>
      </>
    );
  }

  const state = q.data.state.insurance_state;
  const saveError = save.error as unknown as ApiError | null;

  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Link Insurance" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
          Add up to {maxBackups} backup destinations. If your primary URL goes
          down, we automatically redirect new clicks to the next healthy backup
          until the primary is back.
        </Text>

        {/* Current state */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            Current state
          </Text>
          <View style={styles.stateRow}>
            <View
              style={[
                styles.badge,
                { backgroundColor: stateColor(state, colors) + "22" },
              ]}
            >
              <Text style={{ color: stateColor(state, colors), fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 }}>
                {state.charAt(0).toUpperCase() + state.slice(1)}
              </Text>
            </View>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              Last checked {timeAgo(q.data.state.last_checked_at)}
            </Text>
          </View>
          {state === "failover" && q.data.state.insurance_active_url ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 6 }}>
              Serving: {q.data.state.insurance_active_url}
            </Text>
          ) : null}
          <View style={styles.stateActions}>
            <Button
              label="Test now"
              variant="outline"
              loading={probe.isPending}
              onPress={() => probe.mutate()}
              style={{ flex: 1 }}
            />
            {state !== "primary" ? (
              <Button
                label="Restore primary"
                loading={restore.isPending}
                onPress={() => restore.mutate()}
                style={{ flex: 1 }}
              />
            ) : null}
          </View>
          {probe.data ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 8 }}>
              {probe.data.message}
            </Text>
          ) : null}
        </View>

        {/* Enable */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Pressable
            onPress={() => setEnabled((v) => !v)}
            style={styles.switchRow}
          >
            <Text style={[styles.switchLabel, { color: colors.foreground }]}>
              Enable Link Insurance for this link
            </Text>
            <Switch
              value={enabled}
              onValueChange={setEnabled}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </Pressable>
        </View>

        {/* Settings */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            Check every
          </Text>
          <View style={styles.chips}>
            {cadences.map((c) => {
              const active = c === cadence;
              return (
                <Pressable
                  key={c}
                  onPress={() => setCadence(c)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? colors.primary : "transparent",
                      borderColor: active ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: active ? colors.primaryForeground : colors.foreground,
                      fontSize: 13,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                    }}
                  >
                    {cadenceLabel(c)}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          <View style={{ height: 12 }} />
          <TextField
            label="Failover after N consecutive failures"
            value={failureThreshold}
            keyboardType="number-pad"
            onChangeText={setFailureThreshold}
          />
          <View style={{ height: 12 }} />
          <TextField
            label="Restore after N consecutive successes"
            value={recoveryThreshold}
            keyboardType="number-pad"
            onChangeText={setRecoveryThreshold}
          />

          <Pressable
            onPress={() => setAutoRestore((v) => !v)}
            style={[styles.switchRow, { marginTop: 12 }]}
          >
            <Text style={[styles.switchLabel, { color: colors.foreground }]}>
              Auto-restore primary when healthy again
            </Text>
            <Switch
              value={autoRestore}
              onValueChange={setAutoRestore}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </Pressable>

          <View style={{ height: 12 }} />
          <TextField
            label="Fallback message (shown if everything is down)"
            value={fallbackMessage}
            onChangeText={setFallbackMessage}
            placeholder="Optional; leave blank to keep redirecting"
          />
        </View>

        {/* Backups */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            Backup destinations
          </Text>
          {backups.map((b, i) => {
            const probed = q.data?.backups[i];
            return (
              <View key={i} style={{ marginBottom: 14 }}>
                <TextField
                  label={`Backup #${i + 1} URL`}
                  value={b.url}
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="url"
                  placeholder={`https://backup-${i + 1}.example.com`}
                  onChangeText={(t) =>
                    setBackups((prev) =>
                      prev.map((row, idx) =>
                        idx === i ? { ...row, url: t } : row,
                      ),
                    )
                  }
                />
                <View style={{ height: 8 }} />
                <TextField
                  label="Label (optional)"
                  value={b.label}
                  placeholder="e.g. Mirror on Netlify"
                  onChangeText={(t) =>
                    setBackups((prev) =>
                      prev.map((row, idx) =>
                        idx === i ? { ...row, label: t } : row,
                      ),
                    )
                  }
                />
                {probed?.last_status ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 6 }}>
                    Last probe: {probed.last_status}
                    {probed.last_http_code ? ` (HTTP ${probed.last_http_code})` : ""}
                    {probed.last_checked_at ? ` · ${timeAgo(probed.last_checked_at)}` : ""}
                  </Text>
                ) : null}
              </View>
            );
          })}
        </View>

        {saveError ? (
          <Text style={{ color: colors.destructive, fontSize: 13, marginBottom: 10 }}>
            {saveError.errors
              ? Object.values(saveError.errors).flat().join("\n")
              : saveError.message}
          </Text>
        ) : null}

        <Button
          label="Save settings"
          loading={save.isPending}
          onPress={() => save.mutate()}
        />

        {/* Recent probes */}
        {q.data.recent_checks.length > 0 ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border, marginTop: 18 }]}>
            <Text style={[styles.cardTitle, { color: colors.foreground }]}>
              Recent probes
            </Text>
            {q.data.recent_checks.map((c, i) => (
              <View
                key={i}
                style={[
                  styles.probeRow,
                  { borderTopColor: colors.border, borderTopWidth: i === 0 ? 0 : 1 },
                ]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontSize: 12 }} numberOfLines={1}>
                    {c.target_url ?? "—"}
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                    {timeAgo(c.checked_at)}
                    {c.http_code ? ` · HTTP ${c.http_code}` : ""}
                    {c.latency_ms != null ? ` · ${c.latency_ms}ms` : ""}
                  </Text>
                </View>
                <View
                  style={[
                    styles.badge,
                    {
                      backgroundColor:
                        (c.status === "healthy" ? colors.success : colors.destructive) + "22",
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: c.status === "healthy" ? colors.success : colors.destructive,
                      fontSize: 11,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                    }}
                  >
                    {c.status}
                  </Text>
                </View>
              </View>
            ))}
          </View>
        ) : null}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, paddingBottom: 48 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  blurb: {
    fontSize: 13,
    fontFamily: "SpaceGrotesk_400Regular",
    lineHeight: 19,
    marginBottom: 16,
  },
  card: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 16,
    marginBottom: 14,
  },
  cardTitle: {
    fontSize: 14,
    fontFamily: "SpaceGrotesk_600SemiBold",
    marginBottom: 10,
  },
  stateRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  stateActions: { flexDirection: "row", gap: 10, marginTop: 14 },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  switchLabel: {
    fontSize: 13,
    fontFamily: "SpaceGrotesk_600SemiBold",
    flex: 1,
  },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  probeRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 10,
  },
});
