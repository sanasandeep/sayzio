import { Feather } from "@expo/vector-icons";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { cancelSensitiveChange } from "@/lib/api/security";

type State = "working" | "ok" | "expired" | "notfound" | "error";

export default function CancelChangeScreen() {
  const colors = useColors();
  const router = useRouter();
  // Deep link shape (sent in the cancel-link email):
  //   sayzio://cancel-change?id=123&token=abc
  // The screen lives under (auth) so it works whether the user is signed
  // in on the device or not — the API endpoint accepts the token-only
  // form for the unauthenticated case.
  const params = useLocalSearchParams<{ id?: string; token?: string }>();
  const id = Number(params.id);
  const token = String(params.token ?? "");

  const [state, setState] = useState<State>("working");
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!Number.isFinite(id) || !token) {
      setState("notfound");
      return;
    }
    let cancelled = false;
    cancelSensitiveChange(id, token)
      .then(() => {
        if (!cancelled) setState("ok");
      })
      .catch((e: { status?: number; message?: string }) => {
        if (cancelled) return;
        if (e?.status === 410) setState("expired");
        else if (e?.status === 404) setState("notfound");
        else {
          setState("error");
          setMessage(e?.message ?? null);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [id, token]);

  const ICON: Record<State, keyof typeof Feather.glyphMap> = {
    working: "loader",
    ok: "check-circle",
    expired: "clock",
    notfound: "help-circle",
    error: "alert-triangle",
  };

  const TITLE: Record<State, string> = {
    working: "Cancelling change…",
    ok: "Change cancelled",
    expired: "Too late to cancel",
    notfound: "Cancel link not recognised",
    error: "Something went wrong",
  };

  const BODY: Record<State, string> = {
    working: "Hold on while we tell our servers to drop the pending change.",
    ok: "Your account email and password are unchanged. If you didn't request the change, sign in and review your security settings.",
    expired:
      "The cooling-off window has already elapsed, so the change is now in effect. Sign in with your new credentials and review your security settings.",
    notfound:
      "The link in your email may have been used already, or it's missing pieces. Open the most recent cancel email or contact support.",
    error: message ?? "Try opening the link again from your email.",
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView contentContainerStyle={styles.scroll}>
        <View
          style={[
            styles.iconWrap,
            { backgroundColor: colors.primary + "1c" },
          ]}
        >
          <Feather
            name={ICON[state]}
            size={32}
            color={state === "ok" ? colors.primary : colors.foreground}
          />
        </View>
        <Text style={[styles.title, { color: colors.foreground }]}>
          {TITLE[state]}
        </Text>
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          {BODY[state]}
        </Text>
        {state !== "working" ? (
          <Button
            label="Back to sign in"
            onPress={() => router.replace("/(auth)")}
          />
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flexGrow: 1,
    padding: 24,
    gap: 16,
    alignItems: "center",
    justifyContent: "center",
  },
  iconWrap: {
    width: 72,
    height: 72,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
    textAlign: "center",
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
    textAlign: "center",
    maxWidth: 360,
  },
});
