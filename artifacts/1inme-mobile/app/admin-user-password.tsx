import { Feather } from "@expo/vector-icons";
import { useMutation } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { setUserPassword } from "@/lib/api/adminUsers";

// Admin screen: set or replace a user's password.
// Reached from the admin user-detail section (capability `set_user_password`).
// Route: /admin-user-password?id=<userId>&name=<userName>

export default function AdminUserPasswordScreen() {
  const colors = useColors();
  const router = useRouter();
  const { id: rawId, name: rawName } = useLocalSearchParams<{
    id: string;
    name?: string;
  }>();
  const userId = Number(rawId);
  const userName = rawName ?? "this user";

  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => setUserPassword(userId, password),
    onSuccess: (data) => {
      setPassword("");
      setConfirm("");
      setNotice(data.message);
      setError(null);
    },
    onError: (err: { message?: string }) => {
      setError(err?.message ?? "Failed to update password.");
      setNotice(null);
    },
  });

  function submit() {
    setNotice(null);
    setError(null);

    if (!Number.isFinite(userId) || userId <= 0) {
      setError("Invalid user ID.");
      return;
    }
    if (password.length < 8) {
      setError("Password must be at least 8 characters.");
      return;
    }
    if (password.length > 72) {
      setError("Password must be 72 characters or fewer.");
      return;
    }
    if (password !== confirm) {
      setError("Passwords do not match.");
      return;
    }

    if (Platform.OS === "web") {
      if (!window.confirm(`Set a new password for ${userName}?`)) return;
      mutation.mutate();
    } else {
      Alert.alert(
        "Set password",
        `Set a new password for ${userName}? They can sign in immediately with the new credential.`,
        [
          { text: "Cancel", style: "cancel" },
          {
            text: "Set password",
            style: "destructive",
            onPress: () => mutation.mutate(),
          },
        ],
      );
    }
  }

  const busy = mutation.isPending;

  return (
    <KeyboardAvoidingView
      style={{ flex: 1 }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <Stack.Screen
        options={{
          title: "Set password",
          headerStyle: { backgroundColor: colors.background },
          headerTintColor: colors.text,
        }}
      />
      <ScrollView
        style={[styles.root, { backgroundColor: colors.background }]}
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <View style={styles.cardHeader}>
            <Feather name="key" size={18} color={colors.primary} style={styles.cardIcon} />
            <Text style={[styles.cardTitle, { color: colors.text }]}>
              Set password for {userName}
            </Text>
          </View>
          <Text style={[styles.hint, { color: colors.muted }]}>
            The user can sign in at /login immediately with the new password.
            Does not affect OTP or social logins. Protected accounts cannot be
            changed.
          </Text>

          {notice ? (
            <View style={[styles.banner, { backgroundColor: "#064e3b22" }]}>
              <Feather name="check-circle" size={14} color="#34d399" />
              <Text style={[styles.bannerText, { color: "#34d399" }]}>
                {notice}
              </Text>
            </View>
          ) : null}

          {error ? (
            <View style={[styles.banner, { backgroundColor: "#7f1d1d22" }]}>
              <Feather name="alert-circle" size={14} color="#f87171" />
              <Text style={[styles.bannerText, { color: "#f87171" }]}>{error}</Text>
            </View>
          ) : null}

          <View style={styles.fieldWrapper}>
            <TextField
              label="New password"
              value={password}
              onChangeText={setPassword}
              secureTextEntry={!showPassword}
              autoCapitalize="none"
              autoComplete="new-password"
              placeholder="Min. 8 characters"
              returnKeyType="next"
              trailing={
                <Pressable
                  onPress={() => setShowPassword((v) => !v)}
                  style={styles.eyeBtn}
                  accessibilityLabel={showPassword ? "Hide password" : "Show password"}
                >
                  <Feather
                    name={showPassword ? "eye-off" : "eye"}
                    size={16}
                    color={colors.mutedForeground}
                  />
                </Pressable>
              }
            />
          </View>

          <View style={styles.fieldWrapper}>
            <TextField
              label="Confirm password"
              value={confirm}
              onChangeText={setConfirm}
              secureTextEntry={!showConfirm}
              autoCapitalize="none"
              autoComplete="new-password"
              placeholder="Repeat the password"
              returnKeyType="done"
              onSubmitEditing={submit}
              trailing={
                <Pressable
                  onPress={() => setShowConfirm((v) => !v)}
                  style={styles.eyeBtn}
                  accessibilityLabel={showConfirm ? "Hide password" : "Show password"}
                >
                  <Feather
                    name={showConfirm ? "eye-off" : "eye"}
                    size={16}
                    color={colors.mutedForeground}
                  />
                </Pressable>
              }
            />
          </View>

          {busy ? (
            <ActivityIndicator color={colors.primary} style={{ marginTop: 12 }} />
          ) : (
            <Button
              label="Set password"
              onPress={submit}
              style={{ marginTop: 16 }}
            />
          )}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  content: { padding: 16, paddingBottom: 40 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 20,
    gap: 4,
  },
  cardHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 6,
    gap: 8,
  },
  cardIcon: { marginTop: 1 },
  cardTitle: { fontSize: 16, fontWeight: "600" },
  hint: { fontSize: 13, lineHeight: 18, marginBottom: 12 },
  banner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderRadius: 10,
    padding: 12,
    marginBottom: 8,
  },
  bannerText: { fontSize: 13, flex: 1 },
  fieldWrapper: { marginTop: 12 },
  eyeBtn: { padding: 6 },
});
