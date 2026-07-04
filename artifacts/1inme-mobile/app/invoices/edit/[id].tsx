import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import InvoiceForm, { type InvoiceFormInitial, type LineDraft } from "@/components/InvoiceForm";
import { useColors } from "@/hooks/useColors";
import { getInvoice, type InvoiceDetail } from "@/lib/api/invoices";

function buildInitial(inv: InvoiceDetail): InvoiceFormInitial {
  const lines: LineDraft[] = (inv.lines ?? []).map((l) => ({
    label: l.description ?? "",
    quantity: String(l.quantity ?? 1),
    amount: (l.unit_minor / 100).toFixed(2),
    taxPct: l.tax_rate_bps ? String(l.tax_rate_bps / 100) : "",
  }));
  const hasRecipientRef = !!inv.contact_id || !!inv.vault_client_id;
  return {
    currency: inv.currency ?? "USD",
    contactId: inv.contact_id ?? null,
    vaultClientId: inv.vault_client_id ?? null,
    recipientLabel: hasRecipientRef ? (inv.recipient_name ?? "Selected recipient") : null,
    recipientEmail: inv.recipient_email ?? "",
    recipientName: inv.recipient_name ?? "",
    recipientAddress: inv.recipient_address ?? "",
    dueDate: inv.due_at ? inv.due_at.slice(0, 10) : "",
    notes: inv.notes_md ?? "",
    discount: inv.discount_minor ? (inv.discount_minor / 100).toFixed(2) : "",
    lines,
    letterheadUrl: inv.letterhead_url ?? null,
    orientation: inv.letterhead_orientation ?? "portrait",
  };
}

export default function EditInvoiceScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);

  const q = useQuery({
    queryKey: ["billing-invoice", id],
    queryFn: () => getInvoice(id),
    enabled: Number.isFinite(id),
  });

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit invoice" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !q.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit invoice" }} />
        <Text style={[styles.msg, { color: colors.mutedForeground }]}>
          Couldn't load this invoice. Pull back and try again.
        </Text>
      </View>
    );
  }

  return <InvoiceForm mode="edit" invoiceId={id} initial={buildInitial(q.data)} />;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  msg: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, textAlign: "center" },
});
