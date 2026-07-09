import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
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
import { listLinks, type Link } from "@/lib/api/links";
import {
  listVerifications,
  submitVerification,
  type VerificationCategory,
  type VerificationRequest,
} from "@/lib/api/verification";
import { showAlert } from "@/lib/webAlert";

const statusColorMap = (
  colors: ReturnType<typeof useColors>,
): Record<string, string> => ({
  pending: colors.warning,
  approved: colors.success,
  rejected: colors.destructive,
});

const CATEGORIES: { value: VerificationCategory; label: string }[] = [
  { value: "individual", label: "Individual" },
  { value: "creator", label: "Creator" },
  { value: "business", label: "Business" },
  { value: "org", label: "Organization" },
];

export default function VerificationScreen() {
  const colors = useColors();
  const STATUS_COLORS = statusColorMap(colors);
  const qc = useQueryClient();
  const [showNew, setShowNew] = useState(false);
  const [linkId, setLinkId] = useState<number | null>(null);
  const [category, setCategory] = useState<VerificationCategory>("individual");
  const [businessName, setBusinessName] = useState("");
  const [displayName, setDisplayName] = useState("");
  const [purpose, setPurpose] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  const q = useQuery({ queryKey: ["verifications"], queryFn: listVerifications });
  const linksQ = useQuery({
    queryKey: ["links", "for-verification"],
    queryFn: () => listLinks({ per_page: 100 }),
    enabled: showNew,
  });

  const resetForm = () => {
    setLinkId(null);
    setCategory("individual");
    setBusinessName("");
    setDisplayName("");
    setPurpose("");
    setErrors({});
  };

  const submit = useMutation({
    mutationFn: () => {
      if (!linkId) throw new Error("Pick a Link in Bio first");
      return submitVerification({
        link_id: linkId,
        category,
        business_name: businessName.trim() || null,
        display_name: displayName.trim() || null,
        purpose: purpose.trim() || null,
      });
    },
    onSuccess: () => {
      setShowNew(false);
      resetForm();
      qc.invalidateQueries({ queryKey: ["verifications"] });
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

  const links = linksQ.data?.items ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Verification" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<VerificationRequest>
          data={q.data ?? []}
          keyExtractor={(r) => String(r.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          ListHeaderComponent={
            <Text style={[styles.intro, { color: colors.mutedForeground }]}>
              Apply for the verified badge on a specific Link in Bio. We review each request manually.
            </Text>
          }
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="award" size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.business_name || item.display_name || `Request #${item.id}`}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.category}
                  {item.created_at ? ` • ${new Date(item.created_at).toLocaleDateString()}` : ""}
                </Text>
              </View>
              <View
                style={[
                  styles.badge,
                  { backgroundColor: (STATUS_COLORS[item.status] ?? colors.mutedForeground) + "33" },
                ]}
              >
                <Text style={[styles.badgeText, { color: STATUS_COLORS[item.status] ?? colors.mutedForeground }]}>
                  {item.status}
                </Text>
              </View>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="award"
              title="No verification requests"
              body="Submit a request to get the verified badge on one of your Link in Bio pages."
              action={<Button label="Apply" onPress={() => setShowNew(true)} />}
            />
          }
          ListFooterComponent={
            (q.data?.length ?? 0) > 0 ? (
              <Button label="Submit another request" variant="outline" onPress={() => setShowNew(true)} />
            ) : null
          }
        />
      )}

      <Modal visible={showNew} animationType="slide" transparent onRequestClose={() => setShowNew(false)}>
        <View style={styles.modalBackdrop}>
          <ScrollView
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>Apply for verification</Text>

            <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Link in Bio to verify</Text>
            {linksQ.isLoading ? (
              <ActivityIndicator color={colors.primary} />
            ) : links.length === 0 ? (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                Create a link first to request verification.
              </Text>
            ) : (
              <View style={{ gap: 6 }}>
                {links.map((l: Link) => {
                  const active = linkId === l.id;
                  return (
                    <Pressable
                      key={l.id}
                      onPress={() => setLinkId(l.id)}
                      style={({ pressed }) => [
                        styles.pickerRow,
                        {
                          borderColor: active ? colors.primary : colors.border,
                          backgroundColor: active ? colors.primary + "1c" : colors.card,
                          borderRadius: colors.radius,
                          opacity: pressed ? 0.7 : 1,
                        },
                      ]}
                    >
                      <Feather
                        name={active ? "check-circle" : "circle"}
                        size={16}
                        color={active ? colors.primary : colors.mutedForeground}
                      />
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                          {l.title || l.alias}
                        </Text>
                        <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                          /{l.alias}
                        </Text>
                      </View>
                    </Pressable>
                  );
                })}
              </View>
            )}
            {errors.link_id ? (
              <Text style={[styles.sub, { color: colors.destructive }]}>{errors.link_id}</Text>
            ) : null}

            <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Category</Text>
            <View style={styles.segmented}>
              {CATEGORIES.map((c) => {
                const active = category === c.value;
                return (
                  <Pressable
                    key={c.value}
                    onPress={() => setCategory(c.value)}
                    style={({ pressed }) => [
                      styles.segment,
                      {
                        borderColor: active ? colors.primary : colors.border,
                        backgroundColor: active ? colors.primary + "1c" : "transparent",
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
                      {c.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            {errors.category ? (
              <Text style={[styles.sub, { color: colors.destructive }]}>{errors.category}</Text>
            ) : null}

            <TextField
              label="Business / organization name"
              value={businessName}
              onChangeText={setBusinessName}
              error={errors.business_name}
            />
            <TextField
              label="Display name"
              value={displayName}
              onChangeText={setDisplayName}
              error={errors.display_name}
            />
            <TextField
              label="Why do you need verification?"
              value={purpose}
              onChangeText={setPurpose}
              multiline
              numberOfLines={4}
              error={errors.purpose}
            />

            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button
                label="Cancel"
                variant="outline"
                onPress={() => {
                  setShowNew(false);
                  resetForm();
                }}
                style={{ flex: 1 }}
              />
              <Button
                label="Submit"
                onPress={() => submit.mutate()}
                loading={submit.isPending}
                disabled={!linkId}
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
  intro: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 4,
  },
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
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
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
  segmented: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  segment: { paddingHorizontal: 12, paddingVertical: 8, borderWidth: 1 },
  segmentLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
});
