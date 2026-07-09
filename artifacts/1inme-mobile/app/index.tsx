import { LinearGradient } from "expo-linear-gradient";
import { Redirect } from "expo-router";
import { useEffect, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { BrandWordmark } from "@/components/Brand";
import { ZioSplash } from "@/components/ZioSplash";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { getOnboardingStatus } from "@/lib/api/profile";
import { getOnboardingComplete } from "@/lib/secure";

export default function GateScreen() {
  const colors = useColors();
  const { ready, user, locked, biometricEnabled, token } = useAuth();
  const [onboarded, setOnboarded] = useState<boolean | null>(null);
  // Server-side first-run setup gate (separate from the local intro-slides
  // flag above): null = not yet checked, true = needs the stepped /setup flow.
  const [needsSetup, setNeedsSetup] = useState<boolean | null>(null);
  // Animated Zio splash shown once on every cold launch.
  const [showSplash, setShowSplash] = useState(true);

  useEffect(() => {
    getOnboardingComplete().then(setOnboarded);
  }, []);

  useEffect(() => {
    let cancelled = false;
    if (!user || !token) {
      setNeedsSetup(false);
      return;
    }
    setNeedsSetup(null);
    getOnboardingStatus()
      .then((s) => {
        if (!cancelled) setNeedsSetup(s.onboarded_at === null);
      })
      // Fail open: never trap the user on the splash if the check fails.
      .catch(() => {
        if (!cancelled) setNeedsSetup(false);
      });
    return () => {
      cancelled = true;
    };
  }, [user, token]);

  // appReady: true when we know exactly where the user is going.
  // Covers every branch below — once this is true the gate can proceed
  // the instant the minimum splash duration has elapsed.
  const appReady =
    ready &&
    onboarded !== null &&
    (
      onboarded === false ||      // → /onboarding
      !user || !token ||          // → /(auth)
      (locked && biometricEnabled) || // → /lock
      needsSetup !== null         // → /setup or /(tabs)
    );

  // While the splash is running, render it as the sole view so that
  // <Redirect> nodes never mount — navigation must not fire during the splash.
  if (showSplash) {
    return (
      <ZioSplash
        onDone={() => setShowSplash(false)}
        appReady={appReady}
        minDuration={2400}
        maxDuration={3200}
      />
    );
  }

  // ── Post-splash gate ──────────────────────────────────────────────────────
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
  // Wait for the server onboarding check before deciding between the stepped
  // first-run setup and the app itself.
  if (needsSetup === null) {
    return (
      <View style={[styles.splash, { backgroundColor: colors.background }]}>
        <BrandWordmark size={56} />
        <ActivityIndicator color={colors.primary} style={{ marginTop: 32 }} />
      </View>
    );
  }
  if (needsSetup) return <Redirect href={"/setup" as never} />;
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
