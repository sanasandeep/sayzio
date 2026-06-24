import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useMemo, useState } from "react";
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
import { useAuth, type AuthUser } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import {
  getAdminContext,
  getUserRoles,
  grantAdminAccess,
  impersonateUser,
  revokeAdminAccess,
  updateUserRoles,
  type UserRolesPanel,
} from "@/lib/api/admin";

// Per-user role + admin-access assignment, mirroring the web back-office
// roles panel: toggle the web-guard roles (with the feature permissions each
// unlocks shown inline), promote / change / revoke back-office admin access,
// and impersonate the user. Every action is gated server-side behind the
// operator's admin-guard permission; the UI hides what they can't do.

function PermChips({
  perms,
  colors,
}: {
  perms: { name: string; slug: string }[];
  colors: ReturnType<typeof useColors>;
}) {
  if (perms.length === 0) {
    return (
      <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 6 }}>
        No specific feature permissions — baseline access only.
      </Text>
    );
  }
  return (
    <View style={styles.chipWrap}>
      {perms.map((p) => (
        <View key={p.slug} style={[styles.chip, { backgroundColor: colors.primary + "1a" }]}>
          <Text style={{ color: colors.primary, fontSize: 10 }}>{p.name}</Text>
        </View>
      ))}
    </View>
  );
}

