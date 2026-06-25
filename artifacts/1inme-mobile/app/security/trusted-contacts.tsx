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
  acceptTrustedContactInvitation,
  decideRecoveryRequest,
  declineTrustedContactInvitation,
  getSecuritySettings,
  listRecoveryRequests,
  listTrustedContactInvitations,
  listTrustedContacts,
  nominateTrustedContact,
  revokeTrustedContact,
  type RecoveryRequest,
  type TrustedContact,
  type TrustedContactInvitation,
} from "@/lib/api/security";

const STATUS_LABEL: Record<TrustedContact["status"], string> = {
  pending: "Waiting to accept",
  active: "Active",
  revoked: "Revoked",
};

export default function TrustedContactsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [handle, setHandle] = useState("");
  const [error, setError] = useState<string | null>(null);

  const settingsQ = useQuery({
    queryKey: ["security", "settings"],
    queryFn: getSecuritySettings,
    retry: false,
  });
  const contactsQ = useQuery({
    queryKey: ["security", "trusted-contacts"],
    queryFn: listTrustedContacts,
  });
  const invitesQ = useQuery({
    queryKey: ["security", "trusted-contact-invitations"],
    queryFn: listTrustedContactInvitations,
  });
  const recoveryQ = useQuery({
    queryKey: ["security", "recovery-requests"],
    queryFn: listRecoveryRequests,
  });

  const max = settingsQ.data?.trusted_contacts_max ?? 5;
  const required = settingsQ.data?.trusted_contacts_required_to_recover ?? 2;

  const contacts = contactsQ.data ?? [];
  const invites = invitesQ.data ?? [];
  const recovery = recoveryQ.data ?? [];
  const liveCount = contacts.filter((c) => c.status !== "revoked").length;
  const atLimit = liveCount >= max;

  const nominate = useMutation({
    mutationFn: () => {
      const h = handle.trim().replace(/^@/, "");
      if (!h) throw { message: "Enter a handle" } as Error;
      return nominateTrustedContact({ handle: h });
    },
    onSuccess: () => {
      setHandle("");
      setError(null);
      qc.invalidateQueries({ queryKey: ["security", "trusted-contacts"] });
    },
    onError: (e: { status?: number; message?: string }) => {
      if (e?.status === 409) {
        setError(e.message ?? "Already nominated, or your list is full.");
      } else if (e?.status === 404) {
        setError("That handle doesn't exist.");
      } else {
        setError(e?.message ?? "Couldn't send the invite.");
      }
    },
  });

  const revoke = useMutation({
    mutationFn: (id: number) => revokeTrustedContact(id),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["security", "trusted-contacts"] }),
  });

  const accept = useMutation({
    mutationFn: (id: number) => acceptTrustedContactInvitation(id),
    onSuccess: () => {
      qc.invalidateQueries({
        queryKey: ["security", "trusted-contact-invitations"],
      });
    },
  });

  const decline = useMutation({
    mutationFn: (id: number) => declineTrustedContactInvitation(id),
    onSuccess: () =>
      qc.invalidateQueries({
        queryKey: ["security", "trusted-contact-invitations"],
      }),
  });

  const decide = useMutation({
    mutationFn: ({ id, decision }: { id: number; decision: "confirmed" | "denied" }) =>
      decideRecoveryRequest(id, decision),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["security", "recovery-requests"] }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't submit", e?.message ?? "Try again shortly."),
  });

  const loading =
    contactsQ.isLoading || invitesQ.isLoading || recoveryQ.isLoading;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Trusted contacts" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 18, paddingBottom: 40 }}>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Pick up to {max} Sayzio friends who can vouch for you if you ever lose
          access. {required} of them need to confirm during recovery.
        </Text>

        {/* People I've nominated ────────────────────────────── */}
        <Section title="My contacts" colors={colors}>
          {loading ? (
            <ActivityIndicator color={colors.primary} />
          ) : contacts.length === 0 ? (
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              No one yet. Add your first one below.
            </Text>
          ) : (
            contacts.map((c) => (
              <ContactRow
                key={c.id}
                contact={c}
                colors={colors}
                onRevoke={() => revoke.mutate(c.id)}
                disabled={revoke.isPending}
              />
            ))
          )}

          <View style={{ height: 4 }} />
          <TextField
            label="Add by handle"
            placeholder="@friend"
            autoCapitalize="none"
            autoCorrect={false}
            value={handle}
            onChangeText={(t) => {
              setHandle(t);
              setError(null);
            }}
            error={error ?? undefined}
            hint={
              atLimit
                ? `You've reached the maximum of ${max} contacts. Remove one to add another.`
                : undefined
            }
          />
          <Button
            label="Send invite"
            onPress={() => nominate.mutate()}
            loading={nominate.isPending}
            disabled={atLimit || !handle.trim()}
          />
        </Section>

        {/* Invites pointing at me ─────────────────────────── */}
        {invites.length > 0 ? (
          <Section title="People who picked you" colors={colors}>
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              They want you as their recovery contact. You'll get a prompt
              here if they ever try to recover.
            </Text>
            {invites.map((inv) => (
              <InvitationRow
                key={inv.id}
                invite={inv}
                colors={colors}
                onAccept={() => accept.mutate(inv.id)}
                onDecline={() => decline.mutate(inv.id)}
                busy={accept.isPending || decline.isPending}
              />
            ))}
          </Section>
        ) : null}

        {/* Recovery prompts I should weigh in on ──────────── */}
        {recovery.length > 0 ? (
          <Section title="Recovery requests for you" colors={colors}>
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              Someone you vouched for is trying to recover their account.
              Only confirm if you're sure it's really them.
            </Text>
            {recovery.map((r) => (
              <RecoveryRow
                key={r.id}
                req={r}
                colors={colors}
                onDecide={(decision) => decide.mutate({ id: r.id, decision })}
                busy={decide.isPending}
              />
            ))}
          </Section>
        ) : null}
      </ScrollView>
    </View>
  );
}

