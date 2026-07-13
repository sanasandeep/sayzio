import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
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
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import { showAlert } from "@/lib/webAlert";
import {
  getWorkspaceTeam,
  inviteWorkspaceMember,
  removeWorkspaceMember,
  revokeWorkspaceInvite,
  updateWorkspaceMemberRole,
  type WorkspaceMember,
  type WorkspaceRole,
} from "@/lib/api/workspaces";

const ROLES: { value: WorkspaceRole; label: string; blurb: string }[] = [
  { value: "admin", label: "Admin", blurb: "Manage teammates + workspace settings" },
  { value: "editor", label: "Editor", blurb: "Create and edit content" },
  { value: "replier", label: "Replier", blurb: "Reply in inbox + DMs" },
  { value: "analyst", label: "Analyst", blurb: "Read analytics and reports" },
  { value: "viewer", label: "Viewer", blurb: "Read-only access" },
];

const roleLabel = (role: string) =>
  ROLES.find((r) => r.value === role)?.label ?? role;

export default function WorkspaceMembersScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id, name } = useLocalSearchParams<{ id?: string; name?: string }>();
  const wsId = Number(id);

  const [inviteOpen, setInviteOpen] = useState(false);
  const [email, setEmail] = useState("");
  const [inviteRole, setInviteRole] = useState<WorkspaceRole>("editor");
  // The member whose role is being changed (null = the picker is closed).
  const [roleTarget, setRoleTarget] = useState<WorkspaceMember | null>(null);

  const q = useQuery({
    queryKey: ["workspace-team", wsId],
    queryFn: () => getWorkspaceTeam(wsId),
    enabled: Number.isFinite(wsId),
  });

  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["workspace-team", wsId] });

  const invite = useMutation({
    mutationFn: () =>
      inviteWorkspaceMember(wsId, { email: email.trim(), role: inviteRole }),
    onSuccess: () => {
      setInviteOpen(false);
      setEmail("");
      invalidate();
    },
    onError: (e: { message?: string }) => {
      // A seat cap (plan / team-billing) comes back plan-gated — show the same
      // upgrade prompt the workspace-create flow uses instead of a raw error.
      if (handlePlanLockedError(e)) return;
      showAlert("Couldn't invite teammate", e?.message ?? "Try again.");
    },
  });

  const changeRole = useMutation({
    mutationFn: ({ memberId, role }: { memberId: number; role: WorkspaceRole }) =>
      updateWorkspaceMemberRole(wsId, memberId, role),
    onSuccess: () => {
      setRoleTarget(null);
      invalidate();
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't change role", e?.message ?? "Try again."),
  });

  const revoke = useMutation({
    mutationFn: (inviteId: number) => revokeWorkspaceInvite(wsId, inviteId),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      showAlert("Couldn't revoke invite", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (memberId: number) => removeWorkspaceMember(wsId, memberId),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      showAlert("Couldn't remove member", e?.message ?? "Try again."),
  });

  const data = q.data;
  const canManage = !!data?.can_manage;
  const wsName = data?.workspace.name || name || "Members";

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: wsName,
          headerRight: () =>
            canManage ? (
              <Pressable
                onPress={() => setInviteOpen(true)}
                hitSlop={10}
                style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1, paddingHorizontal: 6 })}
                accessibilityRole="button"
                accessibilityLabel="Invite teammate"
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
          title="Couldn't load members"
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
          <View style={{ gap: 4 }}>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              {data.used_seats} of{" "}
              {data.max_seats === -1 ? "unlimited" : data.max_seats} seats used
            </Text>
          </View>

          {data.members.length === 0 ? (
            <EmptyState
              icon="user-plus"
              title="No members yet"
              body={
                canManage
                  ? "Tap the invite icon to add your first teammate."
                  : "Only the workspace owner or an Admin can invite teammates."
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
                  {/* When the caller can manage, the role badge is a button that
                      opens the role picker; otherwise it's a static badge. */}
                  {canManage ? (
                    <Pressable
                      onPress={() => setRoleTarget(m)}
                      hitSlop={6}
                      style={({ pressed }) => [
                        styles.badge,
                        { backgroundColor: colors.primary + "22", opacity: pressed ? 0.6 : 1 },
                      ]}
                      accessibilityRole="button"
                      accessibilityLabel={`Change role for ${m.name || m.email || "member"}`}
                    >
                      <Text style={[styles.badgeText, { color: colors.primary }]}>
                        {roleLabel(m.role)}
                      </Text>
                      <Feather name="chevron-down" size={12} color={colors.primary} />
                    </Pressable>
                  ) : (
                    <View style={[styles.badge, { backgroundColor: colors.primary + "22" }]}>
                      <Text style={[styles.badgeText, { color: colors.primary }]}>
                        {roleLabel(m.role)}
                      </Text>
                    </View>
                  )}
                  {canManage ? (
                    <Pressable
                      hitSlop={10}
                      onPress={() =>
                        showAlert(
                          "Remove teammate?",
                          `${m.name || m.email || "This member"} will lose access to ${wsName}.`,
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
                      accessibilityRole="button"
                      accessibilityLabel={`Remove ${m.name || m.email || "member"}`}
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
                      {roleLabel(i.role).toUpperCase()}
                      {i.expires_at ? ` · expires ${i.expires_at.slice(0, 10)}` : ""}
                    </Text>
                  </View>
                  {canManage ? (
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
                      accessibilityRole="button"
                      accessibilityLabel={`Revoke invite for ${i.email}`}
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

      {/* Invite modal */}
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
                const selected = r.value === inviteRole;
                return (
                  <Pressable
                    key={r.value}
                    onPress={() => setInviteRole(r.value)}
                    style={({ pressed }) => [
                      styles.roleRow,
                      {
                        backgroundColor: selected ? colors.primary + "1c" : "transparent",
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

      {/* Role picker modal */}
      <Modal
        visible={roleTarget !== null}
        animationType="slide"
        transparent
        onRequestClose={() => setRoleTarget(null)}
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
              Change role
            </Text>
            {roleTarget ? (
              <Text style={[styles.subRow, { color: colors.mutedForeground }]}>
                {roleTarget.name || roleTarget.email || `Member #${roleTarget.user_id}`}
              </Text>
            ) : null}
            <View style={{ gap: 6 }}>
              {ROLES.map((r) => {
                const selected = r.value === roleTarget?.role;
                return (
                  <Pressable
                    key={r.value}
                    disabled={changeRole.isPending}
                    onPress={() => {
                      if (!roleTarget) return;
                      if (r.value === roleTarget.role) {
                        setRoleTarget(null);
                        return;
                      }
                      changeRole.mutate({ memberId: roleTarget.id, role: r.value });
                    }}
                    style={({ pressed }) => [
                      styles.roleRow,
                      {
                        backgroundColor: selected ? colors.primary + "1c" : "transparent",
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
            <Button
              label="Cancel"
              variant="ghost"
              onPress={() => setRoleTarget(null)}
              disabled={changeRole.isPending}
            />
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
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
  badge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 3,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
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
