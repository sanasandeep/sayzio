import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  addProtectedAccount,
  listProtectedAccounts,
  removeProtectedAccount,
  type ProtectedAccount,
  type ProtectedAccountsList,
} from "@/lib/api/admin";

// Mobile parity for the web back-office "Protected accounts" page: the
// canonical never-delete/suspend list. Staff with `users.view` may read it;
// only a super-admin (server-enforced) may add or remove entries. The
// hard-locked seeds (super-admin + demo) can never be removed.

export default function ProtectedAccountsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [email, setEmail] = useState("");
  const [label, setLabel] = useState("");

  const query = useQuery({
    queryKey: ["admin-protected-accounts"],
    queryFn: listProtectedAccounts,
  });

  const apply = (list: ProtectedAccountsList) =>
    qc.setQueryData(["admin-protected-accounts"], list);

  const add = useMutation({
    mutationFn: () => addProtectedAccount(email.trim(), label),
    onSuccess: (list) => {
      apply(list);
      setEmail("");
      setLabel("");
    },
    onError: (e: any) =>
      Alert.alert("Couldn't add account", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => removeProtectedAccount(id),
    onSuccess: apply,
    onError: (e: any) =>
      Alert.alert("Couldn't remove account", e?.message ?? "Try again."),
  });

  const data = query.data;
  const canManage = !!data?.can_manage;
  const accounts = data?.accounts ?? [];

  const emailValid = /\S+@\S+\.\S+/.test(email.trim());

  const confirmRemove = (account: ProtectedAccount) =>
    Alert.alert(
      "Remove protection?",
      `${account.email} will no longer be protected from deletion or suspension.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Remove",
          style: "destructive",
          onPress: () => remove.mutate(account.id),
        },
      ],
    );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Protected accounts", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You don't have permission to view protected accounts."
                : "Couldn't load protected accounts."}
            </Text>
          </View>
        ) : (
          <>
            {/* Intro */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.head}>
                <Feather name="lock" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Never deleted or suspended
                </Text>
              </View>
              <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                These accounts are protected everywhere — the server refuses to
                delete or suspend them no matter which surface asks.
                {canManage
                  ? " As a super-admin you can add or remove entries below."
                  : " Only a super-admin can change this list."}
              </Text>
            </View>

            {/* Add form (super-admin only) */}
            {canManage ? (
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                  Add an account
                </Text>
                <View style={{ gap: 10, marginTop: 10 }}>
                  <TextField
                    label="Email"
                    placeholder="person@example.com"
                    autoCapitalize="none"
                    autoCorrect={false}
                    keyboardType="email-address"
                    value={email}
                    onChangeText={setEmail}
                  />
                  <TextField
                    label="Label (optional)"
                    placeholder="e.g. Founder account"
                    value={label}
                    onChangeText={setLabel}
                  />
                  <Button
                    label="Add to protected list"
                    onPress={() => add.mutate()}
                    loading={add.isPending}
                    disabled={!emailValid}
                  />
                </View>
              </View>
            ) : null}

            {/* List */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Protected accounts ({accounts.length})
              </Text>
              {accounts.length === 0 ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 8 }}>
                  No accounts are protected yet.
                </Text>
              ) : (
                accounts.map((a, i) => (
                  <View
                    key={a.id}
                    style={[
                      styles.row,
                      {
                        borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                        borderTopColor: colors.border,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text numberOfLines={1} style={{ color: colors.foreground, fontWeight: "600" }}>
                        {a.email}
                      </Text>
                      {a.label ? (
                        <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                          {a.label}
                        </Text>
                      ) : null}
                    </View>
                    {a.locked ? (
                      <View style={[styles.lockedPill, { backgroundColor: colors.primary + "1a" }]}>
                        <Feather name="lock" size={11} color={colors.primary} />
                        <Text style={{ color: colors.primary, fontSize: 11, fontWeight: "600" }}>
                          Permanent
                        </Text>
                      </View>
                    ) : canManage ? (
                      <Pressable
                        onPress={() => confirmRemove(a)}
                        disabled={remove.isPending}
                        style={({ pressed }) => [styles.removeBtn, { opacity: pressed ? 0.6 : 1 }]}
                        hitSlop={8}
                      >
                        <Feather name="x-circle" size={20} color={colors.destructive} />
                      </Pressable>
                    ) : null}
                  </View>
                ))
              )}
            </View>
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  sectionTitle: { fontSize: 15, fontWeight: "700" },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
  },
  lockedPill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 999,
  },
  removeBtn: { padding: 4 },
});
