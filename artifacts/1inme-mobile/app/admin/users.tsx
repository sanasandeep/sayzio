import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { listAdminUsers } from "@/lib/api/admin";

// User picker for the role / admin-access assignment flow. Mirrors the web
// back-office users index: a debounced search over name / email / handle,
// flagging who already holds back-office admin access. Tapping a user opens
// the per-user roles screen. Gated server-side behind `users.view`.

export default function AdminUsersScreen() {
  const colors = useColors();
  const router = useRouter();

  const [search, setSearch] = useState("");
  const [debounced, setDebounced] = useState("");

  useEffect(() => {
    const t = setTimeout(() => setDebounced(search), 350);
    return () => clearTimeout(t);
  }, [search]);

  const query = useQuery({
    queryKey: ["admin-users", debounced],
    queryFn: () => listAdminUsers(debounced, 1),
  });

  const users = query.data?.users ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Roles & admin access", headerBackTitle: "Back" }} />
      <View style={{ padding: 16, paddingBottom: 8 }}>
        <TextField
          label="Search users"
          placeholder="Name, email, or handle"
          autoCapitalize="none"
          autoCorrect={false}
          value={search}
          onChangeText={setSearch}
        />
      </View>
      <ScrollView contentContainerStyle={{ padding: 16, paddingTop: 4, gap: 10, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You don't have permission to view users."
                : "Couldn't load users."}
            </Text>
          </View>
        ) : users.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, marginTop: 16, textAlign: "center" }}>
            No users found.
          </Text>
        ) : (
          users.map((u) => (
            <Pressable
              key={u.id}
              onPress={() => router.push(`/admin/users/${u.id}` as never)}
              style={({ pressed }) => [
                styles.row,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
            >
              {u.avatar ? (
                <Image source={{ uri: u.avatar }} style={styles.avatar} />
              ) : (
                <View style={[styles.avatar, { backgroundColor: colors.primary + "22", alignItems: "center", justifyContent: "center" }]}>
                  <Text style={{ color: colors.primary, fontWeight: "700" }}>
                    {(u.name || u.email || "?").slice(0, 1).toUpperCase()}
                  </Text>
                </View>
              )}
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text numberOfLines={1} style={[styles.name, { color: colors.foreground }]}>
                  {u.name || "(no name)"}
                </Text>
                <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {u.email}
                </Text>
              </View>
              {u.is_protected ? (
                <View style={[styles.protectedPill, { backgroundColor: colors.primary + "1a" }]}>
                  <Feather name="shield" size={11} color={colors.primary} />
                </View>
              ) : null}
              {u.is_admin ? (
                <View
                  style={[
                    styles.adminPill,
                    {
                      backgroundColor:
                        u.admin_status === "active" ? "#10b98122" : "#f59e0b22",
                    },
                  ]}
                >
                  <Text
                    style={{
                      fontSize: 11,
                      fontWeight: "600",
                      color: u.admin_status === "active" ? "#10b981" : "#f59e0b",
                    }}
                  >
                    Admin
                  </Text>
                </View>
              ) : null}
              <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
            </Pressable>
          ))
        )}
        {query.data?.has_more ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 12, textAlign: "center", marginTop: 8 }}>
            Showing the first {users.length}. Refine your search to narrow down.
          </Text>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderWidth: StyleSheet.hairlineWidth,
  },
  avatar: { width: 40, height: 40, borderRadius: 20 },
  name: { fontSize: 15, fontWeight: "600" },
  adminPill: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  protectedPill: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
});
