import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Stack, router } from "expo-router";

import { EventForm } from "@/components/EventForm";
import { createEvent, type EventInput } from "@/lib/api/events";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import { showAlert } from "@/lib/webAlert";

export default function CreateEventScreen() {
  const qc = useQueryClient();

  const save = useMutation({
    mutationFn: (payload: EventInput) => createEvent(payload),
    onSuccess: (ev) => {
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["my-events"] });
      // Land on the event's organizer surface so tiers/check-in are one tap away.
      router.replace(`/events/tiers/${ev.id}`);
    },
    onError: (e) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't create event",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    },
  });

  return (
    <>
      <Stack.Screen options={{ title: "Create event", headerBackTitle: "Back" }} />
      <EventForm
        submitLabel="Create event"
        saving={save.isPending}
        onSubmit={(payload) => save.mutate(payload)}
      />
    </>
  );
}
