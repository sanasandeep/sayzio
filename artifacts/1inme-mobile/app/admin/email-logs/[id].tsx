import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmailPreviewBox } from "@/components/EmailPreviewBox";
import { useColors } from "@/hooks/useColors";
import { getEmailLog, resendEmailLog } from "@/lib/api/email";

import { statusColor } from "../email-logs";

// Super-admin parity for viewing one email-log row in full and resending it.
// The resend runs through the same central pipeline (throttled server-side);
// rows with no stored content can't be resent (422). Gated behind
// `settings.manage`.

export default function EmailLogDetailScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: rawId } = useLocalSearchParams<{ id: string }>();
  const id = Number(rawId);

  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ["admin-email-log", id],
    queryFn: () => getEmailLog(id),
    enabled: Number.isFinite(id),
  });

  const resend = useMutation({
    mutationFn: () => resendEmailLog(id),
    onSuccess: (r) => {
      setNotice(`Resent to ${r.resent_to}.`);
      setError(null);
      qc.invalidateQueries({ queryKey: ["admin-email-logs"] });
    },
    onError: (e: any) => {
      setNotice(null);
      setError(e?.message ?? "Couldn't resend this email.");
    },
  });

  const l = query.data;
  const canResend = !!l && !(!l.body && !l.subject);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Email", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to view this email."
                : "Couldn't load this email."}
            </Text>
          </View>
        ) : l ? (
          <>
            {/* Meta */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.metaRow}>
                <Text style={[styles.metaKey, { color: colors.mutedForeground }]}>To</Text>
                <Text style={[styles.metaVal, { color: colors.foreground }]} selectable>
                  {l.recipient}
                </Text>
              </View>
              <View style={styles.metaRow}>
                <Text style={[styles.metaKey, { color: colors.mutedForeground }]}>Status</Text>
                <View style={styles.statusWrap}>
                  <View style={[styles.statusDot, { backgroundColor: statusColor(l.status, colors) }]} />
                  <Text style={{ color: statusColor(l.status, colors), fontFamily: "SpaceGrotesk_600SemiBold" }}>
                    {l.status}
                    {l.is_resend ? " · resent" : ""}
                  </Text>
                </View>
              </View>
              {l.category ? (
                <View style={styles.metaRow}>
                  <Text style={[styles.metaKey, { color: colors.mutedForeground }]}>Category</Text>
                  <Text style={[styles.metaVal, { color: colors.foreground }]}>{l.category}</Text>
                </View>
              ) : null}
              {l.email_key ? (
                <View style={styles.metaRow}>
                  <Text style={[styles.metaKey, { color: colors.mutedForeground }]}>Template</Text>
                  <Text style={[styles.metaVal, { color: colors.foreground }]} selectable>
                    {l.email_key}
                  </Text>
                </View>
              ) : null}
              {l.user ? (
                <View style={styles.metaRow}>
                  <Text style={[styles.metaKey, { color: colors.mutedForeground }]}>User</Text>
                  <Text style={[styles.metaVal, { color: colors.foreground }]}>
                    {l.user.name} ({l.user.email})
                  </Text>
                </View>
              ) : null}
              {l.created_at ? (
                <View style={styles.metaRow}>
                  <Text style={[styles.metaKey, { color: colors.mutedForeground }]}>Sent</Text>
                  <Text style={[styles.metaVal, { color: colors.foreground }]}>
                    {new Date(l.created_at).toLocaleString()}
                  </Text>
                </View>
              ) : null}
              {l.error ? (
                <View style={[styles.errorBox, { backgroundColor: colors.destructive + "12", borderColor: colors.destructive }]}>
                  <Feather name="alert-circle" size={15} color={colors.destructive} />
                  <Text style={{ color: colors.foreground, flex: 1, fontSize: 12 }}>{l.error}</Text>
                </View>
              ) : null}
            </View>

            {/* Resend */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Button
                label={`Resend to ${l.recipient}`}
                onPress={() => resend.mutate()}
                loading={resend.isPending}
                disabled={!canResend}
                leading={<Feather name="send" size={16} color={colors.primaryForeground} />}
              />
              {!canResend ? (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  This row has no stored content to resend.
                </Text>
              ) : null}
              {notice ? (
                <View style={[styles.resultBox, { backgroundColor: colors.success + "15", borderColor: colors.success }]}>
                  <Feather name="check-circle" size={16} color={colors.success} />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{notice}</Text>
                </View>
              ) : null}
              {error ? (
                <View style={[styles.resultBox, { backgroundColor: colors.destructive + "15", borderColor: colors.destructive }]}>
                  <Feather name="alert-circle" size={16} color={colors.destructive} />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{error}</Text>
                </View>
              ) : null}
            </View>

            {/* Content */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Content</Text>
              <EmailPreviewBox
                subject={l.subject ?? ""}
                body={l.body ?? ""}
                format={l.format ?? "html"}
              />
            </View>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 10 },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  note: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 17 },
  metaRow: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  metaKey: { width: 76, fontSize: 12, fontFamily: "SpaceGrotesk_600SemiBold", textTransform: "uppercase" },
  metaVal: { flex: 1, fontSize: 13, fontFamily: "SpaceGrotesk_500Medium" },
  statusWrap: { flexDirection: "row", alignItems: "center", gap: 6, flex: 1 },
  statusDot: { width: 8, height: 8, borderRadius: 999 },
  errorBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 10,
    borderWidth: 1,
    borderRadius: 10,
  },
  resultBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
