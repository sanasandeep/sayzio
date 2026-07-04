import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { listContacts } from "@/lib/api/contacts";
import { importDeviceContacts } from "@/lib/deviceContacts";

export default function ContactsScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [importing, setImporting] = useState(false);

  const q = useQuery({
    queryKey: ["contacts", search],
    queryFn: () => listContacts(search || undefined),
  });

  const importDevice = async () => {
    setImporting(true);
    try {
      const out = await importDeviceContacts({ requestPermission: true });
      if (!out.ok) {
        if (out.reason === "unavailable") {
          Alert.alert(
            "Not available",
            "Device contact import isn't available on this build.",
          );
        } else if (out.reason === "denied") {
          Alert.alert(
            "Permission needed",
            "Allow access to your contacts to import them.",
          );
        } else {
          Alert.alert(
            "Nothing to import",
            "No contacts with emails or phones were found.",
          );
        }
        return;
      }
      Alert.alert(
        "Import complete",
        `Created ${out.result.created}, updated ${out.result.updated}, skipped ${out.result.skipped}.`,
      );
      qc.invalidateQueries({ queryKey: ["contacts"] });
    } catch (e: any) {
      Alert.alert("Import failed", e?.message ?? "Try again");
    } finally {
      setImporting(false);
    }
  };

  const importMutation = useMutation({ mutationFn: importDevice });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Contacts",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <View style={{ flexDirection: "row", gap: 14, paddingRight: 12 }}>
              <Pressable
                onPress={() => router.push("/contacts/follow-ups")}
                hitSlop={8}
              >
                <Feather name="bell" size={19} color={colors.primary} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/contacts/google-sync")}
                hitSlop={8}
              >
                <Feather name="refresh-cw" size={19} color={colors.primary} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/contacts/import")}
                hitSlop={8}
              >
                <Feather name="upload" size={20} color={colors.primary} />
              </Pressable>
              <Pressable onPress={() => importMutation.mutate()} hitSlop={8}>
                {importing ? (
                  <ActivityIndicator color={colors.primary} size="small" />
                ) : (
                  <Feather name="download" size={20} color={colors.primary} />
                )}
              </Pressable>
              <Pressable
                onPress={() => router.push("/contacts/new")}
                hitSlop={8}
              >
                <Feather name="plus" size={22} color={colors.primary} />
              </Pressable>
            </View>
          ),
        }}
      />
      <View style={{ padding: 16, paddingBottom: 4 }}>
        <View
          style={[
            styles.searchWrap,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius - 4,
            },
          ]}
        >
          <Feather name="search" size={16} color={colors.mutedForeground} />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder="Search contacts"
            placeholderTextColor={colors.mutedForeground}
            style={{
              flex: 1,
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_400Regular",
              fontSize: 14,
            }}
          />
        </View>
        {q.data?.usage && !q.data.usage.unlimited ? (
          <View
            style={[
              styles.usage,
              {
                backgroundColor: q.data.usage.at_cap
                  ? colors.destructive + "1a"
                  : q.data.usage.near_cap
                    ? colors.primary + "14"
                    : colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius - 4,
              },
            ]}
          >
            <Feather
              name={q.data.usage.at_cap ? "alert-triangle" : "users"}
              size={13}
              color={q.data.usage.at_cap ? colors.destructive : colors.mutedForeground}
            />
            <Text
              style={{
                color: q.data.usage.at_cap ? colors.destructive : colors.mutedForeground,
                fontFamily: "SpaceGrotesk_400Regular",
                fontSize: 12,
              }}
            >
              {q.data.usage.count} / {q.data.usage.cap} contacts
              {q.data.usage.at_cap ? " · limit reached" : ""}
            </Text>
          </View>
        ) : null}
      </View>
      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 16, gap: 8 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => {
            const primary =
              item.emails.find((e) => e.is_primary)?.value ||
              item.emails[0]?.value ||
              item.phones.find((p) => p.is_primary)?.value ||
              item.phones[0]?.value ||
              item.organization ||
              "";
            return (
              <Pressable
                onPress={() => router.push(`/contacts/${item.id}`)}
                style={[
                  styles.row,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View
                  style={[
                    styles.avatar,
                    { backgroundColor: colors.primary + "1c" },
                  ]}
                >
                  <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_700Bold" }}>
                    {(item.display_name || "?").slice(0, 1).toUpperCase()}
                  </Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text
                    numberOfLines={1}
                    style={[styles.name, { color: colors.foreground }]}
                  >
                    {item.display_name || "Unnamed"}
                  </Text>
                  {primary ? (
                    <Text
                      numberOfLines={1}
                      style={[styles.sub, { color: colors.mutedForeground }]}
                    >
                      {primary}
                    </Text>
                  ) : null}
                </View>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
            );
          }}
          ListEmptyComponent={
            <EmptyState
              icon="users"
              title="No contacts yet"
              body="Add a contact or import from your device."
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  searchWrap: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
  },
  usage: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: StyleSheet.hairlineWidth,
    marginTop: 8,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
});
