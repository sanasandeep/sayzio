import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as ImagePicker from "expo-image-picker";
import { Stack, useFocusEffect, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { DictationMic } from "@/components/DictationMic";
import { TextField } from "@/components/TextField";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import {
  createStoreCategory,
  createStoreProduct,
  deleteStoreCategory,
  deleteStoreProduct,
  getOwnerStore,
  saveOwnerStoreSettings,
  updateStoreCategory,
  updateStoreProduct,
  uploadStoreProductPhoto,
  type OwnerStore,
  type OwnerStoreCategory,
  type OwnerStoreProduct,
} from "@/lib/api/storeMenu";

function confirm(title: string, msg: string, onYes: () => void) {
  if (Platform.OS === "web") {
    if (typeof window !== "undefined" && window.confirm(`${title}\n\n${msg}`)) {
      onYes();
    }
    return;
  }
  Alert.alert(title, msg, [
    { text: "Cancel", style: "cancel" },
    { text: "Delete", style: "destructive", onPress: onYes },
  ]);
}

type CategoryDraft = {
  id: number | null;
  name: string;
  description: string;
};

type ProductDraft = {
  id: number | null;
  category_id: number;
  name: string;
  description: string;
  price: string;
  photo_url: string;
  is_out_of_stock: boolean;
};

export default function StoreMenuBuilderScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ id: string }>();
  const linkId = String(params.id ?? "");
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["store-owner-menu", linkId],
    queryFn: () => getOwnerStore(linkId),
    enabled: linkId.length > 0,
  });

  // Local settings state, hydrated from the server payload.
  const [mode, setMode] = useState<"display" | "order">("display");
  const [currency, setCurrency] = useState("USD");
  const [accent, setAccent] = useState("");
  const [whatsapp, setWhatsapp] = useState("");
  const [accepting, setAccepting] = useState(true);

  useEffect(() => {
    const m = q.data;
    if (!m) return;
    setMode(m.mode);
    setCurrency(m.currency);
    setAccent(m.accent_color ?? "");
    setWhatsapp(m.whatsapp_number ?? "");
    setAccepting(m.accepting_orders);
  }, [q.data]);

  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["store-owner-menu", linkId] });

  const settingsMut = useMutation({
    mutationFn: () =>
      saveOwnerStoreSettings(linkId, {
        mode,
        currency: currency.trim().toUpperCase() || "USD",
        accent_color: accent.trim() || null,
        whatsapp_number: whatsapp.trim() || null,
        accepting_orders: accepting,
      }),
    onSuccess: (menu) => qc.setQueryData(["store-owner-menu", linkId], menu),
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't save settings", e?.message ?? "Try again.");
    },
  });

  // ── Category modal ──
  const [catModal, setCatModal] = useState<CategoryDraft | null>(null);
  const catMut = useMutation({
    mutationFn: (d: CategoryDraft) =>
      d.id
        ? updateStoreCategory(linkId, d.id, {
            name: d.name.trim(),
            description: d.description.trim() || null,
          })
        : createStoreCategory(linkId, {
            name: d.name.trim(),
            description: d.description.trim() || null,
          }),
    onSuccess: () => {
      setCatModal(null);
      invalidate();
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't save category", e?.message ?? "Try again.");
    },
  });
  const delCatMut = useMutation({
    mutationFn: (id: number) => deleteStoreCategory(linkId, id),
    onSuccess: invalidate,
  });

  // ── Product modal ──
  const [productModal, setProductModal] = useState<ProductDraft | null>(null);
  const [photoUploading, setPhotoUploading] = useState(false);
  const productMut = useMutation({
    mutationFn: (d: ProductDraft) => {
      const payload = {
        category_id: d.category_id,
        name: d.name.trim(),
        description: d.description.trim() || null,
        price: d.price ? Number(d.price) : 0,
        photo_url: d.photo_url.trim() || null,
        is_out_of_stock: d.is_out_of_stock,
      };
      return d.id
        ? updateStoreProduct(linkId, d.id, payload)
        : createStoreProduct(linkId, payload);
    },
    onSuccess: () => {
      setProductModal(null);
      invalidate();
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't save product", e?.message ?? "Try again.");
    },
  });
  const delProductMut = useMutation({
    mutationFn: (id: number) => deleteStoreProduct(linkId, id),
    onSuccess: invalidate,
  });

  const pickPhoto = async () => {
    if (!productModal) return;
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Photos access needed",
        "Allow access to your photo library in Settings to add a product photo.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    setPhotoUploading(true);
    try {
      const url = await uploadStoreProductPhoto(linkId, {
        uri: a.uri,
        mime: a.mimeType ?? undefined,
        name: a.fileName ?? undefined,
      });
      setProductModal((prev) => (prev ? { ...prev, photo_url: url } : prev));
    } catch (e: any) {
      if (!handlePlanLockedError(e)) {
        Alert.alert("Upload failed", e?.message ?? "Try again.");
      }
    } finally {
      setPhotoUploading(false);
    }
  };

  // Voice turns started while this editor is open prefer the general
  // in-app tools; dictation works via the per-field mics regardless.
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("app");
      return () => setVoiceSurface(null);
    }, []),
  );

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit store" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (q.error || !q.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit store" }} />
        <Text style={{ color: colors.destructive }}>Couldn't load store.</Text>
      </View>
    );
  }

  const menu: OwnerStore = q.data;
  const cur = currency.trim().toUpperCase() || "USD";

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Edit store" }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }}>
        {/* Settings */}
        <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
          Store settings
        </Text>
        <View
          style={[
            styles.card,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            Mode
          </Text>
          <View
            style={[
              styles.segment,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            {(["display", "order"] as const).map((m) => {
              const on = mode === m;
              return (
                <Pressable
                  key={m}
                  onPress={() => setMode(m)}
                  style={[
                    styles.segmentItem,
                    on && { backgroundColor: colors.primary },
                  ]}
                >
                  <Text
                    style={{
                      color: on ? "#fff" : colors.mutedForeground,
                      fontWeight: "600",
                      fontSize: 13,
                      textTransform: "capitalize",
                    }}
                  >
                    {m === "order" ? "Order requests" : "Display only"}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <Text style={[styles.helper, { color: colors.mutedForeground }]}>
            {mode === "order"
              ? "Visitors can send order requests that land in your Requests dashboard. No payment is collected."
              : "Visitors browse the catalog only — no ordering."}
          </Text>

          <View style={{ height: 12 }} />
          <TextField
            label="Currency (3-letter code)"
            value={currency}
            onChangeText={setCurrency}
            autoCapitalize="characters"
            autoCorrect={false}
            maxLength={3}
          />
          <TextField
            label="Accent color (optional)"
            value={accent}
            onChangeText={setAccent}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="#3d6bff"
          />
          {mode === "order" ? (
            <>
              <TextField
                label="WhatsApp number (optional)"
                value={whatsapp}
                onChangeText={setWhatsapp}
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="phone-pad"
                placeholder="e.g. +1 555 123 4567"
              />
              <Text style={[styles.helper, { color: colors.mutedForeground }]}>
                Add your number with country code to let buyers send their
                request to your WhatsApp. Requests still appear on your
                dashboard either way.
              </Text>
              <View style={styles.soldRow}>
                <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                  Accepting requests
                </Text>
                <Switch
                  value={accepting}
                  onValueChange={setAccepting}
                  trackColor={{ true: colors.primary, false: colors.border }}
                />
              </View>
            </>
          ) : null}
          <Button
            label="Save settings"
            onPress={() => settingsMut.mutate()}
            loading={settingsMut.isPending}
          />
        </View>

        {/* Categories + products */}
        <View style={styles.headerRow}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Categories & products
          </Text>
          <Pressable
            onPress={() => setCatModal({ id: null, name: "", description: "" })}
            style={styles.addLink}
            hitSlop={8}
          >
            <Feather name="plus" size={16} color={colors.primary} />
            <Text style={{ color: colors.primary, fontWeight: "600" }}>
              Category
            </Text>
          </Pressable>
        </View>

        {menu.categories.length === 0 ? (
          <View
            style={[
              styles.empty,
              { borderColor: colors.border, backgroundColor: colors.card },
            ]}
          >
            <Feather name="shopping-bag" size={28} color={colors.mutedForeground} />
            <Text style={{ color: colors.mutedForeground, marginTop: 10 }}>
              No categories yet. Add one to start building your store.
            </Text>
          </View>
        ) : null}

        {menu.categories.map((cat: OwnerStoreCategory) => (
          <View
            key={cat.id}
            style={[
              styles.catCard,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.catHead}>
              <View style={{ flex: 1, paddingRight: 8 }}>
                <Text style={[styles.catName, { color: colors.foreground }]}>
                  {cat.name}
                </Text>
                {cat.description ? (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 12.5,
                      marginTop: 2,
                    }}
                  >
                    {cat.description}
                  </Text>
                ) : null}
              </View>
              <Pressable
                onPress={() =>
                  setCatModal({
                    id: cat.id,
                    name: cat.name,
                    description: cat.description ?? "",
                  })
                }
                hitSlop={8}
                style={styles.iconBtn}
              >
                <Feather name="edit-2" size={16} color={colors.mutedForeground} />
              </Pressable>
              <Pressable
                onPress={() =>
                  confirm(
                    "Delete category?",
                    "This removes the category and all its products.",
                    () => delCatMut.mutate(cat.id),
                  )
                }
                hitSlop={8}
                style={styles.iconBtn}
              >
                <Feather name="trash-2" size={16} color={colors.destructive} />
              </Pressable>
            </View>

            {cat.products.map((it: OwnerStoreProduct) => (
              <Pressable
                key={it.id}
                onPress={() =>
                  setProductModal({
                    id: it.id,
                    category_id: cat.id,
                    name: it.name,
                    description: it.description ?? "",
                    price: it.price != null ? String(it.price) : "",
                    photo_url: it.photo_url ?? "",
                    is_out_of_stock: it.is_out_of_stock,
                  })
                }
                style={[styles.itemRow, { borderTopColor: colors.border }]}
              >
                {it.photo_url ? (
                  <Image source={{ uri: it.photo_url }} style={styles.itemPhoto} />
                ) : (
                  <View
                    style={[
                      styles.itemPhoto,
                      styles.itemPhotoPlaceholder,
                      { backgroundColor: colors.background },
                    ]}
                  >
                    <Feather
                      name="image"
                      size={16}
                      color={colors.mutedForeground}
                    />
                  </View>
                )}
                <View style={{ flex: 1 }}>
                  <Text
                    style={{ color: colors.foreground, fontWeight: "600" }}
                    numberOfLines={1}
                  >
                    {it.name}
                    {it.is_out_of_stock ? "  · Out of stock" : ""}
                  </Text>
                  {it.description ? (
                    <Text
                      style={{ color: colors.mutedForeground, fontSize: 12 }}
                      numberOfLines={1}
                    >
                      {it.description}
                    </Text>
                  ) : null}
                </View>
                <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                  {cur} {Number(it.price ?? 0).toFixed(2)}
                </Text>
                <Pressable
                  onPress={() =>
                    confirm(
                      "Delete product?",
                      `Remove "${it.name}" from the store.`,
                      () => delProductMut.mutate(it.id),
                    )
                  }
                  hitSlop={8}
                  style={{ paddingLeft: 6 }}
                >
                  <Feather name="trash-2" size={15} color={colors.destructive} />
                </Pressable>
              </Pressable>
            ))}

            <Pressable
              onPress={() =>
                setProductModal({
                  id: null,
                  category_id: cat.id,
                  name: "",
                  description: "",
                  price: "",
                  photo_url: "",
                  is_out_of_stock: false,
                })
              }
              style={[styles.addItem, { borderTopColor: colors.border }]}
            >
              <Feather name="plus" size={15} color={colors.primary} />
              <Text style={{ color: colors.primary, fontWeight: "600" }}>
                Add product
              </Text>
            </Pressable>
          </View>
        ))}
      </ScrollView>

      {/* Category modal */}
      <Modal
        visible={catModal !== null}
        transparent
        animationType="slide"
        onRequestClose={() => setCatModal(null)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.modalWrap}
        >
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              {catModal?.id ? "Edit category" : "New category"}
            </Text>
            <TextField
              label="Name"
              value={catModal?.name ?? ""}
              onChangeText={(v) =>
                setCatModal((p) => (p ? { ...p, name: v } : p))
              }
              placeholder="Apparel"
              trailing={
                <DictationMic
                  onText={(t) =>
                    setCatModal((p) =>
                      p
                        ? { ...p, name: p.name ? p.name.trim() + " " + t : t }
                        : p,
                    )
                  }
                />
              }
            />
            <TextField
              label="Description (optional)"
              value={catModal?.description ?? ""}
              onChangeText={(v) =>
                setCatModal((p) => (p ? { ...p, description: v } : p))
              }
              multiline
              numberOfLines={2}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setCatModal((p) =>
                      p
                        ? {
                            ...p,
                            description: p.description
                              ? p.description.trim() + " " + t
                              : t,
                          }
                        : p,
                    )
                  }
                />
              }
            />
            <View style={styles.modalActions}>
              <Button
                label="Cancel"
                variant="ghost"
                onPress={() => setCatModal(null)}
                style={{ flex: 1 }}
              />
              <Button
                label="Save"
                onPress={() => catModal && catMut.mutate(catModal)}
                loading={catMut.isPending}
                disabled={!catModal?.name.trim()}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>

      {/* Product modal */}
      <Modal
        visible={productModal !== null}
        transparent
        animationType="slide"
        onRequestClose={() => setProductModal(null)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.modalWrap}
        >
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <ScrollView keyboardShouldPersistTaps="handled">
              <Text style={[styles.modalTitle, { color: colors.foreground }]}>
                {productModal?.id ? "Edit product" : "New product"}
              </Text>
              <TextField
                label="Name"
                value={productModal?.name ?? ""}
                onChangeText={(v) =>
                  setProductModal((p) => (p ? { ...p, name: v } : p))
                }
                placeholder="Cotton T-shirt"
                trailing={
                  <DictationMic
                    onText={(t) =>
                      setProductModal((p) =>
                        p
                          ? { ...p, name: p.name ? p.name.trim() + " " + t : t }
                          : p,
                      )
                    }
                  />
                }
              />
              <TextField
                label="Description (optional)"
                value={productModal?.description ?? ""}
                onChangeText={(v) =>
                  setProductModal((p) => (p ? { ...p, description: v } : p))
                }
                multiline
                numberOfLines={2}
                trailing={
                  <DictationMic
                    onText={(t) =>
                      setProductModal((p) =>
                        p
                          ? {
                              ...p,
                              description: p.description
                                ? p.description.trim() + " " + t
                                : t,
                            }
                          : p,
                      )
                    }
                  />
                }
              />
              <TextField
                label={`Price (${cur})`}
                value={productModal?.price ?? ""}
                onChangeText={(v) =>
                  setProductModal((p) =>
                    p ? { ...p, price: v.replace(/[^0-9.]/g, "") } : p,
                  )
                }
                keyboardType="decimal-pad"
                placeholder="0.00"
              />

              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                Photo
              </Text>
              {productModal?.photo_url ? (
                <Image
                  source={{ uri: productModal.photo_url }}
                  style={styles.modalPhoto}
                />
              ) : null}
              <View style={styles.photoBtns}>
                <Button
                  label={photoUploading ? "Uploading…" : "Upload photo"}
                  variant="outline"
                  onPress={pickPhoto}
                  loading={photoUploading}
                  style={{ flex: 1 }}
                />
                {productModal?.photo_url ? (
                  <Button
                    label="Remove"
                    variant="ghost"
                    onPress={() =>
                      setProductModal((p) => (p ? { ...p, photo_url: "" } : p))
                    }
                  />
                ) : null}
              </View>

              <View style={styles.soldRow}>
                <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                  Out of stock
                </Text>
                <Switch
                  value={productModal?.is_out_of_stock ?? false}
                  onValueChange={(v) =>
                    setProductModal((p) => (p ? { ...p, is_out_of_stock: v } : p))
                  }
                  trackColor={{ true: colors.primary, false: colors.border }}
                />
              </View>

              <View style={styles.modalActions}>
                <Button
                  label="Cancel"
                  variant="ghost"
                  onPress={() => setProductModal(null)}
                  style={{ flex: 1 }}
                />
                <Button
                  label="Save"
                  onPress={() => productModal && productMut.mutate(productModal)}
                  loading={productMut.isPending}
                  disabled={!productModal?.name.trim()}
                  style={{ flex: 1 }}
                />
              </View>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 50,
  },
  sectionLabel: {
    fontSize: 12,
    fontWeight: "700",
    textTransform: "uppercase",
    letterSpacing: 0.5,
    marginBottom: 10,
    marginTop: 16,
  },
  card: { borderWidth: 1, borderRadius: 16, padding: 16, marginBottom: 4 },
  fieldLabel: { fontSize: 12, fontWeight: "600", marginBottom: 6 },
  helper: { fontSize: 12, marginTop: 8 },
  segment: {
    flexDirection: "row",
    borderWidth: 1,
    borderRadius: 12,
    padding: 4,
    gap: 4,
  },
  segmentItem: {
    flex: 1,
    alignItems: "center",
    paddingVertical: 9,
    borderRadius: 8,
  },
  headerRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  addLink: { flexDirection: "row", alignItems: "center", gap: 4, marginTop: 16 },
  empty: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 28,
    alignItems: "center",
  },
  catCard: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
  },
  catHead: { flexDirection: "row", alignItems: "flex-start" },
  catName: { fontSize: 16, fontWeight: "700" },
  iconBtn: { padding: 6 },
  itemRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 10,
    marginTop: 6,
    borderTopWidth: 1,
  },
  itemPhoto: { width: 40, height: 40, borderRadius: 8 },
  itemPhotoPlaceholder: { alignItems: "center", justifyContent: "center" },
  addItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingTop: 10,
    marginTop: 4,
    borderTopWidth: 1,
  },
  modalWrap: {
    flex: 1,
    justifyContent: "flex-end",
    backgroundColor: "rgba(0,0,0,0.5)",
  },
  modalCard: {
    borderTopWidth: 1,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 20,
    paddingBottom: 32,
    maxHeight: "90%",
  },
  modalTitle: { fontSize: 18, fontWeight: "700", marginBottom: 14 },
  modalActions: { flexDirection: "row", gap: 10, marginTop: 8 },
  modalPhoto: {
    width: "100%",
    height: 160,
    borderRadius: 12,
    marginBottom: 10,
  },
  photoBtns: { flexDirection: "row", gap: 10, alignItems: "center" },
  soldRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginTop: 16,
  },
});
