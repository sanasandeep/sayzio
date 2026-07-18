import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { router, Stack } from "expo-router";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { clearFollowUp, Contact, contactInitials, contactPrimaryPhone, listFollowUps } from "@/lib/api/contacts";

export default function FollowUpsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["follow-ups"],
    queryFn: listFollowUps,
    staleTime: 30_000,
  });

  const overdue = q.data?.overdue ?? [];
  const upcoming = q.data?.upcoming ?? [];
  const total = overdue.length + upcoming.length;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Follow-ups",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: { fontFamily: "SpaceGrotesk_600SemiBold", color: colors.foreground },
          headerTintColor: colors.primary,
        }}
      />

      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : total === 0 ? (
        <EmptyState
          icon="check-circle"
          title="All caught up!"
          body="You have no scheduled follow-ups."
        />
      ) : (
        <FlatList
          data={[
            ...(overdue.length > 0
              ? [{ type: "header" as const, label: "Overdue", color: "#ef4444" }, ...overdue.map((c) => ({ type: "contact" as const, contact: c, section: "overdue" }))]
              : []),
            ...(upcoming.length > 0
              ? [{ type: "header" as const, label: "Upcoming", color: colors.primary }, ...upcoming.map((c) => ({ type: "contact" as const, contact: c, section: "upcoming" }))]
              : []),
          ]}
          keyExtractor={(item, i) =>
            item.type === "header" ? `hdr-${i}` : `contact-${item.contact.id}`
          }
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 48, paddingTop: 8 }}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={
            <RefreshControl
              refreshing={q.isRefetching}
              onRefresh={() => qc.invalidateQueries({ queryKey: ["follow-ups"] })}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => {
            if (item.type === "header") {
              return (
                <View style={{ paddingVertical: 8, paddingTop: 16 }}>
                  <Text
                    style={{
                      fontFamily: "SpaceGrotesk_700Bold",
                      fontSize: 11,
                      letterSpacing: 0.7,
                      color: item.color,
                      textTransform: "uppercase",
                    }}
                  >
                    {item.label}
                  </Text>
                </View>
              );
            }
            return (
              <FollowUpRow
                c={item.contact}
                overdue={item.section === "overdue"}
                colors={colors}
                onCleared={() => {
                  qc.invalidateQueries({ queryKey: ["follow-ups"] });
                  qc.invalidateQueries({ queryKey: ["contacts"] });
                  qc.invalidateQueries({ queryKey: ["contact", item.contact.id] });
                }}
              />
            );
          }}
        />
      )}
    </View>
  );
}

function FollowUpRow({
  c,
  overdue,
  colors,
  onCleared,
}: {
  c: Contact;
  overdue: boolean;
  colors: ReturnType<typeof useColors>;
  onCleared: () => void;
}) {
  const clearMut = useMutation({
    mutationFn: () => clearFollowUp(c.id),
    onSuccess: onCleared,
  });

  function handleClear() {
    if (Platform.OS === "web") {
      if (!window.confirm("Clear this follow-up reminder?")) return;
      clearMut.mutate();
    } else {
      Alert.alert(
        "Clear follow-up",
        "Remove this follow-up reminder?",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Clear", style: "destructive", onPress: () => clearMut.mutate() },
        ],
      );
    }
  }

  const phone = contactPrimaryPhone(c);
  const dueLabel = c.follow_up_at ? new Date(c.follow_up_at).toLocaleString() : "";

  return (
    <Pressable
      onPress={() => router.push(`/contacts/${c.id}` as any)}
      style={({ pressed }) => [
        styles.row,
        {
          backgroundColor: overdue ? "#ef444410" : colors.card,
          borderColor: overdue ? "#ef444430" : colors.border,
          opacity: pressed ? 0.75 : 1,
        },
      ]}
    >
      <View style={[styles.avatar, { backgroundColor: overdue ? "#ef444420" : colors.primary + "20" }]}>
        <Text style={[styles.avatarText, { color: overdue ? "#ef4444" : colors.primary, fontFamily: "SpaceGrotesk_700Bold" }]}>
          {contactInitials(c)}
        </Text>
      </View>

      <View style={{ flex: 1, minWidth: 0 }}>
        <Text numberOfLines={1} style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14, color: colors.foreground }}>
          {c.display_name}
        </Text>
        {phone && (
          <Text numberOfLines={1} style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginTop: 1 }}>
            {phone}
          </Text>
        )}
        <View style={{ flexDirection: "row", alignItems: "center", gap: 4, marginTop: 4 }}>
          <Feather name="clock" size={11} color={overdue ? "#ef4444" : colors.primary} />
          <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, color: overdue ? "#ef4444" : colors.primary }}>
            {dueLabel}
          </Text>
        </View>
        {c.follow_up_note ? (
          <Text numberOfLines={2} style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginTop: 2 }}>
            {c.follow_up_note}
          </Text>
        ) : null}
      </View>

      <View style={{ gap: 8, alignItems: "center" }}>
        <Pressable
          onPress={handleClear}
          disabled={clearMut.isPending}
          style={({ pressed }) => ({ opacity: pressed || clearMut.isPending ? 0.5 : 1 })}
        >
          <Feather name="check-circle" size={18} color={overdue ? "#ef4444" : colors.mutedForeground} />
        </Pressable>
        <Feather name="chevron-right" size={13} color={colors.mutedForeground} />
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  avatarText: { fontSize: 15 },
});
