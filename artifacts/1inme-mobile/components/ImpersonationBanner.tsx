import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useState } from "react";
import { ActivityIndicator, Pressable, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useAuth } from "@/contexts/AuthContext";

// Global "Viewing as … / Stop impersonating" banner. Renders whenever an
// operator is impersonating another user (the operator's own session is
// stashed in secure storage by AuthContext). Tapping "Stop" restores the
// operator session and returns to the app. Hidden while locked.

export function ImpersonationBanner() {
  const { impersonating, impersonatedName, stopImpersonating, locked } = useAuth();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  if (!impersonating || locked) return null;

  const stop = async () => {
    if (busy) return;
    setBusy(true);
    try {
      await stopImpersonating();
      router.replace("/(tabs)" as never);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View
      pointerEvents="box-none"
      style={{
        position: "absolute",
        top: 0,
        left: 0,
        right: 0,
        zIndex: 9999,
        paddingTop: insets.top,
        backgroundColor: "#7c3aed",
      }}
    >
      <View
        style={{
          flexDirection: "row",
          alignItems: "center",
          gap: 8,
          paddingHorizontal: 14,
          paddingVertical: 8,
        }}
      >
        <Feather name="eye" size={16} color="#fff" />
        <Text numberOfLines={1} style={{ flex: 1, color: "#fff", fontWeight: "600", fontSize: 13 }}>
          Viewing as {impersonatedName ?? "user"}
        </Text>
        <Pressable
          onPress={stop}
          disabled={busy}
          style={{
            flexDirection: "row",
            alignItems: "center",
            gap: 6,
            backgroundColor: "rgba(255,255,255,0.2)",
            paddingHorizontal: 12,
            paddingVertical: 6,
            borderRadius: 999,
          }}
        >
          {busy ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <Feather name="log-out" size={14} color="#fff" />
          )}
          <Text style={{ color: "#fff", fontWeight: "700", fontSize: 12 }}>Stop</Text>
        </Pressable>
      </View>
    </View>
  );
}
