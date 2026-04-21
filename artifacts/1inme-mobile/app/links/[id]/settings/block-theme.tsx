import { Stack, useLocalSearchParams } from "expo-router";

import { SettingsForm } from "@/components/SettingsForm";

export default function BlockThemeSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Block theme" }} />
      <SettingsForm
        linkId={Number(id)}
        group="block_theme"
        blurb="Default appearance applied to every block."
        fields={[
          {
            key: "shape",
            label: "Shape",
            kind: "choice",
            options: ["rounded", "square", "pill"],
          },
          {
            key: "fill",
            label: "Fill",
            kind: "choice",
            options: ["solid", "outline", "ghost"],
          },
          { key: "block_color", label: "Block color", hint: "#hex" },
          { key: "block_text_color", label: "Block text color", hint: "#hex" },
          { key: "border_width", label: "Border width (px)" },
          { key: "shadow", label: "Drop shadow", kind: "switch" },
        ]}
      />
    </>
  );
}
