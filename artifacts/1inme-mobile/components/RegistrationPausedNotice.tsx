import { Ionicons } from "@expo/vector-icons";
import { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Animated,
  Platform,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";

// Default copy mirrors AuthMethods::registrationPausedMessage() on the
// backend so web and mobile speak with one voice. The live server message
// (error.message) wins when present; this is the offline fallback.
export const REGISTRATION_PAUSED_MESSAGE =
  "We're upgrading and aren't accepting new sign-ups right now. If you already have an account, you can still sign in.";

/**
 * Full-screen, on-brand "sign-ups paused" message shown when the backend
 * returns the `registration_paused` error (HTTP 403) on any mobile
 * account-creation path (OTP send/register, social sign-in). Mirrors the
 * web "we're upgrading" upgrade page. Respects the OS reduce-motion setting:
 * the reveal animation is skipped when reduce motion is enabled.
 */
export function RegistrationPausedNotice({
  message,
  onBack,
}: {
  message?: string | null;
  onBack: () => void;
}) {
  const colors = useColors();
  const insets = useSafeAreaInsets();

  const [reduceMotion, setReduceMotion] = useState(false);
  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled().then((on) => {
      if (mounted) setReduceMotion(on);
    });
    const sub = AccessibilityInfo.addEventListener(
      "reduceMotionChanged",
      (on) => setReduceMotion(on),
    );
    return () => {
      mounted = false;
      sub.remove();
    };
  }, []);

  const fade = useRef(new Animated.Value(0)).current;
  const rise = useRef(new Animated.Value(12)).current;
  useEffect(() => {
    if (reduceMotion) {
      fade.setValue(1);
      rise.setValue(0);
      return;
    }
    Animated.parallel([
      Animated.timing(fade, {
        toValue: 1,
        duration: 320,
        useNativeDriver: true,
      }),
      Animated.timing(rise, {
        toValue: 0,
        duration: 320,
        useNativeDriver: true,
      }),
    ]).start();
  }, [reduceMotion, fade, rise]);

  const webTop = Platform.OS === "web" ? 67 : 0;

  return (
    <View
      style={[
        styles.root,
        {
          backgroundColor: colors.background,
          paddingTop: insets.top + 24 + webTop,
          paddingBottom: insets.bottom + 32,
        },
      ]}
    >
      <BrandWordmark size={32} align="center" />

      <Animated.View
        style={[
          styles.center,
          { opacity: fade, transform: [{ translateY: rise }] },
        ]}
      >
        <View
          style={[
            styles.iconWrap,
            { backgroundColor: colors.primary + "1c" },
          ]}
        >
          <Ionicons name="construct-outline" size={32} color={colors.primary} />
        </View>
        <Text style={[styles.title, { color: colors.foreground }]}>
          Sign-ups are paused
        </Text>
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          {message?.trim() || REGISTRATION_PAUSED_MESSAGE}
        </Text>
      </Animated.View>

      <View style={styles.actions}>
        <Button label="Back to sign in" onPress={onBack} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    paddingHorizontal: 24,
  },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 12,
  },
  iconWrap: {
    width: 72,
    height: 72,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
    letterSpacing: -0.4,
    textAlign: "center",
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
    textAlign: "center",
    maxWidth: 360,
  },
  actions: { alignSelf: "stretch", gap: 12 },
});
