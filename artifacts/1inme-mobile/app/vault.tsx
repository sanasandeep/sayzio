import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  listVaultClients,
  listVaultCredentials,
  type VaultClient,
  type VaultCredential,
} from "@/lib/api/vault";

type Tab = "clients" | "credentials";

export default function VaultScreen() {
  const colors = useColors();
  const [tab, setTab] = useState<Tab>("clients");

  const clients = useQuery({
    queryKey: ["vault-clients"],
    queryFn: listVaultClients,
    enabled: tab === "clients",
  });
  const creds = useQuery({
    queryKey: ["vault-credentials"],
    queryFn: listVaultCredentials,
    enabled: tab === "credentials",
  });

  const active = tab === "clients" ? clients : creds;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Vault" }} />
      <View style={{ paddingHorizontal: 20, paddingTop: 12 }}>
        <View
          style={[
            styles.segment,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          {(["clients", "credentials"] as Tab[]).map((t) => {
            const isActive = t === tab;
            return (
              <Pressable
                key={t}
                onPress={() => setTab(t)}
                style={[
                  styles.segmentItem,
                  {
                    backgroundColor: isActive ? colors.background : "transparent",
                    borderRadius: colors.radius - 4,
                  },
                ]}
              >
                <Text
                  style={{
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    fontSize: 13,
                    color: isActive ? colors.primary : colors.mutedForeground,
                  }}
                >
                  {t === "clients" ? "Clients" : "Credentials"}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </View>

      {active.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : tab === "clients" ? (
        <FlatList<VaultClient>
          data={clients.data ?? []}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="briefcase" size={16} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.company || item.primary_email || item.primary_phone || "—"}
                </Text>
              </View>
              {item.visibility === "private" ? (
                <Feather name="lock" size={14} color={colors.mutedForeground} />
              ) : null}
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="briefcase"
              title="No clients yet"
              body="Vault clients live in your workspace; add them from the web."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={clients.isFetching && !clients.isLoading}
              onRefresh={() => clients.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      ) : (
        <FlatList<VaultCredential>
          data={creds.data ?? []}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="key" size={16} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.label}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.username || item.url || "—"}
                </Text>
              </View>
              <Feather name="lock" size={14} color={colors.mutedForeground} />
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="key"
              title="No credentials yet"
              body="Reveal secrets on the web; the encryption flow is anchored there for safety."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={creds.isFetching && !creds.isLoading}
              onRefresh={() => creds.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: { flex: 1, alignItems: "center", paddingVertical: 10 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 12, borderWidth: 1 },
  iconWrap: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
});
