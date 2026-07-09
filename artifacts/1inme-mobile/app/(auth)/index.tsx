import { Ionicons, Feather } from "@expo/vector-icons";
import * as Google from "expo-auth-session/providers/google";
import { LinearGradient } from "expo-linear-gradient";
import { useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useEffect, useState } from "react";
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { RegistrationPausedNotice } from "@/components/RegistrationPausedNotice";
import { SocialMergePrompt } from "@/components/SocialMergePrompt";
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";

// Ensure WebBrowser sessions close cleanly when the auth flow finishes —
// required by expo-auth-session for the Google provider on Android.
WebBrowser.maybeCompleteAuthSession();
import { useColors } from "@/hooks/useColors";
import { redirectAfterAuth, touchPendingPostAuthNext } from "@/lib/authNext";
import { getBaseUrl, getConfiguredBaseUrl } from "@/lib/api";
import type { ApiError } from "@/lib/api";
import { getAuthConfig, isAllowedCountryCode } from "@/lib/api/authConfig";
import { maybeOfferBiometricEnrollment } from "@/lib/biometricsPrompt";
import { showAlert } from "@/lib/webAlert";

type Channel = "email" | "mobile";

type SocialProvider = "google" | "linkedin";

const SOCIALS: {
  id: SocialProvider;
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  color: string;
}[] = [
  { id: "google", label: "Google", icon: "logo-google", color: "#ea4335" },
  { id: "linkedin", label: "LinkedIn", icon: "logo-linkedin", color: "#0a66c2" },
];

// LinkedIn goes through the web /user/social-oauth/{provider}/login route
// via an in-app browser session. Google uses the native expo-auth-session flow.
const WEB_BROWSER_PROVIDERS = new Set<SocialProvider>(["linkedin"]);

const HAS_GOOGLE_NATIVE =
  !!process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID ||
  !!process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID ||
  !!process.env.EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID ||
  !!process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID;

// On web, expo-auth-session's useIdTokenAuthRequest THROWS at render when no
// webClientId is configured ("Client Id property `webClientId` must be defined
// to use Google auth on this platform."), which crashes the whole login screen
// into the error boundary. On native it safely no-ops without a client id.
// So only invoke the hook when it's safe to do so. Both Platform.OS and the
// env var are module-level constants for the app's lifetime, so this condition
// never changes between renders and the hook call order stays stable.
const GOOGLE_AUTH_SAFE_TO_INIT =
  Platform.OS !== "web" || !!process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID;

type GoogleAuth = ReturnType<typeof Google.useIdTokenAuthRequest>;

function useGuardedGoogleAuth(): GoogleAuth {
  if (!GOOGLE_AUTH_SAFE_TO_INIT) {
    return [null, null, async () => ({ type: "dismiss" })] as unknown as GoogleAuth;
  }
  // eslint-disable-next-line react-hooks/rules-of-hooks
  return Google.useIdTokenAuthRequest({
    clientId: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID,
    iosClientId: process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID,
    androidClientId: process.env.EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID,
    webClientId: process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID,
  });
}

