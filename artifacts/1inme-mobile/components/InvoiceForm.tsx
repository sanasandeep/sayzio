import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import * as ImagePicker from "expo-image-picker";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { listCompanies, getCatalog } from "@/lib/api/accounting";
import { listVaultClients } from "@/lib/api/vault";
import { listContacts, contactPrimaryEmail, type Contact } from "@/lib/api/contacts";
import {
  createInvoice,
  createReceipt,
  updateInvoice,
  type InvoiceLineInput,
  type LetterheadInput,
} from "@/lib/api/invoices";

type DocType = "invoice" | "receipt";

export type LineDraft = {
  label: string;
  quantity: string;
  amount: string; // major (decimal) units, converted to minor on submit
  taxPct: string; // percent, converted to bps on submit
};

/** Prefill values for edit mode. */
export type InvoiceFormInitial = {
  currency?: string;
  contactId?: number | null;
  vaultClientId?: number | null;
  recipientLabel?: string | null;
  recipientEmail?: string;
  recipientName?: string;
  recipientAddress?: string;
  dueDate?: string;
  notes?: string;
  discount?: string;
  lines?: LineDraft[];
  letterheadUrl?: string | null;
  orientation?: "portrait" | "landscape";
};

const PAY_METHODS: [string, string][] = [
  ["manual", "Manual / other"],
  ["cash", "Cash"],
  ["bank_transfer", "Bank transfer"],
  ["card", "Card"],
];

export function emptyLine(): LineDraft {
  return { label: "", quantity: "1", amount: "", taxPct: "" };
}

function toMinor(major: string): number {
  const n = Number(major);
  return Number.isFinite(n) ? Math.round(n * 100) : 0;
}

function fmt(minor: number): string {
  return (minor / 100).toFixed(2);
}

type Props =
  | { mode: "create" }
  | { mode: "edit"; invoiceId: number; initial: InvoiceFormInitial };

