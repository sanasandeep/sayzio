import { Stack, useLocalSearchParams } from "expo-router";

import { SettingsForm } from "@/components/SettingsForm";

export default function AppearanceSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Appearance" }} />
      <SettingsForm
        linkId={Number(id)}
        group="appearance"
        blurb="Visual theme of your biolink page."
        fields={[
          {
            key: "theme",
            label: "Theme",
            kind: "choice",
            options: ["light", "dark", "auto"],
          },
          { key: "background_color", label: "Background color", hint: "#hex" },
          { key: "text_color", label: "Text color", hint: "#hex" },
          { key: "accent_color", label: "Accent color", hint: "#hex" },
          { key: "background_image", label: "Background image URL", kind: "url" },
        ]}
      />
    </>
  );
}
