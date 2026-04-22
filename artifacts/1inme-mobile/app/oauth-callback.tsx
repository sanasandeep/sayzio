import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useAuth, type AuthUser } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";
import { maybeOfferBiometricEnrollment } from "@/lib/biometricsPrompt";

export default function OAuthCallback() {
  const colors = useColors();
  const router = useRouter();
  const auth = useAuth();
  const { applySession, socialLogin } = auth;
  const params = useLocalSearchParams<{
    token?: string | string[];
    user?: string | string[];
    provider?: string | string[];
    id_token?: string | string[];
    access_token?: string | string[];
    code?: string | string[];
    state?: string | string[];
    error?: string | string[];
  }>();

  const [error, setError] = useState<string | null>(null);
  const ran = useRef(false);

  useEffect(() => {
    if (ran.current) return;
    ran.current = true;

    const first = (v: string | string[] | undefined) =>
      Array.isArray(v) ? v[0] : v;

    const errParam = first(params.error);
    if (errParam) {
      setError(errParam);
      return;
    }

    const token = first(params.token);
    const userRaw = first(params.user);
    if (token) {
      let user: AuthUser = { id: "" };
      if (userRaw) {
        try {
          user = JSON.parse(userRaw) as AuthUser;
        } catch {}
      }
      applySession(token, user)
        .then(() => {
          router.replace("/(tabs)");
          maybeOfferBiometricEnrollment(auth);
        })
        .catch((e) => setError(e?.message ?? "Could not complete sign-in"));
      return;
    }

    const provider = first(params.provider);
    const idToken = first(params.id_token);
    const accessToken = first(params.access_token);
    const code = first(params.code);
    const state = first(params.state);

    if (provider && (idToken || accessToken)) {
      socialLogin({
        provider: provider as "google" | "apple",
        id_token: idToken,
        access_token: accessToken,
      })
        .then(() => {
          router.replace("/(tabs)");
          maybeOfferBiometricEnrollment(auth);
        })
        .catch((e) => setError(e?.message ?? "Sign-in failed"));
      return;
    }

    if (provider && code) {
      apiFetch<{ token: string; user: AuthUser }>(
        "/auth/social/exchange",
        {
          method: "POST",
          body: JSON.stringify({ provider, code, state }),
        },
      )
        .then((res) => applySession(res.token, res.user))
        .then(() => {
          router.replace("/(tabs)");
          maybeOfferBiometricEnrollment(auth);
        })
        .catch((e) => setError(e?.message ?? "Sign-in failed"));
      return;
    }

    setError("Sign-in did not return a session");
  }, [params, applySession, socialLogin, router]);

  return (
    <View
      style={[
        styles.root,
        { backgroundColor: colors.background },
      ]}
    >
      {error ? (
        <>
          <Text style={[styles.title, { color: colors.foreground }]}>
            Sign-in failed
          </Text>
          <Text style={[styles.body, { color: colors.mutedForeground }]}>
            {error}
          </Text>
          <Button
            label="Back to sign in"
            variant="outline"
            onPress={() => router.replace("/(auth)")}
          />
        </>
      ) : (
        <>
          <ActivityIndicator color={colors.primary} />
          <Text style={[styles.body, { color: colors.mutedForeground }]}>
            Finishing sign-in…
          </Text>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 32,
    gap: 16,
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    textAlign: "center",
  },
});
