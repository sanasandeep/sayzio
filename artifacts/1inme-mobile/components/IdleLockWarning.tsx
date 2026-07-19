import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";

export function IdleLockWarning() {
  const { lockWarningSecondsRemaining, noteActivity, locked } = useAuth();
  const colors = useColors();
  const insets = useSafeAreaInsets();

  if (locked || lockWarningSecondsRemaining == null) return null;

  const seconds = lockWarningSecondsRemaining;
  const label =
    seconds === 1
      ? "Locking in 1 second. Tap to stay"
      : `Locking in ${seconds} seconds. Tap to stay`;

  return (
    <View
      pointerEvents="box-none"
      style={[styles.wrap, { top: insets.top + 8 }]}
    >
      <Pressable
        onPress={noteActivity}
        accessibilityRole="button"
        accessibilityLabel={label}
        accessibilityHint="Cancels the upcoming auto-lock"
        style={({ pressed }) => [
          styles.banner,
          {
            backgroundColor: colors.foreground,
            opacity: pressed ? 0.85 : 1,
          },
        ]}
      >
        <Text
          style={[
            styles.text,
            { color: colors.background },
          ]}
          numberOfLines={1}
        >
          {label}
        </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    position: "absolute",
    left: 0,
    right: 0,
    alignItems: "center",
    zIndex: 1000,
    elevation: 1000,
  },
  banner: {
    maxWidth: "92%",
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 999,
    shadowColor: "#000",
    shadowOpacity: 0.18,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
  },
  text: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    textAlign: "center",
  },
});
