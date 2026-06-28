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
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  Expense,
  createExpense,
  deleteExpense,
  listExpenses,
  updateExpense,
} from "@/lib/api/accounting";

type Draft = {
  vendor: string;
  description: string;
  spent_at: string;
  amount_major: string;
  tax_major: string;
  currency: string;
  notes: string;
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

const empty: Draft = {
  vendor: "",
  description: "",
  spent_at: today(),
  amount_major: "",
  tax_major: "",
  currency: "USD",
  notes: "",
};

export default function ExpensesScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Draft>(empty);

  const q = useQuery({
    queryKey: ["billing-expenses"],
    queryFn: () => listExpenses(1),
  });

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        vendor: draft.vendor || null,
        description: draft.description || null,
        spent_at: draft.spent_at,
        amount_minor: Math.round(parseFloat(draft.amount_major || "0") * 100),
        tax_minor: Math.round(parseFloat(draft.tax_major || "0") * 100),
        currency: draft.currency.slice(0, 3).toUpperCase(),
        notes: draft.notes || null,
      };
      return editId ? updateExpense(editId, payload) : createExpense(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-expenses"] });
      setOpen(false);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't save", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteExpense(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["billing-expenses"] }),
  });

  const openCreate = () => {
    setEditId(null);
    setDraft(empty);
    setOpen(true);
  };
  const openEdit = (e: Expense) => {
    setEditId(e.id);
    setDraft({
      vendor: e.vendor ?? "",
      description: e.description ?? "",
      spent_at: e.spent_at ?? today(),
      amount_major: String((e.amount_minor ?? 0) / 100),
      tax_major: String((e.tax_minor ?? 0) / 100),
      currency: e.currency ?? "USD",
      notes: e.notes ?? "",
    });
    setOpen(true);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Expenses",
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
        <FlatList<Expense>
          data={q.data?.items ?? []}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => openEdit(item)}
              onLongPress={() =>
                Alert.alert("Delete expense?", item.vendor ?? "Expense", [
                  { text: "Cancel", style: "cancel" },
                  { text: "Delete", style: "destructive", onPress: () => remove.mutate(item.id) },
                ])
              }
              style={[styles.row, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
            >
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.vendor || item.description || "Expense"}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {item.spent_at ?? ""}
                </Text>
              </View>
              <Text style={[styles.amount, { color: colors.foreground }]}>
                {item.currency ?? ""} {((item.amount_minor ?? 0) / 100).toFixed(2)}
              </Text>
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState icon="credit-card" title="No expenses" body="Log business costs here so your ledger shows true profit." />
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
              {editId ? "Edit expense" : "New expense"}
            </Text>
            <View style={{ width: 50 }} />
          </View>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 14 }}>
            {[
              ["Vendor", "vendor", false],
              ["Description", "description", false],
              ["Date (YYYY-MM-DD) *", "spent_at", false],
              ["Amount", "amount_major", false],
              ["Tax amount", "tax_major", false],
              ["Currency (3-letter)", "currency", false],
              ["Notes", "notes", true],
            ].map(([label, key, multiline]) => (
              <View key={key as string} style={{ gap: 6 }}>
                <Text style={[styles.label, { color: colors.mutedForeground }]}>{label as string}</Text>
                <TextInput
                  value={String(draft[key as keyof Draft] ?? "")}
                  onChangeText={(v) => setDraft((d) => ({ ...d, [key as keyof Draft]: v }))}
                  keyboardType={key === "amount_major" || key === "tax_major" ? "decimal-pad" : "default"}
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
            <Button
              label={editId ? "Save changes" : "Log expense"}
              onPress={() =>
                draft.spent_at.trim() && draft.amount_major.trim() && save.mutate()
              }
              loading={save.isPending}
              disabled={!draft.spent_at.trim() || !draft.amount_major.trim()}
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
});