export default function UserRolesScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const auth = useAuth();
  const params = useLocalSearchParams<{ id: string }>();
  const userId = Number(params.id);

  const [selected, setSelected] = useState<number[] | null>(null);
  const [pickedAdminRole, setPickedAdminRole] = useState<number | null>(null);

  const ctxQuery = useQuery({ queryKey: ["admin-context"], queryFn: getAdminContext });
  const query = useQuery({
    queryKey: ["admin-user-roles", userId],
    queryFn: () => getUserRoles(userId),
    enabled: Number.isFinite(userId),
  });

  // Seed local selection from server once.
  useEffect(() => {
    if (query.data && selected === null) {
      setSelected(query.data.roles.filter((r) => r.assigned).map((r) => r.id));
    }
  }, [query.data, selected]);

  // Default the admin-role picker to the current account's role (or the first).
  useEffect(() => {
    if (query.data && pickedAdminRole === null && query.data.admin_roles.length) {
      setPickedAdminRole(
        query.data.admin_account?.role?.id ?? query.data.admin_roles[0].id,
      );
    }
  }, [query.data, pickedAdminRole]);

  const applyPanel = (panel: UserRolesPanel) => {
    qc.setQueryData(["admin-user-roles", userId], panel);
    setSelected(panel.roles.filter((r) => r.assigned).map((r) => r.id));
    setPickedAdminRole(
      panel.admin_account?.role?.id ?? panel.admin_roles[0]?.id ?? null,
    );
  };

  const saveRoles = useMutation({
    mutationFn: () => updateUserRoles(userId, selected ?? []),
    onSuccess: applyPanel,
    onError: (e: any) => Alert.alert("Couldn't save roles", e?.message ?? "Try again."),
  });

  const grant = useMutation({
    mutationFn: () => grantAdminAccess(userId, pickedAdminRole as number),
    onSuccess: applyPanel,
    onError: (e: any) => Alert.alert("Couldn't grant access", e?.message ?? "Try again."),
  });

  const revoke = useMutation({
    mutationFn: () => revokeAdminAccess(userId),
    onSuccess: applyPanel,
    onError: (e: any) => Alert.alert("Couldn't revoke access", e?.message ?? "Try again."),
  });

  const impersonate = useMutation({
    mutationFn: () => impersonateUser(userId),
    onSuccess: async (grantRes) => {
      await auth.impersonate(grantRes.token, grantRes.user as AuthUser);
      router.replace("/(tabs)" as never);
    },
    onError: (e: any) => Alert.alert("Couldn't impersonate", e?.message ?? "Try again."),
  });

  const data = query.data;
  const canImpersonate = !!ctxQuery.data?.can.impersonate;
  const isProtected = !!data?.user.is_protected;

  const dirty = useMemo(() => {
    if (!data || selected === null) return false;
    const assigned = data.roles.filter((r) => r.assigned).map((r) => r.id).sort();
    const cur = [...selected].sort();
    return JSON.stringify(assigned) !== JSON.stringify(cur);
  }, [data, selected]);

  const toggle = (id: number) =>
    setSelected((s) => {
      const list = s ?? [];
      return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
    });

  const confirmRevoke = () =>
    Alert.alert(
      "Revoke admin access?",
      "This deletes the back-office admin record. The user account is untouched.",
      [
        { text: "Cancel", style: "cancel" },
        { text: "Revoke", style: "destructive", onPress: () => revoke.mutate() },
      ],
    );

  const confirmImpersonate = () =>
    Alert.alert(
      "Impersonate this user?",
      `You'll view the app as ${data?.user.name ?? "this user"} until you stop. Your own session is restored when you stop impersonating.`,
      [
        { text: "Cancel", style: "cancel" },
        { text: "Impersonate", onPress: () => impersonate.mutate() },
      ],
    );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Roles", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You don't have permission to manage this user's roles."
                : "Couldn't load this user."}
            </Text>
          </View>
        ) : data ? (
          <>
            {/* User header */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.title, { color: colors.foreground }]}>{data.user.name}</Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>{data.user.email}</Text>
              {isProtected ? (
                <View style={[styles.protectedPill, { backgroundColor: colors.primary + "1a" }]}>
                  <Feather name="shield" size={12} color={colors.primary} />
                  <Text style={{ color: colors.primary, fontSize: 11, fontWeight: "600" }}>
                    Protected — can't be deleted or suspended
                  </Text>
                </View>
              ) : null}
            </View>

            {/* Web roles */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>User roles</Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginBottom: 8 }}>
                Each role grants a bundle of feature permissions across the app.
              </Text>
              {data.roles.length === 0 ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  No user-pool roles are defined.
                </Text>
              ) : (
                data.roles.map((r) => {
                  const on = (selected ?? []).includes(r.id);
                  return (
                    <Pressable
                      key={r.id}
                      onPress={() => toggle(r.id)}
                      style={[
                        styles.roleRow,
                        { borderColor: on ? colors.primary : colors.border },
                      ]}
                    >
                      <Feather
                        name={on ? "check-square" : "square"}
                        size={20}
                        color={on ? colors.primary : colors.mutedForeground}
                      />
                      <View style={{ flex: 1 }}>
                        <Text style={{ color: colors.foreground, fontWeight: "600" }}>{r.name}</Text>
                        <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>{r.slug}</Text>
                        {r.description ? (
                          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                            {r.description}
                          </Text>
                        ) : null}
                        <PermChips perms={r.permissions} colors={colors} />
                      </View>
                    </Pressable>
                  );
                })
              )}
              {data.roles.length > 0 ? (
                <Button
                  label="Save roles"
                  onPress={() => saveRoles.mutate()}
                  loading={saveRoles.isPending}
                  disabled={!dirty}
                  style={{ marginTop: 12 }}
                />
              ) : null}
            </View>

            {/* Admin access */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.head}>
                <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                  Back-office admin access
                </Text>
                {data.admin_account ? (
                  <View
                    style={[
                      styles.statusPill,
                      {
                        backgroundColor:
                          data.admin_account.status === "active" ? "#10b98122" : "#f59e0b22",
                      },
                    ]}
                  >
                    <Text
                      style={{
                        fontSize: 11,
                        fontWeight: "600",
                        color: data.admin_account.status === "active" ? "#10b981" : "#f59e0b",
                      }}
                    >
                      {data.admin_account.status === "active"
                        ? "Admin · active"
                        : `Admin · ${data.admin_account.status}`}
                    </Text>
                  </View>
                ) : (
                  <View style={[styles.statusPill, { borderWidth: StyleSheet.hairlineWidth, borderColor: colors.border }]}>
                    <Text style={{ fontSize: 11, color: colors.mutedForeground }}>Not an admin</Text>
                  </View>
                )}
              </View>
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4, marginBottom: 8 }}>
                Admins are linked by email. Promoting grants back-office access
                and enables seamless dashboard switching.
              </Text>

              {data.can_grant_admin ? (
                data.admin_roles.length === 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    No admin roles are defined yet.
                  </Text>
                ) : (
                  <>
                    <Text style={{ color: colors.mutedForeground, fontSize: 11, marginBottom: 6 }}>
                      Admin role
                    </Text>
                    {data.admin_roles.map((r) => {
                      const on = pickedAdminRole === r.id;
                      return (
                        <Pressable
                          key={r.id}
                          onPress={() => setPickedAdminRole(r.id)}
                          style={[
                            styles.roleRow,
                            { borderColor: on ? colors.primary : colors.border },
                          ]}
                        >
                          <Feather
                            name={on ? "check-circle" : "circle"}
                            size={20}
                            color={on ? colors.primary : colors.mutedForeground}
                          />
                          <View style={{ flex: 1 }}>
                            <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                              {r.name}
                              <Text style={{ color: colors.mutedForeground, fontWeight: "400" }}>
                                {"  "}· {r.slug}
                              </Text>
                            </Text>
                            {r.is_super_admin ? (
                              <Text style={{ color: colors.primary, fontSize: 11, marginTop: 4 }}>
                                Unrestricted — every permission.
                              </Text>
                            ) : (
                              <PermChips perms={r.permissions} colors={colors} />
                            )}
                          </View>
                        </Pressable>
                      );
                    })}
                    <Button
                      label={data.admin_account ? "Update admin role" : "Promote to admin"}
                      onPress={() => grant.mutate()}
                      loading={grant.isPending}
                      disabled={pickedAdminRole === null}
                      style={{ marginTop: 12 }}
                    />
                  </>
                )
              ) : (
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  You don't have permission to change admin access.
                </Text>
              )}

              {data.admin_account && data.can_revoke_admin ? (
                isProtected ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 10 }}>
                    This account is protected — its admin access can't be revoked.
                  </Text>
                ) : (
                  <Button
                    label="Revoke admin access"
                    variant="outline"
                    onPress={confirmRevoke}
                    loading={revoke.isPending}
                    style={{ marginTop: 10 }}
                  />
                )
              ) : null}
            </View>

            {/* Impersonation — only when the operator holds users.impersonate */}
            {canImpersonate ? (
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.sectionTitle, { color: colors.foreground }]}>Impersonate</Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4, marginBottom: 10 }}>
                  View the app exactly as this user. Your own session is
                  restored when you stop impersonating — no re-login needed.
                </Text>
                <Button
                  label={`View as ${data.user.name}`}
                  variant="secondary"
                  onPress={confirmImpersonate}
                  loading={impersonate.isPending}
                  leading={<Feather name="user-check" size={16} color={colors.foreground} />}
                />
              </View>
            ) : null}
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  title: { fontSize: 17, fontWeight: "700" },
  sectionTitle: { fontSize: 15, fontWeight: "700" },
  head: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  statusPill: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  protectedPill: {
    flexDirection: "row",
    alignItems: "center",
    alignSelf: "flex-start",
    gap: 5,
    marginTop: 10,
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 999,
  },
  roleRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    padding: 12,
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 12,
    marginTop: 8,
  },
  chipWrap: { flexDirection: "row", flexWrap: "wrap", gap: 4, marginTop: 6 },
  chip: { paddingHorizontal: 7, paddingVertical: 3, borderRadius: 6 },
});
