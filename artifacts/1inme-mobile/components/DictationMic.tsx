import { Feather } from "@expo/vector-icons";
import { useRef } from "react";
import {
  ActivityIndicator,
  Pressable,
  Text,
  type StyleProp,
  type ViewStyle,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { useVoiceDictation } from "@/hooks/useVoiceDictation";

/**
 * Reusable mic affordance for any text field. Wraps `useVoiceDictation`
 * (the plan-gated / metered STT-only flow) and pipes the transcript back
 * via `onText`. Mirrors the web's per-field `l({ onText })` Alpine factory
 * so dictation works the same everywhere users type, not just in Ask Coach.
 *
 * Two gestures, matching the Voice Assistant's mic:
 *   - tap to toggle (tap once to start, tap again to send), or
 *   - press-and-hold to record, release to transcribe (push-to-talk) —
 *     natural when holding the phone to your ear.
 * A long press fires `onLongPress` and suppresses `onPress`, so the two
 * paths never collide. While recording, a subtle inline "listening…"
 * label appears next to the mic.
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
  const heldRef = useRef(false);

  return (
    <Pressable
      onPress={() => {
        // Only quick taps reach here — a long press fires onLongPress and
        // suppresses onPress — so this stays a pure tap-to-toggle.
        dict.toggle();
      }}
      onLongPress={() => {
        heldRef.current = true;
        void dict.start();
      }}
      onPressOut={() => {
        // Release after a press-and-hold → transcribe what we captured.
        if (heldRef.current) {
          heldRef.current = false;
          void dict.stopAndSend();
        }
      }}
      delayLongPress={200}
      disabled={disabled || dict.busy}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityLabel={
        dict.recording ? "Stop dictation" : "Dictate — tap or hold to talk"
      }
      style={[{ flexDirection: "row", alignItems: "center", gap: 6 }, style]}
    >
      {dict.recording ? (
        <Text
          numberOfLines={1}
          style={{
            fontSize: Math.max(11, size - 6),
            fontWeight: "600",
            color: "#dc2626",
            backgroundColor: colors.background,
            paddingHorizontal: 4,
            borderRadius: 4,
            overflow: "hidden",
          }}
        >
          listening…
        </Text>
      ) : null}
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
