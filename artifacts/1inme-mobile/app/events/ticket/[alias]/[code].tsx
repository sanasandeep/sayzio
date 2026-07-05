import { useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from "react-native";
import { SvgXml } from "react-native-svg";

import { useColors } from "@/hooks/useColors";
import { type EventTicket, getEventTicket } from "@/lib/api/events";

export default function TicketViewScreen() {
  const { alias, code } = useLocalSearchParams<{ alias: string; code: string }>();
  const colors = useColors();
  const [ticket, setTicket] = useState<(EventTicket & { qr_svg: string }) | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!alias || !code) return;
    getEventTicket(alias, code)
      .then(setTicket)
      .catch(() => setError("This ticket could not be found."))
      .finally(() => setLoading(false));
  }, [alias, code]);

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (error || !ticket) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Text style={{ color: colors.mutedForeground }}>{error ?? "Ticket not found."}</Text>
      </View>
    );
  }

  const statusColor =
    ticket.status === "checked_in"
      ? colors.primary
      : ticket.status === "valid"
        ? colors.foreground
        : colors.destructive;

  return (
    <ScrollView contentContainerStyle={[styles.wrap, { backgroundColor: colors.background }]}>
      <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <Text style={[styles.eventTitle, { color: colors.foreground }]}>
          {ticket.event?.title ?? "Event"}
        </Text>
        {ticket.event?.start_date ? (
          <Text style={{ color: colors.mutedForeground }}>
            {new Date(ticket.event.start_date).toLocaleString()}
          </Text>
        ) : null}
        {ticket.event?.location ? (
          <Text style={{ color: colors.mutedForeground }}>📍 {ticket.event.location}</Text>
        ) : null}

        <View style={styles.qrWrap}>
          <SvgXml xml={ticket.qr_svg} width={220} height={220} />
        </View>

        <Text style={[styles.code, { color: colors.foreground }]}>{ticket.code}</Text>
        <Text style={{ color: statusColor, fontWeight: "700", textTransform: "capitalize" }}>
          {ticket.status.replace("_", " ")}
        </Text>

        <View style={styles.divider} />
        <Text style={{ color: colors.mutedForeground }}>Ticket: {ticket.tier?.name}</Text>
        <Text style={{ color: colors.mutedForeground }}>Attendee: {ticket.attendee_name}</Text>
        <Text style={{ color: colors.mutedForeground }}>Quantity: {ticket.quantity}</Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  wrap: { flexGrow: 1, alignItems: "center", justifyContent: "center", padding: 20 },
  card: {
    width: "100%",
    maxWidth: 360,
    borderWidth: 1,
    borderRadius: 20,
    padding: 24,
    alignItems: "center",
    gap: 4,
  },
  eventTitle: { fontSize: 18, fontWeight: "800", textAlign: "center" },
  qrWrap: { marginVertical: 20 },
  code: { fontSize: 15, fontWeight: "700", letterSpacing: 1, marginTop: 4 },
  divider: {
    height: 1,
    alignSelf: "stretch",
    backgroundColor: "rgba(128,128,128,0.2)",
    marginVertical: 12,
  },
});
