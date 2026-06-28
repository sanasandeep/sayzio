import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
  TaxRule,
  createTaxRule,
  deleteTaxRule,
  listTaxRules,
  updateTaxRule,
} from "@/lib/api/accounting";

type Draft = {
  name: string;
  rate_pct: string;
  inclusive: boolean;
  is_compound: boolean;
  is_default: boolean;
  is_active: boolean;
};

const empty: Draft = {
  name: "",
  rate_pct: "",
  inclusive: false,
  is_compound: false,
  is_default: false,
  is_active: true,
};

export default function TaxRulesScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Draft>(empty);

  const q = useQuery({ queryKey: ["billing-tax-rules"], queryFn: listTaxRules });

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        name: draft.name.trim(),
        rate_bps: Math.round(parseFloat(draft.rate_pct || "0") * 100),
        inclusive: draft.inclusive,
        is_compound: draft.is_compound,
        is_default: draft.is_default,
        is_active: draft.is_active,
      };
      return editId ? updateTaxRule(editId, payload) : createTaxRule(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-tax-rules"] });
      setOpen(false);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't save", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteTaxRule(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["billing-tax-rules"] }),
  });

  const openCreate = () => {
    setEditId(null);
    setDraft(empty);
    setOpen(true);
  };
  const openEdit = (r: TaxRule) => {
    setEditId(r.id);
    setDraft({
      name: r.name,
      rate_pct: String((r.rate_bps ?? 0) / 100),
      inclusive: !!r.inclusive,
      is_compound: !!r.is_compound,
      is_default: !!r.is_default,
      is_active: !!r.is_active,
    });
    setOpen(true);
  };

  const toggle = (label: string, key: keyof Draft) => (
    <View style={styles.switchRow}>
      <Text style={[styles.label, { color: colors.foreground }]}>{label}</Text>
      <Switch
        value={!!draft[key]}
        onValueChange={(v) => setDraft((d) => ({ ...d, [key]: v }))}
      />
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Tax rules",
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
        <FlatList<TaxRule>
          data={q.data ?? []}
          keyExtractor={(r) => String(r.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => openEdit(item)}
              onLongPress={() =>
                Alert.alert("Delete tax rule?", item.name, [
                  { text: "Cancel", style: "cancel" },
                  { text: "Delete", style: "destructive", onPress: () => remove.mutate(item.id) },
                ])
              }
              style={[styles.row, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
            >
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]}>
                  {item.name}
                  {item.is_default ? "  ★" : ""}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {(item.rate_bps / 100).toFixed(2)}%
                  {item.inclusive ? " · inclusive" : " · exclusive"}
                  {item.is_compound ? " · compound" : ""}
                  {item.is_active ? "" : " · inactive"}
                </Text>
              </View>
              <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState icon="percent" title="No tax rules" body="Add VAT, GST or sales-tax rates to apply on invoice lines." />
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
              {editId ? "Edit tax rule" : "New tax rule"}
            </Text>
            <View style={{ width: 50 }} />
          </View>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 14 }}>
            <View style={{ gap: 6 }}>
              <Text style={[styles.label, { color: colors.mutedForeground }]}>Name *</Text>
              <TextInput
                value={draft.name}
                onChangeText={(v) => setDraft((d) => ({ ...d, name: v }))}
                placeholder="e.g. VAT 20%"
                placeholderTextColor={colors.mutedForeground}
                style={[styles.input, { color: colors.foreground, backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
              />
            </View>
            <View style={{ gap: 6 }}>
              <Text style={[styles.label, { color: colors.mutedForeground }]}>Rate (%)</Text>
              <TextInput
                value={draft.rate_pct}
                onChangeText={(v) => setDraft((d) => ({ ...d, rate_pct: v }))}
                keyboardType="decimal-pad"
                placeholder="20"
                placeholderTextColor={colors.mutedForeground}
                style={[styles.input, { color: colors.foreground, backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
              />
            </View>
            {toggle("Tax inclusive", "inclusive")}
            {toggle("Compound tax", "is_compound")}
            {toggle("Default rule", "is_default")}
            {toggle("Active", "is_active")}
            <Button
              label={editId ? "Save changes" : "Create tax rule"}
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
  label: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  input: { borderWidth: 1, paddingHorizontal: 14, paddingVertical: 10, fontFamily: "SpaceGrotesk_500Medium", fontSize: 15, minHeight: 48 },
  modalHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", padding: 16, borderBottomWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  switchRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
});
