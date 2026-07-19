import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { useRef } from "react";
import {
  ActivityIndicator,
  Pressable,
  Text,
  type StyleProp,
  type ViewStyle,
} from "react-native";

import { insufficientCoins } from "@/components/CoinCostHint";
import { useColors } from "@/hooks/useColors";
import { useVoiceDictation } from "@/hooks/useVoiceDictation";
import { fetchCapabilities } from "@/lib/api/voice";

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
 * Dictation is coin-metered (STT), so the mic follows the shared
 * coin_cost + coin_balance affordability pattern: capabilities (cached
 * via a shared react-query key across every field on screen) expose
 * `dictation_coin_cost` + `coin_balance`, and when the wallet can't
 * cover one clip the mic is disabled with a compact amber warning
 * instead of failing AFTER recording with an insufficient-coins error.
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

  // Shared across every DictationMic on screen (same query key), so a
  // form with many fields still fetches capabilities once. Fails open —
  // no data means no gate, mirroring the Voice Assistant's fail-open probe.
  const caps = useQuery({
    queryKey: ["voice-capabilities"],
    queryFn: fetchCapabilities,
    staleTime: 60_000,
    retry: false,
  });
  const short = insufficientCoins(
    caps.data?.dictation_coin_cost,
    caps.data?.coin_balance,
  );

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
      // Coin gate only blocks STARTING a clip — if capabilities resolve to
      // "insufficient" mid-recording, the user must still be able to stop.
      disabled={disabled || dict.busy || (short && !dict.recording)}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityState={{
        disabled: disabled || dict.busy || (short && !dict.recording),
      }}
      accessibilityLabel={
        short
          ? "Dictation unavailable: not enough coins"
          : dict.recording
            ? "Stop dictation"
            : "Dictate: tap or hold to talk"
      }
      style={[{ flexDirection: "row", alignItems: "center", gap: 6 }, style]}
    >
      {short ? (
        <Text
          numberOfLines={1}
          testID="text-insufficient-coins"
          style={{
            fontSize: Math.max(10, size - 7),
            fontWeight: "600",
            color: "#fbbf24",
          }}
        >
          Not enough coins
        </Text>
      ) : null}
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
          name={short ? "mic-off" : "mic"}
          size={size}
          color={dict.recording ? "#dc2626" : colors.mutedForeground}
        />
      )}
    </Pressable>
  );
}
