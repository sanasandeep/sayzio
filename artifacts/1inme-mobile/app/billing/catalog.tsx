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
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  CatalogItem,
  createItem,
  deleteItem,
  getCatalog,
  updateItem,
} from "@/lib/api/accounting";
import { showAlert } from "@/lib/webAlert";

type Draft = {
  name: string;
  description: string;
  price_major: string;
  currency: string;
  sku: string;
  unit_label: string;
  is_active: boolean;
};

const empty: Draft = {
  name: "",
  description: "",
  price_major: "",
  currency: "USD",
  sku: "",
  unit_label: "",
  is_active: true,
};

export default function CatalogScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Draft>(empty);

  const q = useQuery({ queryKey: ["billing-catalog"], queryFn: getCatalog });

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        name: draft.name.trim(),
        description: draft.description || null,
        unit_price_minor: Math.round(parseFloat(draft.price_major || "0") * 100),
        currency: draft.currency.slice(0, 3).toUpperCase(),
        sku: draft.sku || null,
        unit_label: draft.unit_label || null,
        is_active: draft.is_active,
      };
      return editId ? updateItem(editId, payload) : createItem(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-catalog"] });
      setOpen(false);
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't save", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteItem(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["billing-catalog"] }),
  });

  const openCreate = () => {
    setEditId(null);
    setDraft(empty);
    setOpen(true);
  };
  const openEdit = (i: CatalogItem) => {
    setEditId(i.id);
    setDraft({
      name: i.name,
      description: i.description ?? "",
      price_major: String((i.unit_price_minor ?? 0) / 100),
      currency: i.currency ?? "USD",
      sku: i.sku ?? "",
      unit_label: i.unit_label ?? "",
      is_active: !!i.is_active,
    });
    setOpen(true);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Item catalog",
          headerRight: () => (
            <Pressable onPress={openCreate} hitSlop={10}>
              <Feather name="plus" size={22} color={colors.primary} />
            </Pressable>
          ),
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<CatalogItem>
          data={q.data?.items ?? []}
          keyExtractor={(i) => String(i.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => openEdit(item)}
              onLongPress={() =>
                showAlert("Delete item?", item.name, [
                  { text: "Cancel", style: "cancel" },
                  { text: "Delete", style: "destructive", onPress: () => remove.mutate(item.id) },
                ])
              }
              style={[styles.row, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
            >
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]}>{item.name}</Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.sku ? `${item.sku} · ` : ""}
                  {item.is_active ? "active" : "inactive"}
                </Text>
              </View>
              <Text style={[styles.amount, { color: colors.foreground }]}>
                {item.currency ?? ""} {((item.unit_price_minor ?? 0) / 100).toFixed(2)}
              </Text>
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState icon="package" title="No catalog items" body="Save products and services here to add them to invoices with one tap." />
          }
        />
      )}

      <Modal visible={open} animationType="slide" presentationStyle="pageSheet">
        <View style={{ flex: 1, backgroundColor: colors.background }}>
          <View style={[styles.modalHead, { borderColor: colors.border }]}>
            <Pressable onPress={() => setOpen(false)} hitSlop={10}>
              <Text style={{ color: colors.mutedForeground, fontSize: 16 }}>Cancel</Text>
            </Pressable>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              {editId ? "Edit item" : "New item"}
            </Text>
            <View style={{ width: 50 }} />
          </View>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 14 }}>
            {[
              ["Name *", "name", false],
              ["Description", "description", true],
              ["Unit price", "price_major", false],
              ["Currency (3-letter)", "currency", false],
              ["SKU", "sku", false],
              ["Unit label (e.g. hour)", "unit_label", false],
            ].map(([label, key, multiline]) => (
              <View key={key as string} style={{ gap: 6 }}>
                <Text style={[styles.label, { color: colors.mutedForeground }]}>{label as string}</Text>
                <TextInput
                  value={String(draft[key as keyof Draft] ?? "")}
                  onChangeText={(v) => setDraft((d) => ({ ...d, [key as keyof Draft]: v }))}
                  keyboardType={key === "price_major" ? "decimal-pad" : "default"}
                  multiline={!!multiline}
                  placeholderTextColor={colors.mutedForeground}
                  style={[
                    styles.input,
                    {
                      color: colors.foreground,
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                      minHeight: multiline ? 80 : 48,
                    },
                  ]}
                />
              </View>
            ))}
            <View style={styles.switchRow}>
              <Text style={[styles.label, { color: colors.foreground }]}>Active</Text>
              <Switch
                value={draft.is_active}
                onValueChange={(v) => setDraft((d) => ({ ...d, is_active: v }))}
              />
            </View>
            <Button
              label={editId ? "Save changes" : "Create item"}
              onPress={() => draft.name.trim() && save.mutate()}
              loading={save.isPending}
              disabled={!draft.name.trim()}
            />
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  amount: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  label: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  input: { borderWidth: 1, paddingHorizontal: 14, paddingVertical: 10, fontFamily: "SpaceGrotesk_500Medium", fontSize: 15 },
  modalHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", padding: 16, borderBottomWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  switchRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
});
