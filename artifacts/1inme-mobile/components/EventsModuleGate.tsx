import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import type { ReactNode } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import { useFeatureStates } from "@/hooks/useFeatureStates";

/**
 * Platform-wide Events module gate (Task #6729). The API 404s every events
 * endpoint when the admin switches the Events module off, so every event
 * screen wraps its content in this gate: when the module is reported off,
 * a graceful "not available" state renders instead of raw request errors.
 *
 * Fails OPEN — while the feature-state overview is loading (or unreachable)
 * the children render normally, mirroring useFeatureStates' fail-open rule,
 * so a working module is never hidden behind a false negative.
 */
export function EventsModuleGate({ children }: { children: ReactNode }) {
  const colors = useColors();
  const router = useRouter();
  const { eventsModuleEnabled } = useFeatureStates();

  if (eventsModuleEnabled) {
    return <>{children}</>;
  }

  return (
    <View style={[styles.wrap, { backgroundColor: colors.background }]}>
      <View
        style={[
          styles.iconCircle,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <Feather name="calendar" size={28} color={colors.mutedForeground} />
      </View>
      <Text style={[styles.title, { color: colors.foreground }]}>
        Events aren't available
      </Text>
      <Text style={[styles.body, { color: colors.mutedForeground }]}>
        Events are currently switched off on this platform. Your event data is
        safe and everything comes back if events are re-enabled.
      </Text>
      <Pressable
        onPress={() => {
          if (router.canGoBack()) {
            router.back();
          } else {
            router.replace("/");
          }
        }}
        style={({ pressed }) => [
          styles.button,
          { backgroundColor: colors.primary, opacity: pressed ? 0.8 : 1 },
        ]}
      >
        <Text style={[styles.buttonLabel, { color: colors.primaryForeground }]}>
          Go back
        </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 32,
    gap: 12,
  },
  iconCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    borderWidth: StyleSheet.hairlineWidth,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  title: {
    fontSize: 18,
    fontWeight: "700",
    textAlign: "center",
  },
  body: {
    fontSize: 14,
    lineHeight: 20,
    textAlign: "center",
    maxWidth: 320,
  },
  button: {
    marginTop: 8,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 999,
  },
  buttonLabel: {
    fontSize: 15,
    fontWeight: "600",
  },
});
