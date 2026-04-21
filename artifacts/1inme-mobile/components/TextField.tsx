import { useState } from "react";
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
} from "react-native";

import { useColors } from "@/hooks/useColors";

export function TextField({
  label,
  hint,
  error,
  ...props
}: TextInputProps & { label?: string; hint?: string; error?: string }) {
  const colors = useColors();
  const [focused, setFocused] = useState(false);

  return (
    <View style={styles.wrap}>
      {label ? (
        <Text style={[styles.label, { color: colors.mutedForeground }]}>
          {label}
        </Text>
      ) : null}
      <TextInput
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
          props.style,
        ]}
      />
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
  input: {
    minHeight: 52,
    paddingHorizontal: 16,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 16,
    borderWidth: 1,
  },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
});
