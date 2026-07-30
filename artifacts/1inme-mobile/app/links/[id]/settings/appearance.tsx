import { Stack, useLocalSearchParams } from "expo-router";

import { BgImageGalleryPicker } from "@/components/BgImageGalleryPicker";
import { BgPresetPicker } from "@/components/BgPresetPicker";
import { BgTemplatePicker } from "@/components/BgTemplatePicker";
import { BiolinkBackgroundPreview } from "@/components/BiolinkBackgroundPreview";
import { DesignLockGate } from "@/components/DesignLockGate";
import { SettingsForm } from "@/components/SettingsForm";

export default function AppearanceSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Appearance" }} />
      <DesignLockGate linkId={Number(id)}>
      <SettingsForm
        linkId={Number(id)}
        group="appearance"
        blurb="Visual theme of your Link in Bio page."
        fields={[
          {
            key: "theme",
            label: "Theme",
            kind: "choice",
            options: ["light", "dark", "auto"],
          },
          { key: "background_color", label: "Background color", hint: "#hex", kind: "color" },
          { key: "text_color", label: "Text color", hint: "#hex", kind: "color" },
          { key: "accent_color", label: "Accent color", hint: "#hex", kind: "color" },
          { key: "background_image", label: "Background image URL", kind: "url" },
        ]}
        extra={
          <>
            <BiolinkBackgroundPreview linkId={Number(id)} />
            <BgImageGalleryPicker linkId={Number(id)} />
            <BgPresetPicker linkId={Number(id)} />
            <BgTemplatePicker linkId={Number(id)} />
          </>
        }
      />
      </DesignLockGate>
    </>
  );
}
