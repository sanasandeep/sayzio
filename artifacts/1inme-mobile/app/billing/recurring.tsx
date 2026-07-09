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
  RecurringInvoice,
  createRecurring,
  deleteRecurring,
  listRecurring,
  runRecurring,
  updateRecurring,
} from "@/lib/api/accounting";
import { showAlert } from "@/lib/webAlert";

type LineDraft = { label: string; amount_major: string; quantity: string };
type Draft = {
  title: string;
  recipient_email: string;
  currency: string;
  interval: "weekly" | "monthly" | "quarterly" | "yearly";
  interval_count: string;
  start_date: string;
  auto_send: boolean;
  status: "active" | "paused";
  lines: LineDraft[];
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

const empty: Draft = {
  title: "",
  recipient_email: "",
  currency: "USD",
  interval: "monthly",
  interval_count: "1",
  start_date: today(),
  auto_send: false,
  status: "active",
  lines: [{ label: "", amount_major: "", quantity: "1" }],
};

const INTERVALS: Draft["interval"][] = ["weekly", "monthly", "quarterly", "yearly"];

export default function RecurringScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Draft>(empty);

  const q = useQuery({ queryKey: ["billing-recurring"], queryFn: listRecurring });

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        title: draft.title || null,
        recipient_email: draft.recipient_email || null,
        currency: draft.currency.slice(0, 3).toUpperCase(),
        interval: draft.interval,
        interval_count: parseInt(draft.interval_count || "1", 10),
        start_date: draft.start_date,
        auto_send: draft.auto_send,
        status: draft.status,
        line_items: draft.lines
          .filter((l) => l.label.trim())
          .map((l) => ({
            label: l.label.trim(),
            amount_minor: Math.round(parseFloat(l.amount_major || "0") * 100),
            quantity: parseInt(l.quantity || "1", 10),
          })),
      };
      return editId ? updateRecurring(editId, payload) : createRecurring(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-recurring"] });
      setOpen(false);
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't save", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteRecurring(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["billing-recurring"] }),
  });

  const runNow = useMutation({
    mutationFn: (id: number) => runRecurring(id),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["billing-recurring"] });
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      showAlert("Invoice generated", res.number);
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't generate", e?.message ?? "Try again."),
  });

  const openCreate = () => {
    setEditId(null);
    setDraft(empty);
    setOpen(true);
  };
  const openEdit = (t: RecurringInvoice) => {
    setEditId(t.id);
    setDraft({
      title: t.title ?? "",
      recipient_email: t.recipient_email ?? "",
      currency: t.currency ?? "USD",
      interval: (t.interval as Draft["interval"]) ?? "monthly",
      interval_count: String(t.interval_count ?? 1),
      start_date: t.start_date ?? today(),
      auto_send: !!t.auto_send,
      status: t.status === "paused" ? "paused" : "active",
      lines:
        (t.line_items ?? []).map((l) => ({
          label: l.label ?? "",
          amount_major: String((l.amount_minor ?? 0) / 100),
          quantity: String(l.quantity ?? 1),
        })) || [],
    });
    setOpen(true);
  };

  const setLine = (idx: number, key: keyof LineDraft, v: string) =>
    setDraft((d) => ({
      ...d,
      lines: d.lines.map((l, i) => (i === idx ? { ...l, [key]: v } : l)),
    }));

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Recurring invoices",
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
        <FlatList<RecurringInvoice>
          data={q.data ?? []}
          keyExtractor={(t) => String(t.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
              <Pressable onPress={() => openEdit(item)} style={{ gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]}>
                  {item.title || item.recipient_email || `Template #${item.id}`}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  every {item.interval_count > 1 ? `${item.interval_count} ` : ""}
                  {item.interval} · {item.status}
                  {item.next_run_date ? ` · next ${item.next_run_date}` : ""}
                </Text>
              </Pressable>
              <View style={{ flexDirection: "row", gap: 10, marginTop: 10 }}>
                <Button label="Run now" variant="outline" style={{ flex: 1 }} onPress={() => runNow.mutate(item.id)} loading={runNow.isPending} />
                <Button
                  label="Delete"
                  variant="ghost"
                  style={{ flex: 1 }}
                  onPress={() =>
                    showAlert("Delete template?", item.title ?? "Recurring invoice", [
                      { text: "Cancel", style: "cancel" },
                      { text: "Delete", style: "destructive", onPress: () => remove.mutate(item.id) },
                    ])
                  }
                />
              </View>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState icon="repeat" title="No recurring invoices" body="Set up a template to bill a client automatically on a schedule." />
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
              {editId ? "Edit template" : "New template"}
            </Text>
            <View style={{ width: 50 }} />
          </View>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 14 }}>
            {[
              ["Title", "title"],
              ["Recipient email", "recipient_email"],
              ["Currency (3-letter)", "currency"],
              ["Start date (YYYY-MM-DD) *", "start_date"],
              ["Interval count", "interval_count"],
            ].map(([label, key]) => (
              <View key={key} style={{ gap: 6 }}>
                <Text style={[styles.label, { color: colors.mutedForeground }]}>{label}</Text>
                <TextInput
                  value={String(draft[key as keyof Draft] ?? "")}
                  onChangeText={(v) => setDraft((d) => ({ ...d, [key as keyof Draft]: v }))}
                  keyboardType={key === "interval_count" ? "number-pad" : key === "recipient_email" ? "email-address" : "default"}
                  autoCapitalize={key === "recipient_email" ? "none" : "sentences"}
                  placeholderTextColor={colors.mutedForeground}
                  style={[styles.input, { color: colors.foreground, backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
                />
              </View>
            ))}
            <Text style={[styles.label, { color: colors.mutedForeground }]}>Interval</Text>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
              {INTERVALS.map((iv) => (
                <Pressable
                  key={iv}
                  onPress={() => setDraft((d) => ({ ...d, interval: iv }))}
                  style={[
                    styles.chip,
                    {
                      borderColor: draft.interval === iv ? colors.primary : colors.border,
                      backgroundColor: draft.interval === iv ? colors.primary + "1c" : colors.card,
                    },
                  ]}
                >
                  <Text style={{ color: draft.interval === iv ? colors.primary : colors.mutedForeground, fontFamily: "SpaceGrotesk_500Medium" }}>
                    {iv}
                  </Text>
                </Pressable>
              ))}
            </View>

            <Text style={[styles.label, { color: colors.foreground, marginTop: 8 }]}>Line items</Text>
            {draft.lines.map((l, idx) => (
              <View key={idx} style={[styles.lineCard, { borderColor: colors.border, borderRadius: colors.radius }]}>
                <TextInput
                  value={l.label}
                  onChangeText={(v) => setLine(idx, "label", v)}
                  placeholder="Description"
                  placeholderTextColor={colors.mutedForeground}
                  style={[styles.input, { color: colors.foreground, backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
                />
                <View style={{ flexDirection: "row", gap: 8 }}>
                  <TextInput
                    value={l.amount_major}
                    onChangeText={(v) => setLine(idx, "amount_major", v)}
                    placeholder="Unit price"
                    keyboardType="decimal-pad"
                    placeholderTextColor={colors.mutedForeground}
                    style={[styles.input, { flex: 2, color: colors.foreground, backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
                  />
                  <TextInput
                    value={l.quantity}
                    onChangeText={(v) => setLine(idx, "quantity", v)}
                    placeholder="Qty"
                    keyboardType="number-pad"
                    placeholderTextColor={colors.mutedForeground}
                    style={[styles.input, { flex: 1, color: colors.foreground, backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
                  />
                </View>
                {draft.lines.length > 1 && (
                  <Pressable onPress={() => setDraft((d) => ({ ...d, lines: d.lines.filter((_, i) => i !== idx) }))}>
                    <Text style={{ color: colors.destructive, fontFamily: "SpaceGrotesk_500Medium" }}>Remove line</Text>
                  </Pressable>
                )}
              </View>
            ))}
            <Button
              label="Add line"
              variant="outline"
              onPress={() => setDraft((d) => ({ ...d, lines: [...d.lines, { label: "", amount_major: "", quantity: "1" }] }))}
            />

            <View style={styles.switchRow}>
              <Text style={[styles.label, { color: colors.foreground }]}>Auto-send each cycle</Text>
              <Switch value={draft.auto_send} onValueChange={(v) => setDraft((d) => ({ ...d, auto_send: v }))} />
            </View>
            <View style={styles.switchRow}>
              <Text style={[styles.label, { color: colors.foreground }]}>Active</Text>
              <Switch
                value={draft.status === "active"}
                onValueChange={(v) => setDraft((d) => ({ ...d, status: v ? "active" : "paused" }))}
              />
            </View>

            <Button
              label={editId ? "Save changes" : "Create template"}
              onPress={() =>
                draft.start_date.trim() &&
                draft.lines.some((l) => l.label.trim()) &&
                save.mutate()
              }
              loading={save.isPending}
              disabled={!draft.start_date.trim() || !draft.lines.some((l) => l.label.trim())}
            />
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { padding: 14, borderWidth: 1 },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  label: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  input: { borderWidth: 1, paddingHorizontal: 14, paddingVertical: 10, fontFamily: "SpaceGrotesk_500Medium", fontSize: 15, minHeight: 48 },
  modalHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", padding: 16, borderBottomWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  switchRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderWidth: 1, borderRadius: 999 },
  lineCard: { padding: 12, borderWidth: 1, gap: 8 },
});
