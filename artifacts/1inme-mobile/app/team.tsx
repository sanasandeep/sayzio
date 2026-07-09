import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  getTeam,
  inviteTeammate,
  removeTeamMember,
  revokeTeamInvite,
  type TeamRole,
} from "@/lib/api/team";
import { showAlert } from "@/lib/webAlert";

const ROLES: { value: TeamRole; label: string; blurb: string }[] = [
  { value: "admin", label: "Admin", blurb: "Manage teammates + workspace settings" },
  { value: "editor", label: "Editor", blurb: "Create and edit content" },
  { value: "replier", label: "Replier", blurb: "Reply in inbox + DMs" },
  { value: "analyst", label: "Analyst", blurb: "Read analytics and reports" },
  { value: "viewer", label: "Viewer", blurb: "Read-only access" },
];

export default function TeamScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [inviteOpen, setInviteOpen] = useState(false);
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<TeamRole>("editor");

  const q = useQuery({ queryKey: ["team"], queryFn: getTeam });

  const invite = useMutation({
    mutationFn: () => inviteTeammate({ email: email.trim(), role }),
    onSuccess: () => {
      setInviteOpen(false);
      setEmail("");
      qc.invalidateQueries({ queryKey: ["team"] });
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't invite teammate", e?.message ?? "Try again."),
  });

  const revoke = useMutation({
    mutationFn: (id: number) => revokeTeamInvite(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["team"] }),
    onError: (e: { message?: string }) =>
      showAlert("Couldn't revoke invite", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => removeTeamMember(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["team"] }),
    onError: (e: { message?: string }) =>
      showAlert("Couldn't remove member", e?.message ?? "Try again."),
  });

  const data = q.data;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Team & staff",
          headerRight: () =>
            data?.can_manage ? (
              <Pressable
                onPress={() => setInviteOpen(true)}
                hitSlop={10}
                style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1, paddingHorizontal: 6 })}
              >
                <Feather name="user-plus" size={20} color={colors.primary} />
              </Pressable>
            ) : null,
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError || !data ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load your team"
          body={(q.error as { message?: string })?.message ?? "Try again."}
        />
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 20, gap: 14, paddingBottom: 40 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        >
          <View style={{ gap: 6 }}>
            <Text style={[styles.h, { color: colors.foreground }]}>
              {data.workspace.name}
            </Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              {data.used_seats} of{" "}
              {data.max_seats === -1 ? "unlimited" : data.max_seats} seats used ·{" "}
              {data.workspace.is_personal ? "Personal workspace" : "Shared workspace"}
            </Text>
          </View>

          {data.members.length === 0 ? (
            <EmptyState
              icon="user-plus"
              title="No teammates yet"
              body={
                data.can_manage
                  ? "Tap the invite icon to add your first teammate."
                  : "Only the workspace owner or an admin can invite teammates."
              }
            />
          ) : (
            <View style={{ gap: 8 }}>
              <Text style={[styles.h2, { color: colors.foreground }]}>
                Members ({data.members.length})
              </Text>
              {data.members.map((m) => (
                <View
                  key={m.id}
                  style={[
                    styles.row,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
                    <Feather name="user" size={16} color={colors.primary} />
                  </View>
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                      {m.name || m.email || `Member #${m.user_id}`}
                    </Text>
                    {m.email ? (
                      <Text style={[styles.subRow, { color: colors.mutedForeground }]} numberOfLines={1}>
                        {m.email}
                      </Text>
                    ) : null}
                  </View>
                  <View style={[styles.badge, { backgroundColor: colors.primary + "22" }]}>
                    <Text style={[styles.badgeText, { color: colors.primary }]}>
                      {m.role}
                    </Text>
                  </View>
                  {data.can_manage ? (
                    <Pressable
                      hitSlop={10}
                      onPress={() =>
                        showAlert(
                          "Remove teammate?",
                          `${m.name || m.email || "This member"} will lose access to ${data.workspace.name}.`,
                          [
                            { text: "Cancel", style: "cancel" },
                            {
                              text: "Remove",
                              style: "destructive",
                              onPress: () => remove.mutate(m.id),
                            },
                          ],
                        )
                      }
                    >
                      <Feather name="x" size={18} color={colors.mutedForeground} />
                    </Pressable>
                  ) : null}
                </View>
              ))}
            </View>
          )}

          {data.pending_invites.length > 0 ? (
            <View style={{ gap: 8 }}>
              <Text style={[styles.h2, { color: colors.foreground }]}>
                Pending invites ({data.pending_invites.length})
              </Text>
              {data.pending_invites.map((i) => (
                <View
                  key={i.id}
                  style={[
                    styles.row,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
                    <Feather name="mail" size={16} color={colors.primary} />
                  </View>
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                      {i.email}
                    </Text>
                    <Text style={[styles.subRow, { color: colors.mutedForeground }]} numberOfLines={1}>
                      {i.role.toUpperCase()}
                      {i.expires_at ? ` · expires ${i.expires_at.slice(0, 10)}` : ""}
                    </Text>
                  </View>
                  {data.can_manage ? (
                    <Pressable
                      hitSlop={10}
                      onPress={() =>
                        showAlert("Revoke invite?", `Revoke ${i.email}?`, [
                          { text: "Cancel", style: "cancel" },
                          {
                            text: "Revoke",
                            style: "destructive",
                            onPress: () => revoke.mutate(i.id),
                          },
                        ])
                      }
                    >
                      <Feather name="x" size={18} color={colors.mutedForeground} />
                    </Pressable>
                  ) : null}
                </View>
              ))}
            </View>
          ) : null}
        </ScrollView>
      )}

      <Modal
        visible={inviteOpen}
        animationType="slide"
        transparent
        onRequestClose={() => setInviteOpen(false)}
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
              Invite teammate
            </Text>
            <TextInput
              value={email}
              onChangeText={setEmail}
              placeholder="teammate@example.com"
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
            <View style={{ gap: 6 }}>
              {ROLES.map((r) => {
                const selected = r.value === role;
                return (
                  <Pressable
                    key={r.value}
                    onPress={() => setRole(r.value)}
                    style={({ pressed }) => [
                      styles.roleRow,
                      {
                        backgroundColor: selected
                          ? colors.primary + "1c"
                          : "transparent",
                        borderColor: selected ? colors.primary : colors.border,
                        borderRadius: colors.radius,
                        opacity: pressed ? 0.7 : 1,
                      },
                    ]}
                  >
                    <Feather
                      name={selected ? "check-circle" : "circle"}
                      size={18}
                      color={selected ? colors.primary : colors.mutedForeground}
                    />
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.label, { color: colors.foreground }]}>
                        {r.label}
                      </Text>
                      <Text style={[styles.subRow, { color: colors.mutedForeground }]}>
                        {r.blurb}
                      </Text>
                    </View>
                  </Pressable>
                );
              })}
            </View>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 1 }}>
                <Button
                  label="Cancel"
                  variant="ghost"
                  onPress={() => {
                    setInviteOpen(false);
                    setEmail("");
                  }}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button
                  label="Send invite"
                  onPress={() => invite.mutate()}
                  loading={invite.isPending}
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
  h: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  subRow: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
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
  roleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 12,
    borderWidth: 1,
  },
});
