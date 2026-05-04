import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useAuth, type AuthUser } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
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
      // Map common provider/backend error codes to friendlier copy
      // instead of forwarding raw values like "access_denied".
      const map: Record<string, string> = {
        access_denied: "You cancelled the sign-in.",
        unauthorized_client: "This app isn't authorized to sign in with that provider yet.",
        invalid_request: "The sign-in request was malformed. Please try again.",
        server_error: "The provider had a server error. Try again shortly.",
        temporarily_unavailable: "The provider is temporarily unavailable. Try again shortly.",
        redirect_uri_mismatch:
          "The mobile redirect URL isn't allowed by the backend. Tell support the redirect URI 1inme://oauth-callback isn't whitelisted.",
      };
      setError(map[errParam] ?? errParam);
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

    // Native SDK path: provider returns id_token/access_token, which we
    // forward to POST /auth/social per OpenAPI. (No client-side OAuth
    // code-exchange path: the backend doesn't expose one, and the
    // browser-based flow returns a ready-to-use token directly.)
    const provider = first(params.provider);
    const idToken = first(params.id_token);
    const accessToken = first(params.access_token);

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

    setError(
      "Sign-in did not return a session. The backend redirect must include either ?token=… or ?provider=…&id_token=… for mobile.",
    );
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
