import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  Share,
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
  deleteInvoice,
  getInvoice,
  getInvoiceReceipt,
  markInvoicePaid,
  refundInvoice,
  sendInvoice,
} from "@/lib/api/invoices";

const statusTint = (
  colors: ReturnType<typeof useColors>,
): Record<string, string> => ({
  paid: colors.success,
  sent: "#0ea5e9",
  draft: "#7d9bff",
  overdue: colors.destructive,
  void: "#9ca3af",
});

function fmt(minor: number, currency: string | null): string {
  const major = (minor || 0) / 100;
  return `${currency ?? ""} ${major.toFixed(2)}`.trim();
}

export default function InvoiceDetailScreen() {
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [sendOpen, setSendOpen] = useState(false);
  const [recipient, setRecipient] = useState("");
  const [paidOpen, setPaidOpen] = useState(false);
  const [payMethod, setPayMethod] = useState("bank_transfer");
  const [payRef, setPayRef] = useState("");
  const [emailReceipt, setEmailReceipt] = useState(true);

  const q = useQuery({
    queryKey: ["billing-invoice", id],
    queryFn: () => getInvoice(id),
    enabled: Number.isFinite(id),
  });

  const send = useMutation({
    mutationFn: () =>
      sendInvoice(id, recipient.trim() ? { recipient_email: recipient.trim() } : {}),
    onSuccess: (res) => {
      setSendOpen(false);
      setRecipient("");
      qc.invalidateQueries({ queryKey: ["billing-invoice", id] });
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      Alert.alert(
        "Invoice sent",
        `Pay link emailed to ${res.invoice.number ?? `#${res.invoice.id}`}.\n\n${res.pay_url}`,
      );
    },
    onError: (e: { message?: string }) => {
      // A failed send writes a new failed email_logs row, so refetch the
      // invoice to surface the persistent "last send failed" banner right away.
      qc.invalidateQueries({ queryKey: ["billing-invoice", id] });
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      Alert.alert("Couldn't send invoice", e?.message ?? "Try again.");
    },
  });

  const remove = useMutation({
    mutationFn: () => deleteInvoice(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      router.back();
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't delete invoice", e?.message ?? "Try again."),
  });

  const markPaid = useMutation({
    mutationFn: () =>
      markInvoicePaid(id, {
        method: payMethod,
        reference: payRef.trim() || undefined,
        email_receipt: emailReceipt,
      }),
    onSuccess: () => {
      setPaidOpen(false);
      setPayRef("");
      qc.invalidateQueries({ queryKey: ["billing-invoice", id] });
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      Alert.alert("Marked as paid", "A receipt has been recorded for this invoice.");
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't mark paid", e?.message ?? "Try again."),
  });

  const refund = useMutation({
    mutationFn: (reason?: string) => refundInvoice(id, reason ? { reason } : {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-invoice", id] });
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      Alert.alert("Refunded", "This invoice has been marked as refunded.");
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't refund", e?.message ?? "Try again."),
  });

  const viewReceipt = useMutation({
    mutationFn: () => getInvoiceReceipt(id),
    onSuccess: (r) => {
      const body =
        [
          r.method ? `Method: ${r.method}` : null,
          r.gateway ? `Gateway: ${r.gateway}` : null,
          r.gateway_ref ? `Reference: ${r.gateway_ref}` : null,
          r.created_at ? `Date: ${r.created_at.slice(0, 10)}` : null,
        ]
          .filter(Boolean)
          .join("\n") || "Receipt recorded.";
      Alert.alert(
        `Receipt ${r.number ?? `#${r.id}`}`,
        body,
        [
          ...(r.pdf_url
            ? [{ text: "Download PDF", onPress: () => openUrl(r.pdf_url as string) }]
            : []),
          { text: "Close", style: "cancel" as const },
        ],
      );
    },
    onError: (e: { message?: string }) =>
      Alert.alert("No receipt", e?.message ?? "No receipt found for this invoice."),
  });

  const inv = q.data;
  const tint = statusTint(colors)[String(inv?.status ?? "").toLowerCase()] ?? colors.primary;
  const status = String(inv?.status ?? "").toLowerCase();
  const canManage = status !== "paid" && status !== "refunded";
  const isPaid = status === "paid";

  const openUrl = async (url: string) => {
    try {
      await WebBrowser.openBrowserAsync(url, {
        toolbarColor: colors.background,
        controlsColor: colors.primary,
      });
    } catch (e) {
      Alert.alert(
        "Couldn't open PDF",
        e instanceof Error ? e.message : "Try again later.",
      );
    }
  };

  const sharePayLink = async () => {
    if (!inv?.pay_url) return;
    try {
      await Share.share({
        message: `Pay invoice ${inv.number ?? `#${inv.id}`}: ${inv.pay_url}`,
        url: inv.pay_url,
      });
    } catch (e) {
      Alert.alert(
        "Couldn't share link",
        e instanceof Error ? e.message : "Try again later.",
      );
    }
  };

  const openPdf = () => {
    if (inv?.pdf_url) openUrl(inv.pdf_url);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: inv?.number ?? `Invoice #${id}`,
          headerRight: () =>
            inv && canManage ? (
              <Pressable
                onPress={() =>
                  Alert.alert(
                    "Delete invoice?",
                    "This draft invoice will be removed permanently.",
                    [
                      { text: "Cancel", style: "cancel" },
                      {
                        text: "Delete",
                        style: "destructive",
                        onPress: () => remove.mutate(),
                      },
                    ],
                  )
                }
                hitSlop={10}
                style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1, paddingHorizontal: 6 })}
              >
                <Feather name="trash-2" size={20} color={colors.mutedForeground} />
              </Pressable>
            ) : null,
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError || !inv ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load invoice"
          body={(q.error as { message?: string })?.message ?? "Try again."}
        />
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 40 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        >
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
              <View style={[styles.iconWrap, { backgroundColor: tint + "1c" }]}>
                <Feather name="file-text" size={20} color={tint} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {inv.number ?? `Invoice #${inv.id}`}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {(inv.status ?? "").toUpperCase()}
                  {inv.issued_at ? ` · issued ${inv.issued_at.slice(0, 10)}` : ""}
                </Text>
              </View>
              <Text style={[styles.amount, { color: colors.foreground }]}>
                {fmt(inv.grand_total_minor, inv.currency)}
              </Text>
            </View>
          </View>

          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.h2, { color: colors.foreground }]}>
              Line items
            </Text>
            {inv.lines.length === 0 ? (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                No line items recorded for this invoice.
              </Text>
            ) : (
              inv.lines.map((l) => (
                <View
                  key={l.id}
                  style={[styles.lineRow, { borderColor: colors.border }]}
                >
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={2}>
                      {l.description ?? "Line item"}
                    </Text>
                    <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                      {l.quantity} × {fmt(l.unit_minor, inv.currency)}
                    </Text>
                  </View>
                  <Text style={[styles.label, { color: colors.foreground }]}>
                    {fmt(l.amount_minor, inv.currency)}
                  </Text>
                </View>
              ))
            )}
          </View>

          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={styles.totalRow}>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>Subtotal</Text>
              <Text style={[styles.label, { color: colors.foreground }]}>
                {fmt(inv.subtotal_minor, inv.currency)}
              </Text>
            </View>
            <View style={styles.totalRow}>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>Tax</Text>
              <Text style={[styles.label, { color: colors.foreground }]}>
                {fmt(inv.tax_total_minor, inv.currency)}
              </Text>
            </View>
            <View style={styles.totalRow}>
              <Text style={[styles.label, { color: colors.foreground }]}>Total</Text>
              <Text style={[styles.amount, { color: colors.foreground }]}>
                {fmt(inv.grand_total_minor, inv.currency)}
              </Text>
            </View>
          </View>

          {inv.last_send_failed && status !== "paid" ? (
            <View
              style={[
                styles.card,
                {
                  backgroundColor: colors.destructive + "14",
                  borderColor: colors.destructive + "59",
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Feather name="alert-triangle" size={18} color={colors.destructive} />
                <Text style={[styles.label, { color: colors.destructive, flex: 1 }]}>
                  Last send failed — not delivered
                </Text>
              </View>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                The invoice email couldn't be delivered. Retry the send, or share
                the pay link manually.
              </Text>
              <View style={{ flexDirection: "row", gap: 8 }}>
                <View style={{ flex: 1 }}>
                  <Button label="Retry send" onPress={() => setSendOpen(true)} />
                </View>
                {inv.pay_url ? (
                  <View style={{ flex: 1 }}>
                    <Button
                      label="Share pay link"
                      variant="outline"
                      onPress={sharePayLink}
                    />
                  </View>
                ) : null}
              </View>
            </View>
          ) : null}

          {canManage ? (
            <Button
              label={inv.status === "draft" ? "Send invoice" : "Resend invoice"}
              onPress={() => setSendOpen(true)}
            />
          ) : null}
          {canManage ? (
            <Button
              label="Mark as paid"
              variant="secondary"
              onPress={() => setPaidOpen(true)}
            />
          ) : null}
          {isPaid ? (
            <Button
              label="View receipt"
              variant="outline"
              onPress={() => viewReceipt.mutate()}
              loading={viewReceipt.isPending}
            />
          ) : null}
          {isPaid ? (
            <Button
              label="Refund invoice"
              variant="ghost"
              onPress={() =>
                Alert.alert(
                  "Refund invoice?",
                  "This marks the invoice as refunded and records a refund receipt.",
                  [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Refund",
                      style: "destructive",
                      onPress: () => refund.mutate(undefined),
                    },
                  ],
                )
              }
            />
          ) : null}
          {inv.pdf_url ? (
            <Button label="Open PDF" onPress={openPdf} variant="outline" />
          ) : null}
        </ScrollView>
      )}

      <Modal
        visible={sendOpen}
        animationType="slide"
        transparent
        onRequestClose={() => setSendOpen(false)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Send invoice
            </Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              We'll email the hosted pay link. Leave blank to use the recipient
              already on the invoice.
            </Text>
            <TextInput
              value={recipient}
              onChangeText={setRecipient}
              placeholder="client@example.com"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              autoFocus
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 1 }}>
                <Button
                  label="Cancel"
                  variant="ghost"
                  onPress={() => {
                    setSendOpen(false);
                    setRecipient("");
                  }}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button
                  label="Send"
                  onPress={() => send.mutate()}
                  loading={send.isPending}
                />
              </View>
            </View>
          </View>
        </View>
      </Modal>

      <Modal
        visible={paidOpen}
        animationType="slide"
        transparent
        onRequestClose={() => setPaidOpen(false)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Mark as paid
            </Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              Record a manual payment for this invoice and generate a receipt.
            </Text>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
              {[
                ["bank_transfer", "Bank transfer"],
                ["cash", "Cash"],
                ["card", "Card"],
                ["other", "Other"],
              ].map(([value, label]) => (
                <Pressable
                  key={value}
                  onPress={() => setPayMethod(value)}
                  style={{
                    paddingHorizontal: 14,
                    paddingVertical: 8,
                    borderWidth: 1,
                    borderRadius: 999,
                    borderColor: payMethod === value ? colors.primary : colors.border,
                    backgroundColor:
                      payMethod === value ? colors.primary + "1c" : colors.background,
                  }}
                >
                  <Text
                    style={{
                      color: payMethod === value ? colors.primary : colors.mutedForeground,
                      fontFamily: "SpaceGrotesk_500Medium",
                      fontSize: 13,
                    }}
                  >
                    {label}
                  </Text>
                </Pressable>
              ))}
            </View>
            <TextInput
              value={payRef}
              onChangeText={setPayRef}
              placeholder="Reference (optional)"
              placeholderTextColor={colors.mutedForeground}
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "space-between",
              }}
            >
              <Text style={[styles.label, { color: colors.foreground }]}>
                Email receipt to client
              </Text>
              <Switch value={emailReceipt} onValueChange={setEmailReceipt} />
            </View>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 1 }}>
                <Button
                  label="Cancel"
                  variant="ghost"
                  onPress={() => setPaidOpen(false)}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button
                  label="Confirm"
                  onPress={() => markPaid.mutate()}
                  loading={markPaid.isPending}
                />
              </View>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { padding: 16, borderWidth: 1, gap: 10 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 17 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  amount: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  lineRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  totalRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    padding: 24,
  },
  modalCard: { padding: 20, gap: 12, borderWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  input: {
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
  },
});
