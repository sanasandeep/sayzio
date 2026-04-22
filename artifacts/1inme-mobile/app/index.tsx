import { LinearGradient } from "expo-linear-gradient";
import { Redirect } from "expo-router";
import { useEffect, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { BrandWordmark } from "@/components/Brand";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { getOnboardingComplete } from "@/lib/secure";

export default function GateScreen() {
  const colors = useColors();
  const { ready, user, locked, biometricEnabled, token } = useAuth();
  const [onboarded, setOnboarded] = useState<boolean | null>(null);

  useEffect(() => {
    getOnboardingComplete().then(setOnboarded);
  }, []);

  if (!ready || onboarded === null) {
    return (
      <View style={[styles.splash, { backgroundColor: colors.background }]}>
        <LinearGradient
          colors={[colors.primary + "22", colors.accent + "22", "transparent"]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
        <BrandWordmark size={56} />
        <Text style={[styles.tagline, { color: colors.mutedForeground }]}>
          One link. Every you.
        </Text>
        <ActivityIndicator color={colors.primary} style={{ marginTop: 32 }} />
      </View>
    );
  }

  if (!onboarded) return <Redirect href="/onboarding" />;
  if (!user || !token) return <Redirect href="/(auth)" />;
  if (locked && biometricEnabled) return <Redirect href={"/lock" as never} />;
  return <Redirect href="/(tabs)" />;
}

const styles = StyleSheet.create({
  splash: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12 },
  tagline: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    letterSpacing: 1.2,
    textTransform: "uppercase",
  },
});
