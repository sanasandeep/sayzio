import * as DocumentPicker from "expo-document-picker";
import { Text, View } from "react-native";
import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { showAlert } from "@/lib/webAlert";
export type EvidenceFile = {
  uri: string;
  name: string;
  mimeType?: string | null;
  size?: number;
};
export function EvidenceFiles({
  files,
  onChange,
}: {
  files: EvidenceFile[];
  onChange: (files: EvidenceFile[]) => void;
}) {
  const colors = useColors();
  async function choose() {
    try {
      const result = await DocumentPicker.getDocumentAsync({
        type: ["image/*", "application/pdf"],
        multiple: true,
        copyToCacheDirectory: true,
      });
      if (!result.canceled) {
        if (files.length + result.assets.length > 5)
          throw new Error("Attach up to five evidence files.");
        onChange([...files, ...result.assets]);
      }
    } catch (e) {
      showAlert(
        "Could not select evidence",
        e instanceof Error ? e.message : "Try again.",
      );
    }
  }
  return (
    <View style={{ gap: 8 }}>
      <Button
        label="Attach evidence (images or PDF)"
        variant="secondary"
        disabled={files.length >= 5}
        onPress={() => {
          void choose();
        }}
      />
      {files.map((f, i) => (
        <View key={`${f.uri}-${i}`} style={{ gap: 4 }}>
          <Text style={{ color: colors.foreground }}>{f.name}</Text>
          <Button
            label={`Remove ${f.name}`}
            variant="secondary"
            onPress={() => onChange(files.filter((_, index) => index !== i))}
          />
        </View>
      ))}
    </View>
  );
}
