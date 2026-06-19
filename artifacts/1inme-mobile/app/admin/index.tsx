import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { getAdminContext, type AdminCapabilities } from "@/lib/api/admin";

// Mobile "admin dashboard" surface. A signed-in user whose account is linked
// to an active back-office admin record reaches this with the same token —
// switching admin <-> user is just navigating here and back, no re-login.
// Each entry is gated by the operator's admin-guard permissions returned by
// /admin/context, mirroring the web back-office.

type Row = {
  key: keyof AdminCapabilities | "mail" | "schema-audits";
  icon: keyof typeof Feather.glyphMap;
  label: string;
  description: string;
  href: string;
  enabled: boolean;
};

export default function AdminHubScreen() {
  const colors = useColors();
  const router = useRouter();

  const query = useQuery({
    queryKey: ["admin-context"],
    queryFn: getAdminContext,
  });

  const ctx = query.data;
  const can = ctx?.can;

  const rows: Row[] = [
    {
      key: "manage_roles",
      icon: "shield",
      label: "Roles & admin access",
      description: "Assign roles, promote or revoke admins",
      href: "/admin/users",
      enabled: !!can?.manage_roles || !!can?.view_users,
    },
  ];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Admin dashboard", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              Couldn't load your admin context.
            </Text>
          </View>
        ) : !ctx?.has_admin_access ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="lock" size={20} color={colors.mutedForeground} />
            <Text style={{ color: colors.foreground, marginTop: 6, fontWeight: "600" }}>
              No back-office access
            </Text>
            <Text style={{ color: colors.mutedForeground, marginTop: 4 }}>
              Your account isn't linked to an active admin role.
            </Text>
          </View>
        ) : (
          <>
            {/* Operator identity */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.head}>
                <Feather name="shield" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {ctx.admin?.name ?? "Administrator"}
                </Text>
              </View>
              {ctx.admin?.role ? (
                <View style={[styles.rolePill, { backgroundColor: colors.primary + "1a" }]}>
                  <Text style={{ color: colors.primary, fontSize: 12, fontWeight: "600" }}>
                    {ctx.admin.role.name}
                    {ctx.admin.role.is_super_admin ? " · full access" : ""}
                  </Text>
                </View>
              ) : null}
              <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                You're viewing the admin dashboard. Tap "Switch to user
                dashboard" any time to return to your own account.
              </Text>
            </View>

            {/* Admin tools */}
            <View
              style={[
                styles.list,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              {rows.map((r, i) => (
                <Pressable
                  key={r.key}
                  disabled={!r.enabled}
                  onPress={() => router.push(r.href as never)}
                  style={({ pressed }) => [
                    styles.listItem,
                    {
                      borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                      borderTopColor: colors.border,
                      opacity: !r.enabled ? 0.4 : pressed ? 0.7 : 1,
                    },
                  ]}
                >
                  <Feather name={r.icon} size={18} color={colors.primary} />
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.itemLabel, { color: colors.foreground }]}>
                      {r.label}
                    </Text>
                    <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                      {r.enabled ? r.description : "Not available for your role"}
                    </Text>
                  </View>
                  <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                </Pressable>
              ))}
            </View>

            {/* Switch back to the user dashboard */}
            <Pressable
              onPress={() => router.back()}
              style={({ pressed }) => [
                styles.switchBtn,
                {
                  borderColor: colors.border,
                  backgroundColor: colors.card,
                  borderRadius: colors.radius,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
            >
              <Feather name="log-out" size={18} color={colors.foreground} />
              <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                Switch to user dashboard
              </Text>
            </Pressable>
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
  rolePill: {
    alignSelf: "flex-start",
    marginTop: 8,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  list: { borderWidth: StyleSheet.hairlineWidth, overflow: "hidden" },
  listItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
  },
  itemLabel: { fontSize: 15, fontWeight: "600" },
  switchBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    borderWidth: StyleSheet.hairlineWidth,
    paddingVertical: 14,
  },
});
