import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import { Platform, StyleSheet, Text, View } from "react-native";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";

export default function LockScreen() {
  const colors = useColors();
  const router = useRouter();
  const {
    ready,
    token,
    locked,
    biometricEnabled,
    biometricCapability,
    unlockWithBiometrics,
    signOut,
  } = useAuth();

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const triedAuto = useRef(false);

  const tryUnlock = useCallback(async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const res = await unlockWithBiometrics();
    setBusy(false);
    if (res.ok) {
      router.replace("/(tabs)");
      return;
    }
    if (res.reason === "unavailable") {
      // Capability vanished — context already cleared the session.
      router.replace("/(auth)");
      return;
    }
    setError(
      res.reason === "cancel"
        ? "Cancelled. Try again to unlock."
        : (res.message ?? "Could not verify. Please try again."),
    );
  }, [busy, router, unlockWithBiometrics]);

  // If we somehow land here without a locked session, leave.
  useEffect(() => {
    if (!ready) return;
    if (!token || !biometricEnabled || !locked) {
      router.replace(token ? "/(tabs)" : "/(auth)");
    }
  }, [ready, token, biometricEnabled, locked, router]);

  // Auto-trigger the system prompt once on mount.
  useEffect(() => {
    if (!ready || triedAuto.current) return;
    if (!locked || !biometricEnabled) return;
    triedAuto.current = true;
    tryUnlock();
  }, [ready, locked, biometricEnabled, tryUnlock]);

  const onUseAnotherMethod = async () => {
    setBusy(true);
    await signOut();
    setBusy(false);
    router.replace("/(auth)");
  };

  const label = biometricCapability?.label ?? "Biometric unlock";
  const icon: keyof typeof Feather.glyphMap =
    Platform.OS === "ios" && label === "Face ID" ? "smile" : "unlock";

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <LinearGradient
        colors={[colors.primary + "22", colors.accent + "22", "transparent"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
      <View style={styles.center}>
        <BrandWordmark size={42} align="center" />
        <View
          style={[
            styles.iconWrap,
            { backgroundColor: colors.primary + "1f" },
          ]}
        >
          <Feather name={icon} size={36} color={colors.primary} />
        </View>
        <Text style={[styles.title, { color: colors.foreground }]}>
          Locked
        </Text>
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          Use {label} to unlock Sayzio and pick up where you left off.
        </Text>
        {error ? (
          <Text style={[styles.error, { color: colors.destructive ?? "#dc2626" }]}>
            {error}
          </Text>
        ) : null}
      </View>
      <View style={styles.actions}>
        <Button
          label={`Unlock with ${label}`}
          onPress={tryUnlock}
          loading={busy}
        />
        <View style={{ height: 12 }} />
        <Button
          label="Use another method"
          variant="ghost"
          onPress={onUseAnotherMethod}
          disabled={busy}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    paddingHorizontal: 24,
    paddingTop: 80,
    paddingBottom: 32,
  },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: 16 },
  iconWrap: {
    width: 88,
    height: 88,
    borderRadius: 44,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 12,
  },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    letterSpacing: -0.4,
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
    textAlign: "center",
    paddingHorizontal: 16,
  },
  error: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    textAlign: "center",
    marginTop: 8,
  },
  actions: {},
});
