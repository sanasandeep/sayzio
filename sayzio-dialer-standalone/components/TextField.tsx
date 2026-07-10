import { useState } from "react";
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";

export function TextField({
  label,
  hint,
  error,
  trailing,
  ...props
}: TextInputProps & {
  label?: string;
  hint?: string;
  error?: string;
  /**
   * Optional accessory pinned to the top-right inside the input box —
   * used to host a `DictationMic` so any field can be spoken into.
   */
  trailing?: React.ReactNode;
}) {
  const colors = useColors();
  const [focused, setFocused] = useState(false);

  return (
    <View style={styles.wrap}>
      {label ? (
        <Text style={[styles.label, { color: colors.mutedForeground }]}>
          {label}
        </Text>
      ) : null}
      <View style={styles.inputWrap}>
        <TextInput
          {...WEB_FOCUS_RING_PROPS}
          placeholderTextColor={colors.mutedForeground}
          {...props}
          onFocus={(e) => {
            setFocused(true);
            props.onFocus?.(e);
          }}
          onBlur={(e) => {
            setFocused(false);
            props.onBlur?.(e);
          }}
          style={[
            styles.input,
            {
              color: colors.foreground,
              backgroundColor: colors.card,
              borderColor: error
                ? colors.destructive
                : focused
                  ? colors.primary
                  : colors.border,
              borderRadius: colors.radius,
            },
            trailing ? styles.inputWithTrailing : null,
            props.style,
          ]}
        />
        {trailing ? <View style={styles.trailing}>{trailing}</View> : null}
      </View>
      {error ? (
        <Text style={[styles.hint, { color: colors.destructive }]}>{error}</Text>
      ) : hint ? (
        <Text style={[styles.hint, { color: colors.mutedForeground }]}>
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 6 },
  label: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  inputWrap: { position: "relative" },
  input: {
    minHeight: 52,
    paddingHorizontal: 16,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 16,
    borderWidth: 1,
  },
  inputWithTrailing: { paddingRight: 48 },
  trailing: {
    position: "absolute",
    right: 12,
    top: 0,
    height: 52,
    alignItems: "center",
    justifyContent: "center",
  },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
});
