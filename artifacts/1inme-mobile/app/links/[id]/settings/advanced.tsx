import { Stack, useLocalSearchParams } from "expo-router";

import { SettingsForm } from "@/components/SettingsForm";

export default function AdvancedSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Advanced" }} />
      <SettingsForm
        linkId={Number(id)}
        group="advanced"
        blurb="Tracking, integrations, and access control."
        fields={[
          { key: "ga_id", label: "Google Analytics ID", hint: "G-XXXXX" },
          { key: "fb_pixel", label: "Meta pixel ID" },
          { key: "tt_pixel", label: "TikTok pixel ID" },
          { key: "custom_head", label: "Custom <head> HTML", kind: "multiline" },
          {
            key: "custom_footer",
            label: "Custom footer HTML",
            kind: "multiline",
          },
          { key: "password", label: "Password", hint: "Leave blank to remove" },
          { key: "redirect_url", label: "Forward to URL", kind: "url" },
          { key: "noindex", label: "Hide from search engines", kind: "switch" },
        ]}
      />
    </>
  );
}
