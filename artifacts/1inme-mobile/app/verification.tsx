import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getProfileVerificationStatus,
  submitProfileVerification,
  type ProfileVerificationRequest,
  type TickType,
} from "@/lib/api/verification";
import { showAlert } from "@/lib/webAlert";

const STATUS_LABEL: Record<string, string> = {
  unverified: "Not Verified",
  pending: "Pending Review",
  verified: "Verified",
  pending_reverification: "Re-verification Pending",
};

export default function VerificationScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [showNew, setShowNew] = useState(false);
  const [selectedTickId, setSelectedTickId] = useState<number | null>(null);
  const [officialName, setOfficialName] = useState("");
  const [purpose, setPurpose] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  const q = useQuery({
    queryKey: ["profile-verification"],
    queryFn: getProfileVerificationStatus,
  });

  const resetForm = () => {
    setSelectedTickId(null);
    setOfficialName("");
    setPurpose("");
    setErrors({});
  };

  const submit = useMutation({
    mutationFn: () => {
      if (!selectedTickId) throw new Error("Choose a verification type first");
      return submitProfileVerification({
        tick_type_id: selectedTickId,
        official_name: officialName.trim(),
        purpose: purpose.trim(),
      });
    },
    onSuccess: () => {
      setShowNew(false);
      resetForm();
      qc.invalidateQueries({ queryKey: ["profile-verification"] });
    },
    onError: (e: any) => {
      if (e?.errors) {
        const flat: Record<string, string> = {};
        Object.entries(e.errors).forEach(([k, v]) => {
          flat[k] = Array.isArray(v) ? (v[0] as string) : String(v);
        });
        setErrors(flat);
      } else {
        showAlert("Could not submit", e?.message ?? "Unknown error");
      }
    },
  });

  const data = q.data;
  const requests = data?.requests ?? [];
  const tickTypes = data?.tick_types ?? [];
  const status = data?.status ?? "unverified";
  const canApply = status === "unverified";

  const statusColor = {
    unverified: colors.mutedForeground,
    pending: colors.warning,
    verified: colors.success,
    pending_reverification: colors.warning,
  }[status] ?? colors.mutedForeground;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Verification" }} />

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: 20, gap: 16 }}>
          {/* Status card */}
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
            <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center" }}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.name, { color: colors.foreground }]}>
                  {data?.verified_name ?? "Your Account"}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {data?.tick_type?.name ? `${data.tick_type.name} · ` : ""}
                  Profile Verification
                </Text>
              </View>
              <View style={[styles.badge, { backgroundColor: statusColor + "33" }]}>
                <Text style={[styles.badgeText, { color: statusColor }]}>
                  {STATUS_LABEL[status] ?? status}
                </Text>
              </View>
            </View>

            {data?.verified_at ? (
              <Text style={[styles.sub, { color: colors.mutedForeground, marginTop: 8 }]}>
                Verified since {new Date(data.verified_at).toLocaleDateString()}
              </Text>
            ) : null}
          </View>

          {/* Tick type catalog (for unverified users) */}
          {canApply && tickTypes.length > 0 ? (
            <View>
              <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>Tick Types</Text>
              <View style={{ gap: 8, marginTop: 8 }}>
                {tickTypes.map((t: TickType) => (
                  <View key={t.id} style={[styles.row, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
                    <View style={[styles.iconWrap, { backgroundColor: t.color + "22" }]}>
                      <Feather name="shield" size={18} color={t.color} />
                    </View>
                    <Text style={[styles.name, { color: colors.foreground }]}>{t.name}</Text>
                  </View>
                ))}
              </View>
            </View>
          ) : null}

          {/* Apply CTA */}
          {canApply ? (
            <Button label="Apply for Verification" onPress={() => setShowNew(true)} />
          ) : status === "pending" ? (
            <View style={[styles.notice, { backgroundColor: colors.warning + "18", borderColor: colors.warning + "44" }]}>
              <Text style={[styles.sub, { color: colors.warning }]}>
                Your request is under review. We'll notify you when it's processed.
              </Text>
            </View>
          ) : status === "pending_reverification" ? (
            <View style={[styles.notice, { backgroundColor: colors.warning + "18", borderColor: colors.warning + "44" }]}>
              <Text style={[styles.sub, { color: colors.warning }]}>
                Your name change is under review. Your tick remains visible in the meantime.
              </Text>
            </View>
          ) : null}

          {/* Request history */}
          {requests.length > 0 ? (
            <View>
              <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>Request History</Text>
              <View style={{ gap: 8, marginTop: 8 }}>
                {requests.map((r: ProfileVerificationRequest) => {
                  const sc = r.status === "approved" ? colors.success : r.status === "rejected" ? colors.destructive : colors.warning;
                  return (
                    <View key={r.id} style={[styles.row, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
                      <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                        <Feather name="award" size={18} color={colors.primary} />
                      </View>
                      <View style={{ flex: 1, gap: 2 }}>
                        <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                          {r.official_name}
                        </Text>
                        <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                          {r.kind === "reverification" ? "Re-verification" : "New request"}
                          {r.created_at ? ` · ${new Date(r.created_at).toLocaleDateString()}` : ""}
                        </Text>
                        {r.admin_notes && r.status !== "pending" ? (
                          <Text style={[styles.sub, { color: colors.mutedForeground, fontStyle: "italic" }]} numberOfLines={2}>
                            {r.admin_notes}
                          </Text>
                        ) : null}
                      </View>
                      <View style={[styles.badge, { backgroundColor: sc + "33" }]}>
                        <Text style={[styles.badgeText, { color: sc }]}>{r.status}</Text>
                      </View>
                    </View>
                  );
                })}
              </View>
            </View>
          ) : canApply ? (
            <EmptyState
              icon="award"
              title="Not yet verified"
              body="Apply for a colored verification tick on your creator profile."
              action={<Button label="Apply" onPress={() => setShowNew(true)} />}
            />
          ) : null}
        </ScrollView>
      )}

      {/* Apply modal */}
      <Modal visible={showNew} animationType="slide" transparent onRequestClose={() => setShowNew(false)}>
        <View style={styles.modalBackdrop}>
          <ScrollView
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>Apply for Verification</Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              Your profile name will be locked once approved.
            </Text>

            <Text style={[styles.fieldLabel, { color: colors.mutedForeground, marginTop: 8 }]}>Verification Type</Text>
            <View style={{ gap: 6 }}>
              {tickTypes.map((t: TickType) => {
                const active = selectedTickId === t.id;
                return (
                  <Pressable
                    key={t.id}
                    onPress={() => setSelectedTickId(t.id)}
                    style={({ pressed }) => [
                      styles.pickerRow,
                      {
                        borderColor: active ? t.color : colors.border,
                        backgroundColor: active ? t.color + "1c" : colors.card,
                        borderRadius: colors.radius,
                        opacity: pressed ? 0.7 : 1,
                      },
                    ]}
                  >
                    <Feather
                      name={active ? "check-circle" : "circle"}
                      size={16}
                      color={active ? t.color : colors.mutedForeground}
                    />
                    <Text style={[styles.name, { color: colors.foreground }]}>{t.name}</Text>
                  </Pressable>
                );
              })}
            </View>
            {errors.tick_type_id ? (
              <Text style={[styles.sub, { color: colors.destructive }]}>{errors.tick_type_id}</Text>
            ) : null}

            <TextField
              label="Official / legal name"
              value={officialName}
              onChangeText={setOfficialName}
              error={errors.official_name}
            />
            <TextField
              label="Why should your account be verified?"
              value={purpose}
              onChangeText={setPurpose}
              multiline
              numberOfLines={4}
              error={errors.purpose}
            />

            <View style={{ flexDirection: "row", gap: 8, marginTop: 4 }}>
              <Button
                label="Cancel"
                variant="outline"
                onPress={() => { setShowNew(false); resetForm(); }}
                style={{ flex: 1 }}
              />
              <Button
                label="Submit"
                onPress={() => submit.mutate()}
                loading={submit.isPending}
                disabled={!selectedTickId || !officialName.trim()}
                style={{ flex: 1 }}
              />
            </View>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { padding: 16, borderWidth: 1 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  notice: { padding: 14, borderRadius: 12, borderWidth: 1 },
  modalBackdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.5)", justifyContent: "flex-end" },
  modalCard: { padding: 20, gap: 14, borderTopWidth: 1, maxHeight: "92%" },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  pickerRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 12,
    borderWidth: 1,
  },
});