type Colors = ReturnType<typeof useColors>;

function Section({
  title,
  colors,
  children,
}: {
  title: string;
  colors: Colors;
  children: React.ReactNode;
}) {
  return (
    <View
      style={[
        styles.section,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text style={[styles.sectionTitle, { color: colors.mutedForeground }]}>
        {title}
      </Text>
      {children}
    </View>
  );
}

function ContactRow({
  contact,
  colors,
  onRevoke,
  disabled,
}: {
  contact: TrustedContact;
  colors: Colors;
  onRevoke: () => void;
  disabled: boolean;
}) {
  return (
    <View style={[styles.row, { borderColor: colors.border }]}>
      <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
        <Feather name="user" size={16} color={colors.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
          {contact.contact_name ||
            (contact.contact_handle ? `@${contact.contact_handle}` : `User ${contact.contact_user_id}`)}
        </Text>
        <Text style={[styles.meta, { color: colors.mutedForeground }]} numberOfLines={1}>
          {STATUS_LABEL[contact.status]}
        </Text>
      </View>
      {contact.status !== "revoked" ? (
        <Pressable
          onPress={onRevoke}
          disabled={disabled}
          hitSlop={8}
          accessibilityLabel="Remove contact"
        >
          <Feather name="x" size={18} color={colors.mutedForeground} />
        </Pressable>
      ) : null}
    </View>
  );
}

function InvitationRow({
  invite,
  colors,
  onAccept,
  onDecline,
  busy,
}: {
  invite: TrustedContactInvitation;
  colors: Colors;
  onAccept: () => void;
  onDecline: () => void;
  busy: boolean;
}) {
  return (
    <View style={[styles.row, { borderColor: colors.border, alignItems: "flex-start" }]}>
      <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
        <Feather name="shield" size={16} color={colors.primary} />
      </View>
      <View style={{ flex: 1, gap: 6 }}>
        <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
          {invite.owner_name ||
            (invite.owner_handle ? `@${invite.owner_handle}` : `User ${invite.owner_user_id}`)}
        </Text>
        <Text style={[styles.meta, { color: colors.mutedForeground }]}>
          Invited {new Date(invite.invited_at).toLocaleDateString()}
        </Text>
        <View style={{ flexDirection: "row", gap: 8 }}>
          <Button label="Accept" onPress={onAccept} disabled={busy} />
          <Button
            label="Decline"
            variant="outline"
            onPress={onDecline}
            disabled={busy}
          />
        </View>
      </View>
    </View>
  );
}

function RecoveryRow({
  req,
  colors,
  onDecide,
  busy,
}: {
  req: RecoveryRequest;
  colors: Colors;
  onDecide: (d: "confirmed" | "denied") => void;
  busy: boolean;
}) {
  const settled = !!req.my_confirmation || req.status !== "pending";
  return (
    <View style={[styles.row, { borderColor: colors.border, alignItems: "flex-start" }]}>
      <View style={[styles.avatar, { backgroundColor: colors.primary + "1c" }]}>
        <Feather name="alert-circle" size={16} color={colors.primary} />
      </View>
      <View style={{ flex: 1, gap: 6 }}>
        <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
          {req.owner_name ||
            (req.owner_handle ? `@${req.owner_handle}` : `User ${req.owner_user_id}`)}
        </Text>
        {req.reason ? (
          <Text style={[styles.meta, { color: colors.foreground }]}>
            "{req.reason}"
          </Text>
        ) : null}
        <Text style={[styles.meta, { color: colors.mutedForeground }]}>
          {req.confirmations_received}/{req.confirmations_required} confirmations
          {" · expires "}
          {new Date(req.expires_at).toLocaleString()}
        </Text>
        {settled ? (
          <Text style={[styles.meta, { color: colors.mutedForeground }]}>
            {req.my_confirmation
              ? `You ${req.my_confirmation === "confirmed" ? "confirmed" : "denied"} this.`
              : `Status: ${req.status}`}
          </Text>
        ) : (
          <View style={{ flexDirection: "row", gap: 8 }}>
            <Button
              label="It's really them"
              onPress={() => onDecide("confirmed")}
              disabled={busy}
            />
            <Button
              label="Deny"
              variant="outline"
              onPress={() => onDecide("denied")}
              disabled={busy}
            />
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  intro: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  section: { padding: 14, borderWidth: 1, gap: 10 },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  meta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, lineHeight: 17 },
  empty: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
});
