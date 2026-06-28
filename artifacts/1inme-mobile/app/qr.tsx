import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import {
  createQrCode,
  deleteQrCode,
  generateQrArt,
  getQrArtAvailability,
  getQrCatalog,
  listQrCodes,
  type QrArtResult,
  type QrCode,
  type QrPreset,
} from "@/lib/api/qr";

const TYPES = [
  { value: "url", label: "URL", placeholder: "https://example.com" },
  { value: "text", label: "Text", placeholder: "Anything" },
  { value: "wifi", label: "Wi-Fi", placeholder: "MySSID" },
];

export default function QrScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const plan = usePlanFeatures();
  const designLocked = plan.isFeatureLocked("qr_customization");
  const [showNew, setShowNew] = useState(false);
  const [name, setName] = useState("");
  const [type, setType] = useState<string>("url");
  const [value, setValue] = useState("");
  const [preset, setPreset] = useState<QrPreset | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // AI Artistic QR (Task #2690) — prompt + style preset + generated artwork.
  const [artPrompt, setArtPrompt] = useState("");
  const [artStyle, setArtStyle] = useState<string | null>(null);
  const [art, setArt] = useState<QrArtResult | null>(null);
  const [artError, setArtError] = useState("");

  const resetForm = () => {
    setShowNew(false);
    setName("");
    setValue("");
    setPreset(null);
    setErrors({});
    setArtPrompt("");
    setArtStyle(null);
    setArt(null);
    setArtError("");
  };

  const payloadFor = () =>
    type === "url" ? { url: value } : type === "wifi" ? { ssid: value } : { text: value };

  const q = useQuery({ queryKey: ["qr-codes"], queryFn: listQrCodes });
  const catalog = useQuery({ queryKey: ["qr-catalog"], queryFn: getQrCatalog, staleTime: 5 * 60 * 1000 });
  const artAvail = useQuery({
    queryKey: ["qr-art-availability"],
    queryFn: getQrArtAvailability,
    enabled: showNew,
  });

  const generateArt = useMutation({
    mutationFn: () =>
      generateQrArt({
        prompt: artPrompt.trim(),
        style: artStyle,
        type,
        payload: payloadFor(),
      }),
    onSuccess: (r) => {
      setArt(r);
      setArtError("");
      if (typeof r.balance === "number") {
        artAvail.refetch();
      }
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      setArtError(e?.message ?? "Generation failed — please try again.");
      artAvail.refetch();
    },
  });

  const create = useMutation({
    mutationFn: () =>
      createQrCode({
        name: name.trim(),
        type,
        payload: payloadFor(),
        design: art
          ? {
              ...(preset?.design ?? {}),
              ai_art: {
                enabled: true,
                image_url: art.image_url,
                prompt: artPrompt.trim(),
                style: art.style,
                data: art.encoded,
                provider: "replicate",
              },
            }
          : preset?.design,
      }),
    onSuccess: () => {
      resetForm();
      qc.invalidateQueries({ queryKey: ["qr-codes"] });
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      if (e?.errors) {
        const flat: Record<string, string> = {};
        Object.entries(e.errors).forEach(([k, v]) => {
          flat[k] = Array.isArray(v) ? (v[0] as string) : String(v);
        });
        setErrors(flat);
      } else {
        Alert.alert("Could not create", e?.message ?? "Unknown error");
      }
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteQrCode(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["qr-codes"] }),
  });

  const confirmRemove = (item: QrCode) => {
    const go = () => remove.mutate(item.id);
    if (Platform.OS === "web") {
      if (confirm(`Delete QR “${item.name}”?`)) go();
    } else {
      Alert.alert("Delete QR code?", item.name, [
        { text: "Cancel", style: "cancel" },
        { text: "Delete", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "QR codes",
          headerRight: () => (
            <Pressable onPress={() => setShowNew(true)} hitSlop={8} style={{ paddingRight: 12 }}>
              <Feather name="plus" size={20} color={colors.primary} />
            </Pressable>
          ),
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<QrCode>
          data={q.data ?? []}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              {item.preview_url ? (
                <Image source={{ uri: item.preview_url }} style={styles.thumb} />
              ) : (
                <View style={[styles.thumb, { backgroundColor: colors.primary + "1c", alignItems: "center", justifyContent: "center" }]}>
                  <Feather name="grid" size={20} color={colors.primary} />
                </View>
              )}
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.type.toUpperCase()}
                </Text>
              </View>
              <Pressable onPress={() => confirmRemove(item)} hitSlop={6}>
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="grid"
              title="No QR codes yet"
              body="Generate scannable codes for any URL, Wi-Fi network or piece of text."
              action={<Button label="New QR" onPress={() => setShowNew(true)} />}
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}

      <Modal visible={showNew} animationType="slide" transparent onRequestClose={resetForm}>
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>New QR code</Text>
            <ScrollView
              style={{ maxHeight: 460 }}
              contentContainerStyle={{ gap: 14, paddingBottom: 4 }}
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
            >
            <TextField label="Name" value={name} onChangeText={setName} error={errors.name} />
            <View style={styles.segment}>
              {TYPES.map((t) => {
                const active = t.value === type;
                return (
                  <Pressable
                    key={t.value}
                    onPress={() => setType(t.value)}
                    style={[
                      styles.segmentItem,
                      {
                        backgroundColor: active ? colors.primary : colors.card,
                        borderColor: colors.border,
                        borderRadius: colors.radius - 4,
                      },
                    ]}
                  >
                    <Text
                      style={{
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        fontSize: 12,
                        color: active ? colors.primaryForeground : colors.mutedForeground,
                      }}
                    >
                      {t.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <TextField
              label={type === "url" ? "URL" : type === "wifi" ? "SSID" : "Text"}
              value={value}
              onChangeText={setValue}
              autoCapitalize="none"
              autoCorrect={false}
              placeholder={TYPES.find((t) => t.value === type)?.placeholder}
            />
            {(catalog.data?.presets?.length ?? 0) > 0 && (
              <View style={{ gap: 8 }}>
                <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                  <Text style={[styles.sub, { color: colors.mutedForeground }]}>TEMPLATE</Text>
                  {designLocked ? <UpgradeLockBadge /> : null}
                </View>
                {designLocked ? (
                  <Text style={[styles.lockHint, { color: colors.mutedForeground }]}>
                    Styled QR templates are a plan feature. Your QR is created
                    with the default look — upgrade to customize colors, dots and
                    frames.
                  </Text>
                ) : null}
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                  {catalog.data!.presets.map((p) => {
                    const active = !designLocked && preset?.id === p.id;
                    return (
                      <Pressable
                        key={p.id}
                        onPress={() =>
                          designLocked
                            ? showUpgradePrompt({
                                message:
                                  "Custom QR templates aren't available on your current plan. Upgrade to style your QR codes.",
                              })
                            : setPreset(preset?.id === p.id ? null : p)
                        }
                        style={[
                          styles.presetChip,
                          {
                            backgroundColor: active ? colors.primary : colors.card,
                            borderColor: active ? colors.primary : colors.border,
                            borderRadius: colors.radius - 4,
                            opacity: designLocked ? 0.55 : 1,
                          },
                        ]}
                      >
                        <Text
                          style={{
                            fontFamily: "SpaceGrotesk_600SemiBold",
                            fontSize: 12,
                            color: active ? colors.primaryForeground : colors.foreground,
                          }}
                        >
                          {p.name}
                        </Text>
                      </Pressable>
                    );
                  })}
                </ScrollView>
              </View>
            )}

            {/* AI Artistic QR — prompt-driven artwork woven into the code. */}
            <View style={{ gap: 8 }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Feather name="image" size={13} color={colors.mutedForeground} />
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>AI ARTISTIC</Text>
                {artAvail.data && !artAvail.data.allowed ? <UpgradeLockBadge /> : null}
              </View>

              {artAvail.isLoading ? (
                <ActivityIndicator color={colors.primary} style={{ alignSelf: "flex-start" }} />
              ) : !artAvail.data?.enabled ? (
                <Text style={[styles.lockHint, { color: colors.mutedForeground }]}>
                  AI Artistic QR isn’t available yet — an administrator needs to
                  connect an image provider.
                </Text>
              ) : !artAvail.data.allowed ? (
                <>
                  <Text style={[styles.lockHint, { color: colors.mutedForeground }]}>
                    Turn your QR into AI artwork. This is a plan feature
                    {artAvail.data.recommended_plan
                      ? ` — upgrade to ${artAvail.data.recommended_plan.name} to unlock it.`
                      : "."}
                  </Text>
                  <Button
                    label="Upgrade to unlock"
                    variant="outline"
                    onPress={() =>
                      showUpgradePrompt({
                        message:
                          "AI Artistic QR isn't included in your current plan. Upgrade to generate artwork-styled QR codes.",
                        hint: artAvail.data?.recommended_plan
                          ? {
                              planSlug: artAvail.data.recommended_plan.slug,
                              planName: artAvail.data.recommended_plan.name,
                              feature: "qr_art",
                            }
                          : { feature: "qr_art" },
                      })
                    }
                  />
                </>
              ) : (
                <>
                  <TextField
                    label="Describe the artwork"
                    value={artPrompt}
                    onChangeText={(t) => {
                      setArtPrompt(t);
                      setArtError("");
                    }}
                    placeholder="e.g. a neon cyberpunk city at night"
                    multiline
                  />
                  {artAvail.data.presets.length > 0 ? (
                    <ScrollView
                      horizontal
                      showsHorizontalScrollIndicator={false}
                      contentContainerStyle={{ gap: 8 }}
                    >
                      {artAvail.data.presets.map((p) => {
                        const active = artStyle === p.label;
                        return (
                          <Pressable
                            key={p.label}
                            onPress={() => {
                              if (active) {
                                setArtStyle(null);
                              } else {
                                setArtStyle(p.label);
                                setArtPrompt(p.prompt);
                              }
                              setArtError("");
                            }}
                            style={[
                              styles.presetChip,
                              {
                                backgroundColor: active ? colors.primary : colors.card,
                                borderColor: active ? colors.primary : colors.border,
                                borderRadius: colors.radius - 4,
                              },
                            ]}
                          >
                            <Text
                              style={{
                                fontFamily: "SpaceGrotesk_600SemiBold",
                                fontSize: 12,
                                color: active ? colors.primaryForeground : colors.foreground,
                              }}
                            >
                              {p.label}
                            </Text>
                          </Pressable>
                        );
                      })}
                    </ScrollView>
                  ) : null}

                  <View style={styles.balanceRow}>
                    <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                      Balance: {artAvail.data.balance} coins
                    </Text>
                    <Text style={[styles.meta, { color: colors.foreground }]}>
                      Cost: {artAvail.data.cost} coin{artAvail.data.cost === 1 ? "" : "s"}
                    </Text>
                  </View>

                  {art?.image_url ? (
                    <View style={{ gap: 8 }}>
                      <Image
                        source={{ uri: art.image_url }}
                        style={styles.artPreview}
                        resizeMode="contain"
                      />
                      <View style={[styles.caveat, { backgroundColor: colors.primary + "14", borderRadius: colors.radius - 4 }]}>
                        <Feather name="alert-triangle" size={13} color={colors.primary} />
                        <Text style={[styles.caveatText, { color: colors.foreground }]}>
                          Artistic blending can reduce scan reliability — test the
                          final image before sharing.
                        </Text>
                      </View>
                    </View>
                  ) : null}

                  {artError ? (
                    <Text style={[styles.lockHint, { color: colors.destructive }]}>{artError}</Text>
                  ) : null}

                  <Button
                    label={art ? "Regenerate artwork" : "Generate artwork"}
                    variant="outline"
                    loading={generateArt.isPending}
                    disabled={!value.trim() || !artPrompt.trim() || generateArt.isPending}
                    onPress={() => generateArt.mutate()}
                  />
                  {art ? (
                    <Pressable onPress={() => setArt(null)} hitSlop={6} style={{ alignSelf: "flex-start" }}>
                      <Text style={[styles.meta, { color: colors.mutedForeground }]}>Remove artwork</Text>
                    </Pressable>
                  ) : null}
                </>
              )}
            </View>
            </ScrollView>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button label="Cancel" variant="outline" onPress={resetForm} style={{ flex: 1 }} />
              <Button
                label="Create"
                onPress={() => create.mutate()}
                loading={create.isPending}
                disabled={!name.trim() || !value.trim()}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 12, borderWidth: 1 },
  thumb: { width: 48, height: 48, borderRadius: 8 },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, letterSpacing: 0.4 },
  modalBackdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.5)", justifyContent: "flex-end" },
  modalCard: { padding: 20, gap: 14, borderTopWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  segment: { flexDirection: "row", gap: 8 },
  segmentItem: {
    flex: 1,
    paddingVertical: 10,
    alignItems: "center",
    borderWidth: 1,
  },
  presetChip: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderWidth: 1,
  },
  lockHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 15,
  },
  balanceRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  meta: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
  },
  artPreview: {
    width: "100%",
    aspectRatio: 1,
    borderRadius: 12,
  },
  caveat: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    padding: 10,
  },
  caveatText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 15,
  },
});
