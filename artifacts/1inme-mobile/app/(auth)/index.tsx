import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import {
  Alert,
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
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import type { ApiError } from "@/lib/api";

type Channel = "email" | "mobile";

export default function AuthLanding() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { sendOtp, demoLogin } = useAuth();

  const [channel, setChannel] = useState<Channel>("email");
  const [identifier, setIdentifier] = useState("");
  const [busy, setBusy] = useState<null | "otp" | "demo-user" | "demo-admin" | "google">(null);
  const [error, setError] = useState<string | null>(null);

  const onSendOtp = async () => {
    if (!identifier.trim()) {
      setError(channel === "email" ? "Enter your email" : "Enter your mobile number");
      return;
    }
    setError(null);
    setBusy("otp");
    try {
      await sendOtp({ channel, identifier: identifier.trim() });
      router.push({
        pathname: "/(auth)/verify",
        params: { channel, identifier: identifier.trim() },
      });
    } catch (e) {
      setError((e as ApiError)?.message ?? "Could not send code");
    } finally {
      setBusy(null);
    }
  };

  const onDemo = async (role: "user" | "admin") => {
    setBusy(role === "user" ? "demo-user" : "demo-admin");
    setError(null);
    try {
      await demoLogin(role === "user" ? "user" : "super_admin");
    } catch (e) {
      setError((e as ApiError)?.message ?? "Demo unavailable");
    } finally {
      setBusy(null);
    }
  };

  const onGoogle = async () => {
    setBusy("google");
    try {
      const url = `${getBaseUrl()}/auth/google?source=mobile&return=1inme%3A%2F%2Foauth-callback`;
      const result = await WebBrowser.openAuthSessionAsync(url, "1inme://oauth-callback");
      if (result.type !== "success") {
        if (result.type === "cancel" || result.type === "dismiss") {
          return;
        }
        throw new Error("Sign-in was cancelled");
      }
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Google sign-in is not configured yet";
      if (Platform.OS === "web") setError(msg);
      else Alert.alert("Sign in", msg);
    } finally {
      setBusy(null);
    }
  };

  const webTop = Platform.OS === "web" ? 67 : 0;
  const webBottom = Platform.OS === "web" ? 34 : 0;

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
        <BrandWordmark size={36} />
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
          {(["email", "mobile"] as const).map((c) => {
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
                    {
                      color: active ? colors.primary : colors.mutedForeground,
                    },
                  ]}
                >
                  {c === "email" ? "Email" : "Mobile"}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <View style={{ height: 16 }} />

        <TextField
          label={channel === "email" ? "Email address" : "Mobile number"}
          placeholder={channel === "email" ? "you@example.com" : "+1 555 123 4567"}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType={channel === "email" ? "email-address" : "phone-pad"}
          value={identifier}
          onChangeText={setIdentifier}
          error={error ?? undefined}
        />

        <View style={{ height: 12 }} />
        <Button
          label="Send code"
          onPress={onSendOtp}
          loading={busy === "otp"}
          disabled={!!busy && busy !== "otp"}
        />

        <View style={styles.divider}>
          <View style={[styles.line, { backgroundColor: colors.border }]} />
          <Text style={[styles.dividerText, { color: colors.mutedForeground }]}>or</Text>
          <View style={[styles.line, { backgroundColor: colors.border }]} />
        </View>

        <Button
          label="Continue with Google"
          variant="outline"
          onPress={onGoogle}
          loading={busy === "google"}
          disabled={!!busy && busy !== "google"}
          leading={<Feather name="globe" size={18} color={colors.foreground} />}
        />

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
        <Text style={[styles.fineprint, { color: colors.mutedForeground }]}>
          By continuing you agree to our{" "}
          <Text
            style={{ color: colors.primary }}
            onPress={() => router.push("/info/terms")}
          >
            Terms
          </Text>{" "}
          and{" "}
          <Text
            style={{ color: colors.primary }}
            onPress={() => router.push("/info/privacy")}
          >
            Privacy Policy
          </Text>
          .
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
  tabs: {
    flexDirection: "row",
    padding: 4,
    borderWidth: 1,
  },
  tab: {
    flex: 1,
    alignItems: "center",
    paddingVertical: 10,
  },
  tabText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  divider: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginVertical: 24,
  },
  line: { flex: 1, height: StyleSheet.hairlineWidth },
  dividerText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  section: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.6,
    textTransform: "uppercase",
    marginBottom: 12,
  },
  demoRow: { flexDirection: "row", gap: 12 },
  fineprint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    lineHeight: 18,
  },
});
