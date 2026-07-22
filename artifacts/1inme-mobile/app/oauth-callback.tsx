import { LinearGradient } from "expo-linear-gradient";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { MandatoryNameModal } from "@/components/MandatoryNameModal";
import { RegistrationPausedNotice } from "@/components/RegistrationPausedNotice";
import { SocialMergePrompt } from "@/components/SocialMergePrompt";
import { useAuth, type AuthUser } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import type { ApiError } from "@/lib/api";
import { redirectAfterAuth } from "@/lib/authNext";
import { maybeOfferBiometricEnrollment } from "@/lib/biometricsPrompt";

// Friendly display names for the providers we name in failure copy. Mirrors
// the SOCIALS labels in app/(auth)/index.tsx — note "twitter" shows as "X".
const PROVIDER_LABELS: Record<string, string> = {
  google: "Google",
  apple: "Apple",
  instagram: "Instagram",
  facebook: "Facebook",
  twitter: "X",
  linkedin: "LinkedIn",
  pinterest: "Pinterest",
  tiktok: "TikTok",
};

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
    // Connected Apps (CRM/GA) OAuth completion carries these instead of a
    // session — see the web ConnectedAppController::finish deep link.
    feature?: string | string[];
    status?: string | string[];
    message?: string | string[];
  }>();

  const [error, setError] = useState<string | null>(null);
  // Set to the backend message when the social sign-in is rejected because
  // an admin has paused new sign-ups (`registration_paused`, HTTP 403).
  // Drives the full-screen "we're upgrading" notice.
  const [pausedMessage, setPausedMessage] = useState<string | null>(null);
  // The provider that failed, when the redirect tells us which one it was.
  // Drives the provider-specific guidance line on the failure screen.
  const [providerLabel, setProviderLabel] = useState<string | null>(null);
  // Set to the provider name when the social identity already belongs to a
  // different Sayzio account (`identity_taken`); drives the merge prompt.
  const [mergeProvider, setMergeProvider] = useState<string | null>(null);
  const [showNameModal, setShowNameModal] = useState(false);
  const ran = useRef(false);

  useEffect(() => {
    if (ran.current) return;
    ran.current = true;

    const first = (v: string | string[] | undefined) =>
      Array.isArray(v) ? v[0] : v;

    // Connected Apps (CRM / GA4) OAuth completion: no session, just a
    // status + message. Bounce back to the Connected Apps screen with the
    // outcome instead of trying to sign in.
    if (first(params.feature) === "connected-apps") {
      router.replace({
        pathname: "/connected-apps",
        params: {
          oauth_status: first(params.status) ?? "ok",
          oauth_message: first(params.message) ?? "",
        },
      });
      return;
    }

    // Resolve the provider up front so every failure branch can name it.
    const provider = first(params.provider);
    const label = provider ? (PROVIDER_LABELS[provider] ?? null) : null;

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
          "The mobile redirect URL isn't allowed by the backend. Tell support the redirect URI sayzio://oauth-callback isn't whitelisted.",
      };
      // Don't blame the provider when the user simply cancelled.
      if (label && errParam !== "access_denied") setProviderLabel(label);
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
        .then(async () => {
          if (user.needs_name) {
            setShowNameModal(true);
            return;
          }
          await redirectAfterAuth(router);
          maybeOfferBiometricEnrollment(auth);
        })
        .catch((e) => {
          if (label) setProviderLabel(label);
          setError(e?.message ?? "Could not complete sign-in");
        });
      return;
    }

    // Native SDK path: provider returns id_token/access_token, which we
    // forward to POST /auth/social per OpenAPI. (No client-side OAuth
    // code-exchange path: the backend doesn't expose one, and the
    // browser-based flow returns a ready-to-use token directly.)
    const idToken = first(params.id_token);
    const accessToken = first(params.access_token);

    if (provider && (idToken || accessToken)) {
      socialLogin({
        provider: provider as "google" | "apple",
        id_token: idToken,
        access_token: accessToken,
      })
        .then(async ({ needsName }) => {
          if (needsName) {
            setShowNameModal(true);
            return;
          }
          await redirectAfterAuth(router);
          maybeOfferBiometricEnrollment(auth);
        })
        .catch((e: ApiError) => {
          // New sign-ups are paused by an admin — show the branded notice
          // instead of a generic error (existing users are unaffected).
          if (e?.code === "registration_paused") {
            setPausedMessage(e?.message ?? "");
            return;
          }
          // Identity (or its email) already bound to another Sayzio account —
          // offer the web merge flow instead of a dead-end error.
          if (e?.code === "identity_taken") {
            setMergeProvider(provider);
            return;
          }
          // Account has an authenticator app enrolled — carry the
          // short-lived challenge token to the verify screen, which opens
          // straight in its authenticator-code step.
          if (e?.code === "totp_required") {
            const challenge = e?.details?.challenge_token;
            if (typeof challenge === "string" && challenge) {
              router.replace({
                pathname: "/(auth)/verify",
                params: { challenge_token: challenge },
              });
              return;
            }
          }
          if (label) setProviderLabel(label);
          setError(e?.message ?? "Sign-in failed");
        });
      return;
    }

    setError(
      "Sign-in did not return a session. The backend redirect must include either ?token=… or ?provider=…&id_token=… for mobile.",
    );
  }, [params, applySession, socialLogin, router]);

  if (pausedMessage !== null) {
    return (
      <RegistrationPausedNotice
        message={pausedMessage}
        onBack={() => router.replace("/(auth)")}
      />
    );
  }

  // Derive tinted brand gradient stops for the screen background wash so the
  // OAuth return step matches the login and verify screens. Uses the
  // theme-aware brandGradient tokens so colors adapt to dark mode; dark mode
  // gets more opacity (0x40 = 25%) since the near-black base makes lighter
  // tints less visible, light mode uses 0x2e (18%) for a soft wash that keeps
  // the spinner/status text legible.
  const bgAlpha = colors.scheme === "dark" ? "40" : "2e";
  const bgGradientColors = colors.brandGradient.map(
    (c) => `${c}${bgAlpha}`,
  ) as unknown as [string, string, string];

  return (
    <View
      style={[
        styles.root,
        { backgroundColor: colors.background },
      ]}
    >
      <LinearGradient
        colors={bgGradientColors}
        start={{ x: 0.0, y: 0.0 }}
        end={{ x: 1.0, y: 1.0 }}
        style={StyleSheet.absoluteFill}
      />

      {mergeProvider ? (
        <SocialMergePrompt
          provider={mergeProvider}
          onDismiss={() => router.replace("/(auth)")}
        />
      ) : error ? (
        <>
          <Text style={[styles.title, { color: colors.foreground }]}>
            Sign-in failed
          </Text>
          {providerLabel ? (
            <Text style={[styles.body, { color: colors.foreground }]}>
              {providerLabel} sign-in is having issues right now; you can use
              email instead.
            </Text>
          ) : null}
          <Text style={[styles.body, { color: colors.mutedForeground }]}>
            {error}
          </Text>
          <View style={styles.actions}>
            <Button
              label="Use email instead"
              onPress={() =>
                router.replace({
                  pathname: "/(auth)",
                  params: { method: "email" },
                })
              }
            />
            <Button
              label="Back to sign in"
              variant="outline"
              onPress={() => router.replace("/(auth)")}
            />
          </View>
        </>
      ) : (
        <>
          <ActivityIndicator color={colors.primary} />
          <Text style={[styles.body, { color: colors.mutedForeground }]}>
            Finishing sign-in…
          </Text>
        </>
      )}

      <MandatoryNameModal
        visible={showNameModal}
        onSaved={async () => {
          setShowNameModal(false);
          await redirectAfterAuth(router);
          maybeOfferBiometricEnrollment(auth);
        }}
      />
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
  actions: { alignSelf: "stretch", gap: 12 },
});