export default function InvoiceForm(props: Props) {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const isEdit = props.mode === "edit";
  const initial = isEdit ? props.initial : undefined;

  // In edit mode only invoices are supported (receipts can't be edited).
  const [docType, setDocType] = useState<DocType>("invoice");

  // Recipient
  const [currency, setCurrency] = useState(initial?.currency ?? "USD");
  const [contactId, setContactId] = useState<number | null>(initial?.contactId ?? null);
  const [vaultClientId, setVaultClientId] = useState<number | null>(
    initial?.vaultClientId ?? null,
  );
  const [recipientLabel, setRecipientLabel] = useState<string | null>(
    initial?.recipientLabel ?? null,
  );
  const [recipientEmail, setRecipientEmail] = useState(initial?.recipientEmail ?? "");
  const [recipientName, setRecipientName] = useState(initial?.recipientName ?? "");
  const [recipientAddress, setRecipientAddress] = useState(initial?.recipientAddress ?? "");
  const [dueDate, setDueDate] = useState(initial?.dueDate ?? "");
  const [notes, setNotes] = useState(initial?.notes ?? "");

  // Receipt-only (create mode)
  const [companyId, setCompanyId] = useState<number | null>(null);
  const [payMethod, setPayMethod] = useState("manual");
  const [payRef, setPayRef] = useState("");

  // Letterhead
  const [letterhead, setLetterhead] = useState<LetterheadInput | null>(null);
  const [existingLetterheadUrl, setExistingLetterheadUrl] = useState<string | null>(
    initial?.letterheadUrl ?? null,
  );
  const [removeLetterhead, setRemoveLetterhead] = useState(false);
  const [orientation, setOrientation] = useState<"portrait" | "landscape">(
    initial?.orientation ?? "portrait",
  );

  // Line items
  const [lines, setLines] = useState<LineDraft[]>(
    initial?.lines && initial.lines.length > 0 ? initial.lines : [emptyLine()],
  );
  const [discount, setDiscount] = useState(initial?.discount ?? "");

  // Pickers
  const [pickerOpen, setPickerOpen] = useState<null | "contact" | "client" | "catalog" | "company">(
    null,
  );
  const [contactQuery, setContactQuery] = useState("");

  const companiesQ = useQuery({
    queryKey: ["billing-companies"],
    queryFn: listCompanies,
    enabled: !isEdit,
  });
  const clientsQ = useQuery({ queryKey: ["vault-clients"], queryFn: listVaultClients });
  const catalogQ = useQuery({ queryKey: ["billing-catalog"], queryFn: getCatalog });
  const contactsQ = useQuery({
    queryKey: ["contacts", contactQuery],
    queryFn: () => listContacts(contactQuery),
    enabled: pickerOpen === "contact",
  });

  const subtotalMinor = useMemo(
    () => lines.reduce((s, l) => s + toMinor(l.amount) * (Number(l.quantity) || 1), 0),
    [lines],
  );
  const discountMinor = toMinor(discount);
  const taxMinor = useMemo(() => {
    const base = subtotalMinor || 1;
    return lines.reduce((s, l) => {
      const lineNet = toMinor(l.amount) * (Number(l.quantity) || 1);
      const share = lineNet / base;
      const discounted = lineNet - discountMinor * share;
      const bps = Math.round((Number(l.taxPct) || 0) * 100);
      return s + Math.round((discounted * bps) / 10000);
    }, 0);
  }, [lines, subtotalMinor, discountMinor]);
  const grandMinor = Math.max(0, subtotalMinor - discountMinor) + taxMinor;

  const buildLineItems = (): InvoiceLineInput[] =>
    lines
      .filter((l) => l.label.trim() && toMinor(l.amount) > 0)
      .map((l) => {
        const bps = Math.round((Number(l.taxPct) || 0) * 100);
        const item: InvoiceLineInput = {
          label: l.label.trim(),
          amount_minor: toMinor(l.amount),
          quantity: Number(l.quantity) || 1,
        };
        if (bps > 0) item.tax_rate_bps = bps;
        return item;
      });

  const hasLetterhead = !!letterhead || (!!existingLetterheadUrl && !removeLetterhead);
  const letterheadPreviewUri = letterhead?.uri ?? (!removeLetterhead ? existingLetterheadUrl : null);

  const save = useMutation({
    mutationFn: async () => {
      const items = buildLineItems();

      if (isEdit) {
        const patch: Parameters<typeof updateInvoice>[1] = {
          line_items: items,
          discount_minor: discountMinor,
          notes_md: notes.trim() ? notes.trim() : null,
          due_date: dueDate.trim() ? dueDate.trim() : null,
          vault_client_id: vaultClientId,
          contact_id: contactId,
          recipient_email: recipientEmail.trim() || null,
          recipient_name: recipientName.trim() || null,
          recipient_address: recipientAddress.trim() || null,
        };
        if (letterhead) {
          patch.letterhead = letterhead;
          patch.letterhead_orientation = orientation;
        } else if (removeLetterhead) {
          patch.remove_letterhead = true;
        } else if (existingLetterheadUrl) {
          patch.letterhead_orientation = orientation;
        }
        return updateInvoice(props.invoiceId, patch);
      }

      const shared = {
        currency: currency.trim().toUpperCase() || undefined,
        billing_company_id: companyId ?? undefined,
        vault_client_id: vaultClientId ?? undefined,
        contact_id: contactId ?? undefined,
        recipient_email: recipientEmail.trim() || undefined,
        recipient_name: recipientName.trim() || undefined,
        recipient_address: recipientAddress.trim() || undefined,
        notes_md: notes.trim() || undefined,
        discount_minor: discountMinor > 0 ? discountMinor : undefined,
        letterhead_orientation: letterhead ? orientation : undefined,
        letterhead: letterhead ?? undefined,
      };
      if (docType === "receipt") {
        return createReceipt({
          ...shared,
          method: payMethod,
          reference: payRef.trim() || undefined,
          line_items: items,
        });
      }
      return createInvoice({
        ...shared,
        due_date: dueDate.trim() || undefined,
        line_items: items,
      });
    },
    onSuccess: (inv) => {
      qc.invalidateQueries({ queryKey: ["billing-invoices"] });
      if (isEdit) {
        qc.invalidateQueries({ queryKey: ["billing-invoice", props.invoiceId] });
        Alert.alert("Changes saved", `${inv.number ?? `#${inv.id}`} has been updated.`);
        router.back();
        return;
      }
      Alert.alert(
        docType === "receipt" ? "Receipt created" : "Invoice created",
        `${inv.number ?? `#${inv.id}`} is ready.`,
      );
      router.replace(`/invoices/${inv.id}` as never);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't save", e?.message ?? "Please check the details and try again."),
  });

  const canSubmit = () => {
    const items = buildLineItems();
    if (items.length === 0) return false;
    if (!contactId && !vaultClientId && !recipientEmail.trim() && !recipientName.trim()) {
      return false;
    }
    return true;
  };

  const submit = () => {
    if (!canSubmit()) {
      Alert.alert(
        "Missing details",
        "Add at least one line item with an amount, and pick a recipient (contact, client, or enter a name/email).",
      );
      return;
    }
    save.mutate();
  };

  const applyContact = (c: Contact) => {
    setContactId(c.id);
    setVaultClientId(null);
    setRecipientLabel(c.display_name);
    const email = contactPrimaryEmail(c);
    if (email) setRecipientEmail(email);
    if (!recipientName.trim()) setRecipientName(c.display_name);
    setPickerOpen(null);
  };

  const applyClient = (id: number, name: string, email: string | null) => {
    setVaultClientId(id);
    setContactId(null);
    setRecipientLabel(name);
    if (email) setRecipientEmail(email);
    if (!recipientName.trim()) setRecipientName(name);
    setPickerOpen(null);
  };

  const clearRecipient = () => {
    setContactId(null);
    setVaultClientId(null);
    setRecipientLabel(null);
  };

  const pickLetterhead = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Photos access needed",
        "Allow access to your photo library in Settings to attach a letterhead.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.9,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    setLetterhead({ uri: a.uri, mimeType: a.mimeType ?? undefined });
    setRemoveLetterhead(false);
  };

  const dropLetterhead = () => {
    setLetterhead(null);
    setExistingLetterheadUrl(null);
    setRemoveLetterhead(true);
  };

  const updateLine = (idx: number, patch: Partial<LineDraft>) =>
    setLines((prev) => prev.map((l, i) => (i === idx ? { ...l, ...patch } : l)));
  const addLine = () => setLines((prev) => [...prev, emptyLine()]);
  const removeLine = (idx: number) =>
    setLines((prev) => (prev.length > 1 ? prev.filter((_, i) => i !== idx) : prev));
  const addCatalogItem = (name: string, unitMinor: number) => {
    setLines((prev) => [
      ...prev.filter((l) => l.label.trim() || toMinor(l.amount) > 0),
      { label: name, quantity: "1", amount: (unitMinor / 100).toFixed(2), taxPct: "" },
    ]);
    setPickerOpen(null);
  };

  const inputStyle = [
    styles.input,
    {
      color: colors.foreground,
      backgroundColor: colors.background,
      borderColor: colors.border,
      borderRadius: colors.radius,
    },
  ];
  const cardStyle = [
    styles.card,
    { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
  ];

  const selectedCompany = companiesQ.data?.find((c) => c.id === companyId);

  const screenTitle = isEdit
    ? "Edit invoice"
    : docType === "receipt"
      ? "New receipt"
      : "New invoice";

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: screenTitle }} />
      <ScrollView
        contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 48 }}
        keyboardShouldPersistTaps="handled"
      >
        {/* Mode toggle (create only) */}
        {!isEdit ? (
          <>
            <View
              style={[styles.segment, { borderColor: colors.border, borderRadius: colors.radius }]}
            >
              {(["invoice", "receipt"] as DocType[]).map((m) => (
                <Pressable
                  key={m}
                  onPress={() => setDocType(m)}
                  style={[
                    styles.segmentBtn,
                    {
                      backgroundColor: docType === m ? colors.primary : "transparent",
                      borderRadius: colors.radius - 2,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: docType === m ? "#fff" : colors.mutedForeground,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 14,
                    }}
                  >
                    {m === "invoice" ? "Invoice" : "Receipt"}
                  </Text>
                </Pressable>
              ))}
            </View>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              {docType === "receipt"
                ? "Record a payment already collected — no pay link is sent."
                : "Create an invoice and send it for payment from the detail screen."}
            </Text>
          </>
        ) : (
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>
            Fix line items, discount, recipient, notes or letterhead, then save before sending.
          </Text>
        )}

        {/* Recipient */}
        <View style={cardStyle}>
          <Text style={[styles.h2, { color: colors.foreground }]}>Recipient</Text>

          <View style={{ flexDirection: "row", gap: 8 }}>
            <View style={{ flex: 1 }}>
              <Button
                label={contactId ? "Contact ✓" : "Pick contact"}
                variant={contactId ? "secondary" : "outline"}
                onPress={() => {
                  setContactQuery("");
                  setPickerOpen("contact");
                }}
              />
            </View>
            <View style={{ flex: 1 }}>
              <Button
                label={vaultClientId ? "Client ✓" : "Pick client"}
                variant={vaultClientId ? "secondary" : "outline"}
                onPress={() => setPickerOpen("client")}
              />
            </View>
          </View>
          {recipientLabel && (contactId || vaultClientId) ? (
            <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
              <Feather name="user-check" size={14} color={colors.primary} />
              <Text style={[styles.sub, { color: colors.foreground, flex: 1 }]} numberOfLines={1}>
                {recipientLabel}
              </Text>
              <Pressable onPress={clearRecipient} hitSlop={8}>
                <Feather name="x" size={16} color={colors.mutedForeground} />
              </Pressable>
            </View>
          ) : null}

          <TextInput
            value={recipientName}
            onChangeText={setRecipientName}
            placeholder="Recipient name"
            placeholderTextColor={colors.mutedForeground}
            style={inputStyle}
          />
          <TextInput
            value={recipientEmail}
            onChangeText={setRecipientEmail}
            placeholder="Recipient email"
            placeholderTextColor={colors.mutedForeground}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            style={inputStyle}
          />
          <TextInput
            value={recipientAddress}
            onChangeText={setRecipientAddress}
            placeholder="Recipient address (optional)"
            placeholderTextColor={colors.mutedForeground}
            multiline
            style={[...inputStyle, { minHeight: 60, textAlignVertical: "top" }]}
          />

          {/* Billing company + currency can only be set at creation time. */}
          {!isEdit ? (
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 1 }}>
                <Button
                  label={selectedCompany ? selectedCompany.name : "Billing company"}
                  variant="outline"
                  onPress={() => setPickerOpen("company")}
                />
              </View>
              <TextInput
                value={currency}
                onChangeText={(t) => setCurrency(t.toUpperCase())}
                placeholder="USD"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="characters"
                maxLength={3}
                style={[...inputStyle, { width: 90, textAlign: "center" }]}
              />
            </View>
          ) : null}

          {docType === "invoice" ? (
            <TextInput
              value={dueDate}
              onChangeText={setDueDate}
              placeholder="Due date (YYYY-MM-DD, optional)"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              style={inputStyle}
            />
          ) : (
            <>
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                {PAY_METHODS.map(([value, label]) => (
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
                placeholder="Payment reference (optional)"
                placeholderTextColor={colors.mutedForeground}
                style={inputStyle}
              />
            </>
          )}
        </View>

        {/* Letterhead */}
        <View style={cardStyle}>
          <Text style={[styles.h2, { color: colors.foreground }]}>Letterhead (optional)</Text>
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>
            Leave blank to use the billing company's default letterhead.
          </Text>
          {hasLetterhead && letterheadPreviewUri ? (
            <View style={{ gap: 10 }}>
              <Image
                source={{ uri: letterheadPreviewUri }}
                style={{
                  width: "100%",
                  height: 120,
                  borderRadius: colors.radius,
                  borderWidth: 1,
                  borderColor: colors.border,
                }}
                resizeMode="cover"
              />
              <View style={{ flexDirection: "row", gap: 8 }}>
                <View style={{ flex: 1 }}>
                  <Button label="Replace image" variant="outline" onPress={pickLetterhead} />
                </View>
                <View style={{ flex: 1 }}>
                  <Button label="Remove letterhead" variant="ghost" onPress={dropLetterhead} />
                </View>
              </View>
            </View>
          ) : (
            <Button label="Attach letterhead image" variant="outline" onPress={pickLetterhead} />
          )}
          {hasLetterhead ? (
            <View style={{ flexDirection: "row", gap: 8 }}>
              {(["portrait", "landscape"] as const).map((o) => (
                <Pressable
                  key={o}
                  onPress={() => setOrientation(o)}
                  style={{
                    flex: 1,
                    paddingVertical: 10,
                    borderWidth: 1,
                    borderRadius: colors.radius,
                    alignItems: "center",
                    borderColor: orientation === o ? colors.primary : colors.border,
                    backgroundColor: orientation === o ? colors.primary + "1c" : colors.background,
                  }}
                >
                  <Text
                    style={{
                      color: orientation === o ? colors.primary : colors.mutedForeground,
                      fontFamily: "SpaceGrotesk_500Medium",
                      fontSize: 13,
                    }}
                  >
                    {o === "portrait" ? "Portrait" : "Landscape"}
                  </Text>
                </Pressable>
              ))}
            </View>
          ) : null}
        </View>

        {/* Line items */}
        <View style={cardStyle}>
          <View
            style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}
          >
            <Text style={[styles.h2, { color: colors.foreground }]}>Line items</Text>
            <Pressable onPress={() => setPickerOpen("catalog")} hitSlop={8}>
              <Text
                style={{
                  color: colors.primary,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 13,
                }}
              >
                + From catalog
              </Text>
            </Pressable>
          </View>

          {lines.map((line, idx) => (
            <View
              key={idx}
              style={[styles.lineCard, { borderColor: colors.border, borderRadius: colors.radius }]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <TextInput
                  value={line.label}
                  onChangeText={(t) => updateLine(idx, { label: t })}
                  placeholder="Description"
                  placeholderTextColor={colors.mutedForeground}
                  style={[...inputStyle, { flex: 1 }]}
                />
                <Pressable onPress={() => removeLine(idx)} hitSlop={8}>
                  <Feather name="trash-2" size={18} color={colors.destructive} />
                </Pressable>
              </View>
              <View style={{ flexDirection: "row", gap: 8 }}>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.tiny, { color: colors.mutedForeground }]}>Qty</Text>
                  <TextInput
                    value={line.quantity}
                    onChangeText={(t) => updateLine(idx, { quantity: t.replace(/[^0-9]/g, "") })}
                    keyboardType="number-pad"
                    placeholder="1"
                    placeholderTextColor={colors.mutedForeground}
                    style={[...inputStyle, { textAlign: "right" }]}
                  />
                </View>
                <View style={{ flex: 1.4 }}>
                  <Text style={[styles.tiny, { color: colors.mutedForeground }]}>Amount</Text>
                  <TextInput
                    value={line.amount}
                    onChangeText={(t) => updateLine(idx, { amount: t.replace(/[^0-9.]/g, "") })}
                    keyboardType="decimal-pad"
                    placeholder="0.00"
                    placeholderTextColor={colors.mutedForeground}
                    style={[...inputStyle, { textAlign: "right" }]}
                  />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.tiny, { color: colors.mutedForeground }]}>Tax %</Text>
                  <TextInput
                    value={line.taxPct}
                    onChangeText={(t) => updateLine(idx, { taxPct: t.replace(/[^0-9.]/g, "") })}
                    keyboardType="decimal-pad"
                    placeholder="0"
                    placeholderTextColor={colors.mutedForeground}
                    style={[...inputStyle, { textAlign: "right" }]}
                  />
                </View>
              </View>
            </View>
          ))}
          <Button label="Add line" variant="outline" onPress={addLine} />

          <View style={{ gap: 6, marginTop: 4 }}>
            <View style={styles.totalRow}>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>Subtotal</Text>
              <Text style={[styles.label, { color: colors.foreground }]}>{fmt(subtotalMinor)}</Text>
            </View>
            <View style={[styles.totalRow, { alignItems: "center" }]}>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>Discount</Text>
              <TextInput
                value={discount}
                onChangeText={(t) => setDiscount(t.replace(/[^0-9.]/g, ""))}
                keyboardType="decimal-pad"
                placeholder="0.00"
                placeholderTextColor={colors.mutedForeground}
                style={[...inputStyle, { width: 110, textAlign: "right" }]}
              />
            </View>
            <View style={styles.totalRow}>
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>Tax</Text>
              <Text style={[styles.label, { color: colors.foreground }]}>{fmt(taxMinor)}</Text>
            </View>
            <View
              style={[
                styles.totalRow,
                {
                  borderTopWidth: StyleSheet.hairlineWidth,
                  borderColor: colors.border,
                  paddingTop: 8,
                },
              ]}
            >
              <Text style={[styles.label, { color: colors.foreground }]}>
                {docType === "receipt" ? "Total collected" : "Total"}
              </Text>
              <Text style={[styles.amount, { color: colors.foreground }]}>
                {currency} {fmt(grandMinor)}
              </Text>
            </View>
          </View>
        </View>

        <TextInput
          value={notes}
          onChangeText={setNotes}
          placeholder="Notes (optional)"
          placeholderTextColor={colors.mutedForeground}
          multiline
          style={[...inputStyle, { minHeight: 70, textAlignVertical: "top" }]}
        />

        <Button
          label={isEdit ? "Save changes" : docType === "receipt" ? "Create receipt" : "Create invoice"}
          onPress={submit}
          loading={save.isPending}
        />
      </ScrollView>

      {/* Contact picker */}
      <PickerModal
        visible={pickerOpen === "contact"}
        title="Pick a contact"
        onClose={() => setPickerOpen(null)}
        colors={colors}
      >
        <TextInput
          value={contactQuery}
          onChangeText={setContactQuery}
          placeholder="Search contacts"
          placeholderTextColor={colors.mutedForeground}
          autoCapitalize="none"
          style={inputStyle}
        />
        {contactsQ.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginVertical: 20 }} />
        ) : (contactsQ.data ?? []).length === 0 ? (
          <Text style={[styles.sub, { color: colors.mutedForeground, paddingVertical: 12 }]}>
            No contacts found.
          </Text>
        ) : (
          <ScrollView style={{ maxHeight: 320 }} keyboardShouldPersistTaps="handled">
            {(contactsQ.data ?? []).map((c) => (
              <Pressable
                key={c.id}
                onPress={() => applyContact(c)}
                style={[styles.pickRow, { borderColor: colors.border }]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={1}>
                    {c.display_name}
                  </Text>
                  <Text style={[styles.tiny, { color: colors.mutedForeground }]} numberOfLines={1}>
                    {contactPrimaryEmail(c) ?? c.organization ?? "—"}
                  </Text>
                </View>
                <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
              </Pressable>
            ))}
          </ScrollView>
        )}
      </PickerModal>

      {/* Client picker */}
      <PickerModal
        visible={pickerOpen === "client"}
        title="Pick a client"
        onClose={() => setPickerOpen(null)}
        colors={colors}
      >
        {clientsQ.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginVertical: 20 }} />
        ) : (clientsQ.data ?? []).length === 0 ? (
          <Text style={[styles.sub, { color: colors.mutedForeground, paddingVertical: 12 }]}>
            No saved clients yet.
          </Text>
        ) : (
          <ScrollView style={{ maxHeight: 320 }} keyboardShouldPersistTaps="handled">
            {(clientsQ.data ?? []).map((c) => (
              <Pressable
                key={c.id}
                onPress={() => applyClient(c.id, c.name, c.primary_email)}
                style={[styles.pickRow, { borderColor: colors.border }]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={1}>
                    {c.name}
                  </Text>
                  <Text style={[styles.tiny, { color: colors.mutedForeground }]} numberOfLines={1}>
                    {c.primary_email ?? c.company ?? "—"}
                  </Text>
                </View>
                <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
              </Pressable>
            ))}
          </ScrollView>
        )}
      </PickerModal>

      {/* Company picker (create only) */}
      <PickerModal
        visible={pickerOpen === "company"}
        title="Billing company"
        onClose={() => setPickerOpen(null)}
        colors={colors}
      >
        <ScrollView style={{ maxHeight: 320 }} keyboardShouldPersistTaps="handled">
          <Pressable
            onPress={() => {
              setCompanyId(null);
              setPickerOpen(null);
            }}
            style={[styles.pickRow, { borderColor: colors.border }]}
          >
            <Text style={[styles.label, { color: colors.foreground }]}>— Default —</Text>
          </Pressable>
          {(companiesQ.data ?? []).map((co) => (
            <Pressable
              key={co.id}
              onPress={() => {
                setCompanyId(co.id);
                setPickerOpen(null);
              }}
              style={[styles.pickRow, { borderColor: colors.border }]}
            >
              <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={1}>
                {co.name}
                {co.is_default ? "  (default)" : ""}
              </Text>
            </Pressable>
          ))}
        </ScrollView>
      </PickerModal>

      {/* Catalog picker */}
      <PickerModal
        visible={pickerOpen === "catalog"}
        title="Add from catalog"
        onClose={() => setPickerOpen(null)}
        colors={colors}
      >
        {catalogQ.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginVertical: 20 }} />
        ) : (catalogQ.data?.items ?? []).length === 0 ? (
          <Text style={[styles.sub, { color: colors.mutedForeground, paddingVertical: 12 }]}>
            No catalog items yet.
          </Text>
        ) : (
          <ScrollView style={{ maxHeight: 320 }} keyboardShouldPersistTaps="handled">
            {(catalogQ.data?.items ?? []).map((ci) => (
              <Pressable
                key={ci.id}
                onPress={() => addCatalogItem(ci.name, ci.unit_price_minor)}
                style={[styles.pickRow, { borderColor: colors.border }]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={1}>
                    {ci.name}
                  </Text>
                  {ci.description ? (
                    <Text style={[styles.tiny, { color: colors.mutedForeground }]} numberOfLines={1}>
                      {ci.description}
                    </Text>
                  ) : null}
                </View>
                <Text style={[styles.label, { color: colors.foreground }]}>
                  {fmt(ci.unit_price_minor)}
                </Text>
              </Pressable>
            ))}
          </ScrollView>
        )}
      </PickerModal>
    </View>
  );
}

function PickerModal({
  visible,
  title,
  onClose,
  colors,
  children,
}: {
  visible: boolean;
  title: string;
  onClose: () => void;
  colors: ReturnType<typeof useColors>;
  children: React.ReactNode;
}) {
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View
          style={[
            styles.modalCard,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <View
            style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>{title}</Text>
            <Pressable onPress={onClose} hitSlop={10}>
              <Feather name="x" size={22} color={colors.mutedForeground} />
            </Pressable>
          </View>
          {children}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  card: { padding: 16, borderWidth: 1, gap: 12 },
  segment: { flexDirection: "row", borderWidth: 1, padding: 3, gap: 3 },
  segmentBtn: { flex: 1, alignItems: "center", paddingVertical: 10 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  tiny: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, marginBottom: 4 },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  amount: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  lineCard: { borderWidth: 1, padding: 10, gap: 8 },
  totalRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  input: {
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    padding: 24,
  },
  modalCard: { padding: 20, gap: 12, borderWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  pickRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
});
