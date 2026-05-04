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
import {
  getBackupCodeStatus,
  getSecuritySettings,
  listPendingSensitiveChanges,
  listRecoveryRequests,
  listTrustedContactInvitations,
  listTrustedContacts,
} from "@/lib/api/security";

type Row = {
  href:
    | "/security/two-factor"
    | "/security/backup-codes"
    | "/security/trusted-contacts"
    | "/security/cool-off";
  icon: keyof typeof Feather.glyphMap;
  title: string;
  body: string;
  badge?: string | null;
};

export default function SecurityHub() {
  const colors = useColors();
  const router = useRouter();

  // Fetch lightweight summaries up front so each row can show a status
  // hint ("3 codes left", "2 pending"). Failures are non-fatal — the
  // row just renders without its badge.
  const codesQ = useQuery({
    queryKey: ["security", "backup-codes"],
    queryFn: getBackupCodeStatus,
    retry: false,
  });
  const contactsQ = useQuery({
    queryKey: ["security", "trusted-contacts"],
    queryFn: listTrustedContacts,
    retry: false,
  });
  const invitesQ = useQuery({
    queryKey: ["security", "trusted-contact-invitations"],
    queryFn: listTrustedContactInvitations,
    retry: false,
  });
  const recoveryQ = useQuery({
    queryKey: ["security", "recovery-requests"],
    queryFn: listRecoveryRequests,
    retry: false,
  });
  const pendingQ = useQuery({
    queryKey: ["security", "pending-changes"],
    queryFn: listPendingSensitiveChanges,
    retry: false,
  });
  const settingsQ = useQuery({
    queryKey: ["security", "settings"],
    queryFn: getSecuritySettings,
    retry: false,
  });

  const codes = codesQ.data;
  const contacts = contactsQ.data ?? [];
  const invites = invitesQ.data ?? [];
  const recovery = recoveryQ.data ?? [];
  const pending = pendingQ.data ?? [];
  const coolOffHours = settingsQ.data?.cool_off_hours ?? 24;

  const activeContacts = contacts.filter((c) => c.status === "active").length;
  const pendingContacts = contacts.filter((c) => c.status === "pending").length;
  const pendingActions =
    invites.length +
    recovery.filter((r) => r.status === "pending" && !r.my_confirmation).length;

  const rows: Row[] = [
    {
      href: "/security/two-factor",
      icon: "shield",
      title: "Two-factor authentication",
      body: codes?.enabled
        ? "On — every new device has to enter a code from your authenticator app."
        : "Add an authenticator app so a stolen password isn't enough to get in.",
      badge: codes ? (codes.enabled ? "On" : "Off") : null,
    },
    {
      href: "/security/backup-codes",
      icon: "key",
      title: "Backup codes",
      body: codes?.enabled
        ? "Single-use codes for when you can't reach your second factor."
        : "Turn on two-factor authentication to generate backup codes.",
      badge: codes?.enabled
        ? `${codes.remaining}/${codes.total} left`
        : codes
          ? "2FA off"
          : null,
    },
    {
      href: "/security/trusted-contacts",
      icon: "users",
      title: "Trusted contacts",
      body: "Pick up to a few 1INME friends who can vouch for you if you ever lose access.",
      badge:
        pendingActions > 0
          ? `${pendingActions} need you`
          : activeContacts > 0
            ? `${activeContacts} active`
            : pendingContacts > 0
              ? `${pendingContacts} invited`
              : null,
    },
    {
      href: "/security/cool-off",
      icon: "clock",
      title: "Cooling-off period",
      body: `Email and password changes wait ${coolOffHours}h before they take effect, and we email the old address a one-tap cancel link.`,
      badge:
        pending.length > 0
          ? `${pending.length} pending`
          : `${coolOffHours}h delay`,
    },
  ];

  const loading =
    codesQ.isLoading ||
    contactsQ.isLoading ||
    invitesQ.isLoading ||
    recoveryQ.isLoading ||
    pendingQ.isLoading ||
    settingsQ.isLoading;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Security" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 14, paddingBottom: 40 }}>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Three layers protect your account from takeover even if someone gets
          your password or email. Pick the ones that fit you.
        </Text>

        {loading ? (
          <View style={{ paddingVertical: 24, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : null}

        {rows.map((r) => (
          <Pressable
            key={r.href}
            onPress={() => router.push(r.href as never)}
            style={({ pressed }) => [
              styles.row,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
                opacity: pressed ? 0.7 : 1,
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel={r.title}
          >
            <View
              style={[
                styles.iconWrap,
                { backgroundColor: colors.primary + "1c" },
              ]}
            >
              <Feather name={r.icon} size={18} color={colors.primary} />
            </View>
            <View style={{ flex: 1, gap: 4 }}>
              <View style={styles.titleRow}>
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {r.title}
                </Text>
                {r.badge ? (
                  <View
                    style={[
                      styles.badge,
                      { backgroundColor: colors.primary + "22" },
                    ]}
                  >
                    <Text
                      style={[styles.badgeText, { color: colors.primary }]}
                    >
                      {r.badge}
                    </Text>
                  </View>
                ) : null}
              </View>
              <Text style={[styles.body, { color: colors.mutedForeground }]}>
                {r.body}
              </Text>
            </View>
            <Feather
              name="chevron-right"
              size={18}
              color={colors.mutedForeground}
            />
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  intro: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    padding: 16,
    borderWidth: 1,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  titleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flexWrap: "wrap",
  },
  title: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
  },
  badge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
});
