import { Feather } from "@expo/vector-icons";
import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
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
  BillingCompany,
  createCompany,
  deleteCompany,
  listCompanies,
  updateCompany,
} from "@/lib/api/accounting";

type Draft = {
  name: string;
  legal_name: string;
  email: string;
  phone: string;
  website: string;
  address_line1: string;
  city: string;
  state: string;
  postal_code: string;
  country: string;
  tax_id_label: string;
  tax_id_value: string;
  default_currency: string;
  invoice_prefix: string;
  notes: string;
  is_default: boolean;
};

const empty: Draft = {
  name: "",
  legal_name: "",
  email: "",
  phone: "",
  website: "",
  address_line1: "",
  city: "",
  state: "",
  postal_code: "",
  country: "",
  tax_id_label: "",
  tax_id_value: "",
  default_currency: "USD",
  invoice_prefix: "",
  notes: "",
  is_default: false,
};

function toDraft(c: BillingCompany): Draft {
  return {
    name: c.name ?? "",
    legal_name: c.legal_name ?? "",
    email: c.email ?? "",
    phone: c.phone ?? "",
    website: c.website ?? "",
    address_line1: c.address_line1 ?? "",
    city: c.city ?? "",
    state: c.state ?? "",
    postal_code: c.postal_code ?? "",
    country: c.country ?? "",
    tax_id_label: c.tax_id_label ?? "",
    tax_id_value: c.tax_id_value ?? "",
    default_currency: c.default_currency ?? "USD",
    invoice_prefix: c.invoice_prefix ?? "",
    notes: c.notes ?? "",
    is_default: !!c.is_default,
  };
}

export default function BillingCompaniesScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Draft>(empty);

  const q = useQuery({ queryKey: ["billing-companies"], queryFn: listCompanies });

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        ...draft,
        country: draft.country ? draft.country.slice(0, 2).toUpperCase() : undefined,
        default_currency: draft.default_currency
          ? draft.default_currency.slice(0, 3).toUpperCase()
          : undefined,
      };
      return editId ? updateCompany(editId, payload) : createCompany(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["billing-companies"] });
      setOpen(false);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't save", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteCompany(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["billing-companies"] }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't delete", e?.message ?? "Try again."),
  });

  const openCreate = () => {
    setEditId(null);
    setDraft(empty);
    setOpen(true);
  };
  const openEdit = (c: BillingCompany) => {
    setEditId(c.id);
    setDraft(toDraft(c));
    setOpen(true);
  };

  const field = (
    label: string,
    key: keyof Draft,
    opts: { keyboard?: "default" | "email-address" | "url"; multiline?: boolean } = {},
  ) => (
    <View style={{ gap: 6 }}>
      <Text style={[styles.label, { color: colors.mutedForeground }]}>{label}</Text>
      <TextInput
        value={String(draft[key] ?? "")}
        onChangeText={(v) => setDraft((d) => ({ ...d, [key]: v }))}
        keyboardType={opts.keyboard === "email-address" ? "email-address" : "default"}
        autoCapitalize={opts.keyboard ? "none" : "sentences"}
        multiline={opts.multiline}
        placeholderTextColor={colors.mutedForeground}
        style={[
          styles.input,
          {
            color: colors.foreground,
            backgroundColor: colors.card,
            borderColor: colors.border,
            borderRadius: colors.radius,
            minHeight: opts.multiline ? 80 : 48,
          },
        ]}
      />
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Billing companies",
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
        <FlatList<BillingCompany>
          data={q.data ?? []}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => openEdit(item)}
              onLongPress={() =>
                Alert.alert("Delete company?", item.name, [
                  { text: "Cancel", style: "cancel" },
                  {
                    text: "Delete",
                    style: "destructive",
                    onPress: () => remove.mutate(item.id),
                  },
                ])
              }
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]}>
                  {item.name}
                  {item.is_default ? "  ★" : ""}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {[item.email, item.default_currency].filter(Boolean).join(" · ") || "—"}
                </Text>
              </View>
              <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="briefcase"
              title="No billing companies"
              body="Add a company to put your branding, address and tax IDs on invoices."
            />
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
              {editId ? "Edit company" : "New company"}
            </Text>
            <View style={{ width: 50 }} />
          </View>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 14 }}>
            {field("Name *", "name")}
            {field("Legal name", "legal_name")}
            {field("Email", "email", { keyboard: "email-address" })}
            {field("Phone", "phone")}
            {field("Website", "website", { keyboard: "url" })}
            {field("Address", "address_line1")}
            {field("City", "city")}
            {field("State / region", "state")}
            {field("Postal code", "postal_code")}
            {field("Country (2-letter)", "country")}
            {field("Tax ID label", "tax_id_label")}
            {field("Tax ID value", "tax_id_value")}
            {field("Default currency (3-letter)", "default_currency")}
            {field("Invoice prefix", "invoice_prefix")}
            {field("Notes", "notes", { multiline: true })}
            <View style={styles.switchRow}>
              <Text style={[styles.label, { color: colors.foreground }]}>Default company</Text>
              <Switch
                value={draft.is_default}
                onValueChange={(v) => setDraft((d) => ({ ...d, is_default: v }))}
              />
            </View>
            <Button
              label={editId ? "Save changes" : "Create company"}
              onPress={() => draft.name.trim() && save.mutate()}
              loading={save.isPending}
              disabled={!draft.name.trim()}
            />

            {editId ? (
              <View style={{ gap: 10, marginTop: 4 }}>
                <Text style={[styles.label, { color: colors.mutedForeground }]}>
                  Outbound mail
                </Text>
                <View
                  style={[
                    styles.linkList,
                    { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                  ]}
                >
                  {(
                    [
                      {
                        icon: "send" as const,
                        label: "Email sending (SMTP)",
                        sub: "Send invoices & receipts from your own server",
                        to: `/billing/companies/${editId}/smtp`,
                      },
                      {
                        icon: "mail" as const,
                        label: "Client email templates",
                        sub: "Customise the invoice & receipt emails",
                        to: `/billing/companies/${editId}/emails`,
                      },
                    ]
                  ).map((link, i) => (
                    <Pressable
                      key={link.to}
                      onPress={() => {
                        setOpen(false);
                        router.push(link.to as never);
                      }}
                      style={({ pressed }) => [
                        styles.linkItem,
                        {
                          borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                          borderTopColor: colors.border,
                          opacity: pressed ? 0.7 : 1,
                        },
                      ]}
                    >
                      <Feather name={link.icon} size={18} color={colors.primary} />
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.name, { color: colors.foreground }]}>
                          {link.label}
                        </Text>
                        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                          {link.sub}
                        </Text>
                      </View>
                      <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
                    </Pressable>
                  ))}
                </View>
              </View>
            ) : null}
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  linkList: { borderWidth: 1, overflow: "hidden" },
  linkItem: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14 },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  label: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  input: { borderWidth: 1, paddingHorizontal: 14, paddingVertical: 10, fontFamily: "SpaceGrotesk_500Medium", fontSize: 15 },
  modalHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", padding: 16, borderBottomWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  switchRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
});