export default function AuthLanding() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const auth = useAuth();
  const { sendOtp, demoLogin, socialLogin } = auth;

  // When the OAuth return screen sends users here to fall back to email
  // (?method=email), default to the email tab and focus the field so the
  // recovery path is one tap, not a hunt for the right input.
  const { method } = useLocalSearchParams<{ method?: string | string[] }>();
  const methodParam = Array.isArray(method) ? method[0] : method;
  const [autoFocusEmail] = useState(methodParam === "email");

  // Native Google sign-in via expo-auth-session. Returns an id_token
  // that we POST to /auth/social (per OpenAPI). The hook is no-op
  // unless at least one EXPO_PUBLIC_GOOGLE_*_CLIENT_ID is set, so it
  // safely degrades when the build isn't configured for it. On web it
  // is skipped entirely (see useGuardedGoogleAuth) so a missing
  // webClientId doesn't crash the screen.
  const [googleRequest, googleResponse, googlePrompt] = useGuardedGoogleAuth();

  useEffect(() => {
    if (!googleResponse) return;
    if (googleResponse.type !== "success") return;
    const idToken = googleResponse.params?.id_token;
    if (!idToken) return;
    socialLogin({ provider: "google", id_token: idToken })
      .then(async () => {
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
        // The Google account (or its email) already belongs to a different
        // Sayzio account — offer the web merge flow instead of a dead-end error.
        if (e?.code === "identity_taken") {
          setMergeProvider("google");
          return;
        }
        const msg = e?.message ?? "Google sign-in failed";
        if (Platform.OS === "web") setError(msg);
        else showAlert("Sign in", msg);
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [googleResponse]);

  const [channel, setChannel] = useState<Channel>("email");
  const [identifier, setIdentifier] = useState("");
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Set to the backend message when an account-creation attempt is rejected
  // because an admin has paused new sign-ups (`registration_paused`, HTTP
  // 403). Drives the full-screen "we're upgrading" notice. Existing-user
  // sign-in is unaffected — only new-account paths return this code.
  const [pausedMessage, setPausedMessage] = useState<string | null>(null);
  // Set to the provider name when a social sign-in hits an account conflict
  // (`identity_taken`); drives the inline "merge accounts?" prompt.
  const [mergeProvider, setMergeProvider] = useState<string | null>(null);

  // Login-method policy: email is always available; WhatsApp (mobile) login
  // is behind an admin toggle with an allowed-country-code list. Default to
  // email-only until the config loads / if it fails.
  const [mobileLoginEnabled, setMobileLoginEnabled] = useState(false);
  const [allowedCountryCodes, setAllowedCountryCodes] = useState<string[]>([]);

  // A guest who tapped a "Perfect pairings" card stashed a post-auth
  // destination before being sent here. Sliding its freshness window forward
  // on each active landing means a slow sign-up (email verification, a
  // distraction) isn't silently dropped once the initial 10-minute window
  // lapses — as long as they're actively in the flow, their pairing survives.
  useEffect(() => {
    touchPendingPostAuthNext();
  }, []);

  useEffect(() => {
    let active = true;
    getAuthConfig().then((cfg) => {
      if (!active) return;
      setMobileLoginEnabled(cfg.mobileLoginEnabled);
      setAllowedCountryCodes(cfg.allowedCountryCodes);
      if (!cfg.mobileLoginEnabled) setChannel("email");
    });
    return () => {
      active = false;
    };
  }, []);

  const onSendOtp = async () => {
    // In dev, refuse to send OTP at the production host by accident:
    // the most common cause of "broken login" is a missing local
    // EXPO_PUBLIC_API_BASE_URL. Production builds keep the fallback.
    if (__DEV__ && !getConfiguredBaseUrl()) {
      setError(
        "API base URL isn't set. Add EXPO_PUBLIC_API_BASE_URL to artifacts/1inme-mobile/.env so OTP doesn't go to production.",
      );
      return;
    }
    const id = identifier.trim();
    if (!id) {
      setError(channel === "email" ? "Enter your email" : "Enter your WhatsApp number");
      return;
    }
    if (channel === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(id)) {
      setError("That doesn't look like a valid email");
      return;
    }
    if (channel === "mobile" && !/^\+?[0-9\s\-()]{6,}$/.test(id)) {
      setError("Enter a WhatsApp number with country code (e.g. +1 555 123 4567)");
      return;
    }
    if (
      channel === "mobile" &&
      allowedCountryCodes.length > 0 &&
      !isAllowedCountryCode(id, allowedCountryCodes)
    ) {
      setError(`Supported country codes: ${allowedCountryCodes.join(", ")}.`);
      return;
    }
    setError(null);
    setBusy("otp");
    try {
      const { demoReveal } = await sendOtp({ channel, identifier: id });
      router.push({
        pathname: "/(auth)/verify",
        params: {
          channel,
          identifier: id,
          ...(demoReveal ? { demo_reveal: demoReveal } : {}),
        },
      });
    } catch (e) {
      const err = e as ApiError;
      // New sign-ups are paused by an admin — show the branded notice
      // instead of a generic error. Only new-account OTP sends return this;
      // existing users still get their code.
      if (err?.code === "registration_paused") {
        setPausedMessage(err?.message ?? "");
        return;
      }
      // Surface the most useful piece: backend validation field errors
      // win over the generic message so users see "Email is invalid"
      // instead of "Request failed (422)".
      const fieldMsg = err?.errors
        ? Object.values(err.errors)
            .flatMap((v) => (Array.isArray(v) ? v : [String(v)]))
            .find(Boolean)
        : null;
      let msg = fieldMsg ?? err?.message ?? "Could not send code";
      if (err?.status === 429) msg = "Too many attempts — wait a minute and try again.";
      if (err?.status && err.status >= 500) msg = "Our server is having trouble. Try again shortly.";
      setError(msg);
    } finally {
      setBusy(null);
    }
  };

  const onDemo = async (role: "user" | "admin") => {
    setBusy(role === "user" ? "demo-user" : "demo-admin");
    setError(null);
    try {
      await demoLogin(role === "user" ? "user" : "super_admin");
      await redirectAfterAuth(router);
      maybeOfferBiometricEnrollment(auth);
    } catch (e) {
      setError((e as ApiError)?.message ?? "Demo unavailable");
    } finally {
      setBusy(null);
    }
  };

  const onSocial = async (provider: SocialProvider) => {
    setError(null);

    // Google: native flow via expo-auth-session — POSTs id_token to
    // /auth/social directly, no backend OAuth round-trip needed.
    if (provider === "google") {
      if (!HAS_GOOGLE_NATIVE || !googleRequest) {
        const msg =
          "Google sign-in isn't configured for this build. Add EXPO_PUBLIC_GOOGLE_CLIENT_ID (or platform-specific EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID / EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID) to enable it.";
        if (Platform.OS === "web") setError(msg);
        else showAlert("Sign in", msg);
        return;
      }
      setBusy("social-google");
      try {
        await googlePrompt();
      } finally {
        setBusy(null);
      }
      return;
    }

    if (__DEV__ && !getConfiguredBaseUrl()) {
      const msg =
        "API base URL isn't set. Add EXPO_PUBLIC_API_BASE_URL to artifacts/1inme-mobile/.env so OAuth doesn't redirect through production.";
      if (Platform.OS === "web") setError(msg);
      else showAlert("Sign in", msg);
      return;
    }
    setBusy(`social-${provider}`);
    try {
      const ret = encodeURIComponent("sayzio://oauth-callback");
      // Backend route: /user/social-oauth/{provider}/login (see
      // routes/modules/user.php). It performs the OAuth dance and
      // redirects back to `return` with the token / user payload.
      const url = `${getBaseUrl()}/user/social-oauth/${provider}/login?source=mobile&return=${ret}`;
      // openAuthSessionAsync handles iOS ASWebAuthenticationSession +
      // Android Custom Tabs, returning {type: 'success', url} when the
      // backend redirects back to our `sayzio://oauth-callback` scheme.
      // The scheme is registered in app.json so this should always
      // round-trip; if it doesn't, surface a clear error rather than
      // silently giving up.
      const result = await WebBrowser.openAuthSessionAsync(url, "sayzio://oauth-callback");
      if (result.type === "success") {
        // Success URL handling lives in oauth-callback.tsx, which the
        // deep link router opens automatically. Nothing to do here.
        return;
      }
      if (result.type === "cancel" || result.type === "dismiss") {
        // User backed out — quiet, no error toast.
        return;
      }
      if (result.type === "locked") {
        throw new Error("Another sign-in is in progress. Try again in a moment.");
      }
      throw new Error(`${provider} sign-in didn't complete (${result.type ?? "unknown"})`);
    } catch (e) {
      const msg =
        e instanceof Error
          ? e.message
          : `${provider} sign-in is not configured for this build`;
      if (Platform.OS === "web") setError(msg);
      else showAlert("Sign in", msg);
    } finally {
      setBusy(null);
    }
  };

  const webTop = Platform.OS === "web" ? 0 : 0;
  const webBottom = Platform.OS === "web" ? 34 : 0;

  if (pausedMessage !== null) {
    return (
      <RegistrationPausedNotice
        message={pausedMessage}
        onBack={() => {
          setPausedMessage(null);
          setError(null);
        }}
      />
    );
  }

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <LinearGradient
        colors={[colors.primary + "1a", "transparent"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 0, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          {
            paddingTop: insets.top + 16 + webTop,
            paddingBottom: insets.bottom + 32 + webBottom,
          },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <BrandWordmark size={36} align="center" />
        <View style={{ height: 32 }} />
        <Text style={[styles.h1, { color: colors.foreground }]}>
          Welcome back
        </Text>
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          Sign in with the same account you use on the web.
        </Text>

        <View
          style={[
            styles.tabs,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          {((mobileLoginEnabled ? ["email", "mobile"] : ["email"]) as Channel[]).map((c) => {
            const active = channel === c;
            return (
              <Pressable
                key={c}
                onPress={() => {
                  setChannel(c);
                  setIdentifier("");
                  setError(null);
                }}
                style={[
                  styles.tab,
                  {
                    backgroundColor: active ? colors.background : "transparent",
                    borderRadius: colors.radius - 4,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.tabText,
                    { color: active ? colors.primary : colors.mutedForeground },
                  ]}
                >
                  {c === "email" ? "Email" : "WhatsApp"}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <View style={{ height: 16 }} />

        <TextField
          label={channel === "email" ? "Email address" : "WhatsApp number"}
          placeholder={channel === "email" ? "you@example.com" : "+1 555 123 4567"}
          autoCapitalize="none"
          autoCorrect={false}
          autoFocus={autoFocusEmail && channel === "email"}
          keyboardType={channel === "email" ? "email-address" : "phone-pad"}
          value={identifier}
          onChangeText={setIdentifier}
          error={error ?? undefined}
        />

        <View style={{ height: 12 }} />
        <Button
          label="Send code"
          variant="cta"
          onPress={onSendOtp}
          loading={busy === "otp"}
          disabled={!!busy && busy !== "otp"}
        />

        <View style={styles.divider}>
          <View style={[styles.line, { backgroundColor: colors.border }]} />
          <Text style={[styles.dividerText, { color: colors.mutedForeground }]}>
            or continue with
          </Text>
          <View style={[styles.line, { backgroundColor: colors.border }]} />
        </View>

        <View style={styles.socialGrid}>
          {SOCIALS.filter(
            (s) =>
              (s.id === "google" && HAS_GOOGLE_NATIVE) ||
              WEB_BROWSER_PROVIDERS.has(s.id),
          ).map((s) => {
            const isBusy = busy === `social-${s.id}`;
            const disabled = !!busy && !isBusy;
            return (
              <Pressable
                key={s.id}
                onPress={() => onSocial(s.id)}
                disabled={disabled || isBusy}
                style={({ pressed }) => [
                  styles.socialBtn,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    opacity: disabled ? 0.4 : pressed ? 0.7 : 1,
                  },
                ]}
                accessibilityLabel={`Log in with ${s.label}`}
              >
                <Ionicons
                  name={s.icon}
                  size={20}
                  color={colors.scheme === "dark" ? colors.foreground : s.color}
                />
                <Text style={[styles.socialBtnLabel, { color: colors.foreground }]}>
                  Log in with {s.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        {mergeProvider ? (
          <SocialMergePrompt
            provider={mergeProvider}
            onDismiss={() => setMergeProvider(null)}
          />
        ) : null}

        <View style={{ height: 28 }} />

        <Text style={[styles.section, { color: colors.mutedForeground }]}>
          Just exploring?
        </Text>
        <View style={styles.demoRow}>
          <Button
            label="Demo as user"
            variant="secondary"
            onPress={() => onDemo("user")}
            loading={busy === "demo-user"}
            disabled={!!busy && busy !== "demo-user"}
            style={{ flex: 1 }}
          />
          <Button
            label="Demo as admin"
            variant="secondary"
            onPress={() => onDemo("admin")}
            loading={busy === "demo-admin"}
            disabled={!!busy && busy !== "demo-admin"}
            style={{ flex: 1 }}
          />
        </View>

        <View style={{ height: 24 }} />
        <View style={styles.infoLinks}>
          <Pressable onPress={() => router.push("/info/about")} hitSlop={8}>
            <Text style={[styles.infoLink, { color: colors.mutedForeground }]}>
              About
            </Text>
          </Pressable>
          <Text style={[styles.infoDot, { color: colors.border }]}>·</Text>
          <Pressable onPress={() => router.push("/info/help")} hitSlop={8}>
            <Text style={[styles.infoLink, { color: colors.mutedForeground }]}>
              Help
            </Text>
          </Pressable>
          <Text style={[styles.infoDot, { color: colors.border }]}>·</Text>
          <Pressable onPress={() => router.push("/info/privacy")} hitSlop={8}>
            <Text style={[styles.infoLink, { color: colors.mutedForeground }]}>
              Privacy
            </Text>
          </Pressable>
          <Text style={[styles.infoDot, { color: colors.border }]}>·</Text>
          <Pressable onPress={() => router.push("/info/terms")} hitSlop={8}>
            <Text style={[styles.infoLink, { color: colors.mutedForeground }]}>
              Terms
            </Text>
          </Pressable>
        </View>

        <View style={{ height: 12 }} />
        <Text style={[styles.fineprint, { color: colors.mutedForeground }]}>
          By continuing you agree to our Terms and Privacy Policy.
        </Text>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  scroll: { paddingHorizontal: 24, gap: 4 },
  h1: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 32,
    letterSpacing: -0.5,
  },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16, marginBottom: 24 },
  tabs: { flexDirection: "row", padding: 4, borderWidth: 1 },
  tab: { flex: 1, alignItems: "center", paddingVertical: 10 },
  tabText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  divider: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginVertical: 24,
  },
  line: { flex: 1, height: StyleSheet.hairlineWidth },
  dividerText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  socialGrid: {
    flexDirection: "column",
    gap: 10,
  },
  socialBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 10,
    paddingVertical: 13,
    paddingHorizontal: 16,
    borderWidth: 1,
  },
  socialBtnLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
  section: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.6,
    textTransform: "uppercase",
    marginBottom: 12,
  },
  demoRow: { flexDirection: "row", gap: 12 },
  infoLinks: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "center",
    alignItems: "center",
    gap: 8,
  },
  infoLink: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    paddingVertical: 4,
  },
  infoDot: { fontSize: 13 },
  fineprint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    lineHeight: 18,
  },
});
