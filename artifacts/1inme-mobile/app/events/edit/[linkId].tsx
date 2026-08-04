import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EventForm } from "@/components/EventForm";
import { useColors } from "@/hooks/useColors";
import {
  cancelEvent,
  getOwnerEvent,
  reactivateEvent,
  updateEvent,
  type EventInput,
} from "@/lib/api/events";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import { showAlert } from "@/lib/webAlert";

export default function EditEventScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { linkId } = useLocalSearchParams<{ linkId: string }>();
  const id = Number(linkId);
  const [notifyGuests, setNotifyGuests] = useState(true);

  const q = useQuery({
    queryKey: ["owner-event", id],
    queryFn: () => getOwnerEvent(id),
    enabled: Number.isFinite(id),
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["owner-event", id] });
    qc.invalidateQueries({ queryKey: ["links"] });
    qc.invalidateQueries({ queryKey: ["my-events"] });
  };

  const save = useMutation({
    mutationFn: (payload: EventInput) => updateEvent(id, payload),
    onSuccess: () => {
      invalidate();
      router.back();
    },
    onError: (e) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't save event",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    },
  });

  const cancel = useMutation({
    mutationFn: () => cancelEvent(id, notifyGuests),
    onSuccess: (res) => {
      invalidate();
      if (res.broadcast_skipped) {
        // The event IS cancelled — only the guest notice couldn't go out.
        // Point the organizer at the broadcast screen to send it manually.
        showAlert(
          "Event cancelled",
          `Guests were not notified: ${res.broadcast_message ?? "rate limit reached"}. You can send the cancellation notice from the broadcast screen.`,
          [
            { text: "Not now", style: "cancel" },
            {
              text: "Open broadcast",
              onPress: () => router.push(`/events/broadcast/${id}`),
            },
          ],
        );
        return;
      }
      const notified = res.notified_count;
      showAlert(
        "Event cancelled",
        notified && notified > 0
          ? `Notified ${notified} guest${notified === 1 ? "" : "s"}.`
          : "Your event has been cancelled.",
      );
    },
    onError: (e) => {
      showAlert(
        "Couldn't cancel event",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    },
  });

  const reactivate = useMutation({
    mutationFn: () => reactivateEvent(id),
    onSuccess: () => {
      invalidate();
      showAlert("Event reactivated", "Your event is live again.");
    },
    onError: (e) => {
      showAlert(
        "Couldn't reactivate event",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    },
  });

  const confirmCancel = () => {
    showAlert(
      "Cancel this event?",
      notifyGuests
        ? "This marks the event as cancelled and emails all guests a cancellation notice. You can reactivate it later."
        : "This marks the event as cancelled. You can reactivate it later.",
      [
        { text: "Keep event", style: "cancel" },
        {
          text: "Cancel event",
          style: "destructive",
          onPress: () => cancel.mutate(),
        },
      ],
    );
  };

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit details" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !q.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background, gap: 16 }]}>
        <Stack.Screen options={{ title: "Edit details", headerBackTitle: "Back" }} />
        <Text style={[styles.errorText, { color: colors.mutedForeground }]}>
          This event couldn't be loaded.
        </Text>
        <Button label="Go back" variant="outline" onPress={() => router.back()} />
      </View>
    );
  }

  const cancelled = q.data.cancelled;
  const busy = cancel.isPending || reactivate.isPending;

  // Task #6687 — host access to the Connect QR: a scan-to-connect code
  // guests can scan to sign in, RSVP "yes" and follow in one step.
  const connectQrSection = (
    <View
      style={[
        styles.danger,
        { borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <Text style={[styles.dangerTitle, { color: colors.foreground }]}>
        Connect QR
      </Text>
      <Text style={[styles.dangerHint, { color: colors.mutedForeground }]}>
        A special QR for the door or your invites: guests who scan it verify
        one code, get RSVP'd "yes" and connect with you automatically.
      </Text>
      <Button
        label="View & share the Connect QR"
        variant="outline"
        onPress={() =>
          router.push({
            pathname: "/events/connect-qr/[linkId]",
            params: { linkId: String(id) },
          })
        }
      />
    </View>
  );

  const dangerSection = (
    <View
      style={[
        styles.danger,
        { borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      {cancelled ? (
        <>
          <View style={styles.cancelledBadgeRow}>
            <View style={[styles.badge, { backgroundColor: colors.destructive }]}>
              <Text
                style={[styles.badgeText, { color: colors.destructiveForeground }]}
              >
                Cancelled
              </Text>
            </View>
          </View>
          <Text style={[styles.dangerHint, { color: colors.mutedForeground }]}>
            This event is cancelled. Guests see a cancellation banner and RSVP /
            ticket sales are closed. You can bring it back live.
          </Text>
          <Button
            label="Reactivate event"
            variant="outline"
            loading={reactivate.isPending}
            disabled={busy}
            onPress={() => reactivate.mutate()}
          />
        </>
      ) : (
        <>
          <Text style={[styles.dangerTitle, { color: colors.destructive }]}>
            Cancel event
          </Text>
          <Text style={[styles.dangerHint, { color: colors.mutedForeground }]}>
            Mark this event as cancelled. Guests see a cancellation banner and
            RSVP / ticket sales are closed. You can reactivate it later.
          </Text>
          <View style={styles.toggleRow}>
            <View style={{ flex: 1 }}>
              <Text style={[styles.toggleTitle, { color: colors.foreground }]}>
                Notify all guests
              </Text>
              <Text style={[styles.toggleHint, { color: colors.mutedForeground }]}>
                Email everyone who RSVP'd a cancellation notice.
              </Text>
            </View>
            <Switch
              value={notifyGuests}
              onValueChange={setNotifyGuests}
              trackColor={{ true: colors.primary }}
            />
          </View>
          <Button
            label="Cancel event"
            variant="outline"
            loading={cancel.isPending}
            disabled={busy}
            onPress={confirmCancel}
            style={{ borderColor: colors.destructive }}
          />
        </>
      )}
    </View>
  );

  return (
    <>
      <Stack.Screen options={{ title: "Edit details", headerBackTitle: "Back" }} />
      <EventForm
        initial={q.data}
        submitLabel="Save changes"
        saving={save.isPending}
        onSubmit={(payload) => save.mutate(payload)}
        footer={
          <>
            {connectQrSection}
            {dangerSection}
          </>
        }
      />
    </>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  errorText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    textAlign: "center",
    lineHeight: 21,
  },
  danger: {
    borderWidth: 1,
    padding: 16,
    gap: 12,
  },
  dangerTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
  },
  dangerHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  toggleTitle: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
  toggleHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 2,
  },
  cancelledBadgeRow: {
    flexDirection: "row",
  },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
  },
});
