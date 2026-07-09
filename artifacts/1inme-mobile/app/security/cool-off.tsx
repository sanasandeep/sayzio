import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
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
import {
  cancelSensitiveChange,
  getSecuritySettings,
  listPendingSensitiveChanges,
  stageSensitiveChange,
  updateSecuritySettings,
  type PendingSensitiveChange,
  type SecuritySettings,
} from "@/lib/api/security";
import { showAlert } from "@/lib/webAlert";

const COOL_OFF_PRESETS_HOURS = [0, 1, 24, 72] as const;

function formatCoolOff(hours: number): string {
  if (hours === 0) return "Off";
  if (hours < 24) return `${hours}h`;
  const days = Math.round(hours / 24);
  return days === 1 ? "1 day" : `${days} days`;
}

function formatCountdown(ms: number): string {
  if (ms <= 0) return "Ready to apply";
  const totalSec = Math.floor(ms / 1000);
  const days = Math.floor(totalSec / 86400);
  const hours = Math.floor((totalSec % 86400) / 3600);
  const mins = Math.floor((totalSec % 3600) / 60);
  if (days > 0) return `${days}d ${hours}h left`;
  if (hours > 0) return `${hours}h ${mins}m left`;
  return `${mins}m left`;
}

export default function CoolOffScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const settingsQ = useQuery({
    queryKey: ["security", "settings"],
    queryFn: getSecuritySettings,
  });
  const pendingQ = useQuery({
    queryKey: ["security", "pending-changes"],
    queryFn: listPendingSensitiveChanges,
  });

  const settings = settingsQ.data;
  const pending = pendingQ.data ?? [];

  const [draft, setDraft] = useState<SecuritySettings | null>(null);
  useEffect(() => {
    if (settings && !draft) setDraft(settings);
  }, [settings, draft]);

  const saveSettings = useMutation({
    mutationFn: (patch: Partial<SecuritySettings>) => updateSecuritySettings(patch),
    onSuccess: (s) => {
      setDraft(s);
      qc.invalidateQueries({ queryKey: ["security", "settings"] });
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't save", e?.message ?? "Try again."),
  });

  const cancel = useMutation({
    mutationFn: (id: number) => cancelSensitiveChange(id),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["security", "pending-changes"] }),
    onError: (e: { status?: number; message?: string }) => {
      if (e?.status === 410) {
        showAlert(
          "Too late",
          "The cool-off period has already elapsed. The change is now in effect.",
        );
      } else {
        showAlert("Couldn't cancel", e?.message ?? "Try again.");
      }
      qc.invalidateQueries({ queryKey: ["security", "pending-changes"] });
    },
  });

  // ── Stage-a-change form ────────────────────────────────
  const [kind, setKind] = useState<"email" | "password">("email");
  const [newEmail, setNewEmail] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [stageError, setStageError] = useState<string | null>(null);

  const stage = useMutation({
    mutationFn: () => {
      setStageError(null);
      if (kind === "email") {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail.trim())) {
          throw { message: "Enter a valid email address" };
        }
        return stageSensitiveChange({
          kind,
          new_email: newEmail.trim(),
          current_password: currentPassword || undefined,
        });
      }
      if (newPassword.length < 8) {
        throw { message: "Password must be at least 8 characters" };
      }
      if (!currentPassword) {
        throw { message: "Enter your current password" };
      }
      return stageSensitiveChange({
        kind,
        new_password: newPassword,
        current_password: currentPassword,
      });
    },
    onSuccess: () => {
      setNewEmail("");
      setNewPassword("");
      setCurrentPassword("");
      qc.invalidateQueries({ queryKey: ["security", "pending-changes"] });
    },
    onError: (e: { message?: string; status?: number }) => {
      if (e?.status === 409) {
        setStageError(
          "There's already a pending change of this kind — cancel it first.",
        );
      } else {
        setStageError(e?.message ?? "Couldn't stage that change.");
      }
    },
  });

  if (settingsQ.isLoading || !draft) {
    return (
      <View
        style={{
          flex: 1,
          backgroundColor: colors.background,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <Stack.Screen options={{ title: "Cooling-off period" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  const presets = COOL_OFF_PRESETS_HOURS;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Cooling-off period" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 18, paddingBottom: 40 }}>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          When you change your email or password, we hold the change for a
          while and email a one-tap cancel link to your old address.
          That gives you time to react if someone else triggered it.
          Trade-off: you'll wait the same amount of time before the new
          credentials work.
        </Text>

        {/* Pending changes */}
        {pendingQ.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : pending.length > 0 ? (
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
                gap: 10,
              },
            ]}
          >
            <Text style={[styles.sectionTitle, { color: colors.mutedForeground }]}>
              Pending changes
            </Text>
            {pending.map((p) => (
              <PendingRow
                key={p.id}
                item={p}
                colors={colors}
                onCancel={() => cancel.mutate(p.id)}
                disabled={cancel.isPending}
              />
            ))}
          </View>
        ) : null}

        {/* Settings */}
        <View
          style={[
            styles.card,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
              gap: 12,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: colors.mutedForeground }]}>
            Cool-off duration
          </Text>
          <View style={styles.segmented}>
            {presets.map((h) => {
              const active = draft.cool_off_hours === h;
              return (
                <Pressable
                  key={h}
                  onPress={() => {
                    const next = { ...draft, cool_off_hours: h };
                    setDraft(next);
                    saveSettings.mutate({ cool_off_hours: h });
                  }}
                  style={({ pressed }) => [
                    styles.segment,
                    {
                      borderColor: active ? colors.primary : colors.border,
                      backgroundColor: active
                        ? colors.primary + "1c"
                        : "transparent",
                      borderRadius: colors.radius,
                      opacity: pressed ? 0.7 : 1,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.segmentLabel,
                      {
                        color: active ? colors.primary : colors.foreground,
                      },
                    ]}
                  >
                    {formatCoolOff(h)}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          {draft.cool_off_hours === 0 ? (
            <Text style={[styles.warn, { color: colors.destructive }]}>
              With cool-off off, email and password changes apply right away
              and there's no cancel window. Not recommended.
            </Text>
          ) : null}

          <View style={styles.toggleRow}>
            <View style={{ flex: 1 }}>
              <Text style={[styles.toggleTitle, { color: colors.foreground }]}>
                Block new-device logins during cool-off
              </Text>
              <Text style={[styles.toggleBody, { color: colors.mutedForeground }]}>
                Devices we've never seen before are turned away until the
                pending change either takes effect or gets cancelled.
              </Text>
            </View>
            <Switch
              value={draft.block_new_devices_during_cool_off}
              onValueChange={(v) => {
                const next = { ...draft, block_new_devices_during_cool_off: v };
                setDraft(next);
                saveSettings.mutate({ block_new_devices_during_cool_off: v });
              }}
            />
          </View>
        </View>

        {/* Stage a new change */}
        <View
          style={[
            styles.card,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
              gap: 10,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: colors.mutedForeground }]}>
            Stage a sensitive change
          </Text>
          <View style={styles.segmented}>
            {(["email", "password"] as const).map((k) => {
              const active = kind === k;
              return (
                <Pressable
                  key={k}
                  onPress={() => {
                    setKind(k);
                    setStageError(null);
                  }}
                  style={({ pressed }) => [
                    styles.segment,
                    {
                      borderColor: active ? colors.primary : colors.border,
                      backgroundColor: active
                        ? colors.primary + "1c"
                        : "transparent",
                      borderRadius: colors.radius,
                      opacity: pressed ? 0.7 : 1,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.segmentLabel,
                      { color: active ? colors.primary : colors.foreground },
                    ]}
                  >
                    {k === "email" ? "New email" : "New password"}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {kind === "email" ? (
            <TextField
              label="New email address"
              placeholder="you@example.com"
              autoCapitalize="none"
              keyboardType="email-address"
              value={newEmail}
              onChangeText={setNewEmail}
            />
          ) : (
            <TextField
              label="New password"
              placeholder="At least 8 characters"
              secureTextEntry
              value={newPassword}
              onChangeText={setNewPassword}
            />
          )}
          <TextField
            label={kind === "password" ? "Current password" : "Current password (recommended)"}
            placeholder="••••••••"
            secureTextEntry
            value={currentPassword}
            onChangeText={setCurrentPassword}
          />
          {stageError ? (
            <Text style={[styles.error, { color: colors.destructive }]}>
              {stageError}
            </Text>
          ) : null}
          <Button
            label={
              draft.cool_off_hours > 0
                ? `Stage change (waits ${formatCoolOff(draft.cool_off_hours)})`
                : "Apply change"
            }
            onPress={() => stage.mutate()}
            loading={stage.isPending}
          />
        </View>
      </ScrollView>
    </View>
  );
}

type Colors = ReturnType<typeof useColors>;

function PendingRow({
  item,
  colors,
  onCancel,
  disabled,
}: {
  item: PendingSensitiveChange;
  colors: Colors;
  onCancel: () => void;
  disabled: boolean;
}) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 60_000);
    return () => clearInterval(t);
  }, []);
  const remaining = new Date(item.effective_at).getTime() - now;

  return (
    <View
      style={[
        styles.pendingRow,
        { borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
        <Feather
          name={item.kind === "email" ? "mail" : "lock"}
          size={16}
          color={colors.primary}
        />
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={[styles.pendingTitle, { color: colors.foreground }]}>
          {item.kind === "email"
            ? `New email → ${item.new_email_masked ?? "(hidden)"}`
            : "New password"}
        </Text>
        <Text style={[styles.pendingMeta, { color: colors.mutedForeground }]}>
          {formatCountdown(remaining)}
          {item.ip_address ? ` · from ${item.ip_address}` : ""}
        </Text>
      </View>
      <Pressable
        onPress={onCancel}
        disabled={disabled}
        hitSlop={8}
        style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1 })}
        accessibilityLabel="Cancel pending change"
      >
        <Text style={[styles.cancelLink, { color: colors.destructive }]}>
          Cancel
        </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  intro: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  card: { padding: 14, borderWidth: 1 },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  segmented: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  segment: { paddingHorizontal: 12, paddingVertical: 8, borderWidth: 1 },
  segmentLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  warn: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, lineHeight: 17 },
  error: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingTop: 4,
  },
  toggleTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  toggleBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
    marginTop: 2,
  },
  pendingRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 10,
    borderWidth: 1,
  },
  iconWrap: {
    width: 32,
    height: 32,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  pendingTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  pendingMeta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  cancelLink: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
  },
});
