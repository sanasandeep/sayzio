import { Stack, useLocalSearchParams } from "expo-router";

import { DesignLockGate } from "@/components/DesignLockGate";
import { SettingsForm } from "@/components/SettingsForm";

export default function LayoutSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Layout" }} />
      <DesignLockGate linkId={Number(id)}>
      <SettingsForm
        linkId={Number(id)}
        group="layout"
        blurb="How your blocks are arranged on the page."
        fields={[
          {
            key: "mode",
            label: "Mode",
            kind: "choice",
            options: ["stack", "grid", "compact"],
          },
          {
            key: "alignment",
            label: "Alignment",
            kind: "choice",
            options: ["left", "center", "right"],
          },
          { key: "spacing", label: "Block spacing (px)" },
          { key: "max_width", label: "Max content width (px)" },
          { key: "show_avatar", label: "Show avatar", kind: "switch" },
          { key: "show_name", label: "Show display name", kind: "switch" },
        ]}
      />
      </DesignLockGate>
    </>
  );
}
