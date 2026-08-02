import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { EventForm } from "@/components/EventForm";
import { useColors } from "@/hooks/useColors";
import {
  getOwnerEvent,
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

  const q = useQuery({
    queryKey: ["owner-event", id],
    queryFn: () => getOwnerEvent(id),
    enabled: Number.isFinite(id),
  });

  const save = useMutation({
    mutationFn: (payload: EventInput) => updateEvent(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["owner-event", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["my-events"] });
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

  return (
    <>
      <Stack.Screen options={{ title: "Edit details", headerBackTitle: "Back" }} />
      <EventForm
        initial={q.data}
        submitLabel="Save changes"
        saving={save.isPending}
        onSubmit={(payload) => save.mutate(payload)}
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
});
