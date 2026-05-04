import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  RefreshControl,
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
  deleteClientPortal,
  getClientPortal,
  sendClientPortalLink,
  updateClientPortal,
} from "@/lib/api/client-portals";

export default function ClientPortalDetailScreen() {
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [sendOpen, setSendOpen] = useState(false);
  const [email, setEmail] = useState("");

  const q = useQuery({
    queryKey: ["client-portal", id],
    queryFn: () => getClientPortal(id),
    enabled: Number.isFinite(id),
  });

  const toggle = useMutation({
    mutationFn: (next: boolean) =>
      updateClientPortal(id, { is_enabled: next }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["client-portal", id] });
      qc.invalidateQueries({ queryKey: ["client-portals"] });
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't update", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: () => deleteClientPortal(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["client-portals"] });
      router.back();
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't delete", e?.message ?? "Try again."),
  });

  const sendLink = useMutation({
    mutationFn: () => sendClientPortalLink(id, { email: email.trim() }),
    onSuccess: (link) => {
      setSendOpen(false);
      setEmail("");
      qc.invalidateQueries({ queryKey: ["client-portal", id] });
      Alert.alert(
        "Link sent",
        `Magic link emailed to ${link.email}.\n\n${link.url}`,
      );
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't send link", e?.message ?? "Try again."),
  });

  const portal = q.data;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: portal?.name ?? "Client portal",
          headerRight: () =>
            portal ? (
              <Pressable
                onPress={() =>
                  Alert.alert(
                    "Delete portal?",
                    "Shares, magic links and the activity log will be removed.",
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
      ) : q.isError || !portal ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load portal"
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
              <View
                style={[
                  styles.swatch,
                  { backgroundColor: portal.brand_color ?? colors.primary },
                ]}
              >
                <Text style={styles.swatchText}>
                  {(portal.brand_name ?? portal.name).slice(0, 1).toUpperCase()}
                </Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {portal.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {portal.client_name ?? "No client linked"}
                </Text>
              </View>
            </View>
            {portal.welcome_message ? (
              <Text style={[styles.body, { color: colors.foreground }]}>
                {portal.welcome_message}
              </Text>
            ) : null}
            <View style={styles.toggleRow}>
              <Text style={[styles.label, { color: colors.foreground }]}>
                Portal enabled
              </Text>
              <Switch
                value={portal.is_enabled}
                onValueChange={(v) => toggle.mutate(v)}
                disabled={toggle.isPending}
              />
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
            <View style={styles.sectionHeader}>
              <Text style={[styles.h2, { color: colors.foreground }]}>
                Shares ({portal.shares.length})
              </Text>
            </View>
            {portal.shares.length === 0 ? (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                No shares yet. Add invoices, files, boards or draft posts from
                the web app.
              </Text>
            ) : (
              portal.shares.map((s) => (
                <View key={s.id} style={[styles.itemRow, { borderColor: colors.border }]}>
                  <Feather name="share-2" size={16} color={colors.primary} />
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={1}>
                      {s.label ?? s.type_label}
                    </Text>
                    <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                      {s.type_label}
                    </Text>
                  </View>
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
            <View style={styles.sectionHeader}>
              <Text style={[styles.h2, { color: colors.foreground }]}>
                Magic links ({portal.links.length})
              </Text>
              <Pressable onPress={() => setSendOpen(true)} hitSlop={8}>
                <Text style={[styles.linkAction, { color: colors.primary }]}>
                  + Send
                </Text>
              </Pressable>
            </View>
            {portal.links.length === 0 ? (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                No links sent yet. Send one to give your client read-only
                access.
              </Text>
            ) : (
              portal.links.map((l) => (
                <View key={l.id} style={[styles.itemRow, { borderColor: colors.border }]}>
                  <Feather name="mail" size={16} color={colors.primary} />
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.label, { color: colors.foreground }]} numberOfLines={1}>
                      {l.email}
                    </Text>
                    <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                      {l.status}
                      {l.expires_at ? ` · expires ${l.expires_at.slice(0, 10)}` : ""}
                    </Text>
                  </View>
                </View>
              ))
            )}
          </View>
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
              Send magic link
            </Text>
            <TextInput
              value={email}
              onChangeText={setEmail}
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
                    setEmail("");
                  }}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button
                  label="Send"
                  onPress={() => sendLink.mutate()}
                  loading={sendLink.isPending}
                  disabled={!/.+@.+\..+/.test(email.trim())}
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
  card: { padding: 16, borderWidth: 1, gap: 12 },
  swatch: {
    width: 44,
    height: 44,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  swatchText: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18, color: "#fff" },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  itemRow: {
    flexDirection: "row",
    gap: 10,
    alignItems: "center",
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  linkAction: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
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
