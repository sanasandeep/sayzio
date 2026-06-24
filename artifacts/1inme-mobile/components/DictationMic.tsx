import { Feather } from "@expo/vector-icons";
import { ActivityIndicator, Pressable, type StyleProp, type ViewStyle } from "react-native";

import { useColors } from "@/hooks/useColors";
import { useVoiceDictation } from "@/hooks/useVoiceDictation";

/**
 * Reusable mic affordance for any text field. Wraps `useVoiceDictation`
 * (the plan-gated / metered STT-only flow) and renders a tap-to-toggle
 * mic that pipes the transcript back via `onText`. Mirrors the web's
 * per-field `l({ onText })` Alpine factory so dictation works the same
 * everywhere users type, not just in Ask Coach.
 *
 *   <DictationMic onText={(t) => setTitle((v) => (v ? v.trim() + " " : "") + t)} />
 */
export function DictationMic({
  onText,
  disabled,
  size = 18,
  style,
}: {
  onText: (text: string) => void;
  disabled?: boolean;
  size?: number;
  style?: StyleProp<ViewStyle>;
}) {
  const colors = useColors();
  const dict = useVoiceDictation(onText);

  return (
    <Pressable
      onPress={dict.toggle}
      disabled={disabled || dict.busy}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityLabel={dict.recording ? "Stop dictation" : "Dictate"}
      style={style}
    >
      {dict.busy ? (
        <ActivityIndicator size="small" color={colors.mutedForeground} />
      ) : (
        <Feather
          name="mic"
          size={size}
          color={dict.recording ? "#dc2626" : colors.mutedForeground}
        />
      )}
    </Pressable>
  );
}
