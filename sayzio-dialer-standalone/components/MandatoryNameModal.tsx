import { useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { BrandWordmark } from "@/components/Brand";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";

interface Props {
  visible: boolean;
  onSaved: () => void;
}

/**
 * Mandatory blocking modal shown to brand-new accounts (created via OTP
 * auto-register or social sign-in) that have no name yet. Cannot be
 * dismissed — the user must enter a name before proceeding into the app.
 */
export function MandatoryNameModal({ visible, onSaved }: Props) {
  const colors = useColors();
  const { refresh, clearNameRequirement } = useAuth();
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    const trimmed = name.trim();
    if (!trimmed) {
      setError("Please enter your name");
      return;
    }
    if (trimmed.length < 2) {
      setError("Name must be at least 2 characters");
      return;
    }
    setError(null);
    setBusy(true);
    try {
      await apiFetch("/profile", {
        method: "PATCH",
        body: JSON.stringify({ name: trimmed }),
      });
      // Clear the sticky requirement first so the prompt cannot re-appear on
      // the next cold launch, then refresh the cached user for the new name.
      await clearNameRequirement();
      await refresh();
      onSaved();
    } catch {
      setError("Could not save your name. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent={false}
      presentationStyle="fullScreen"
      statusBarTranslucent
      // Swallow the Android hardware back button so the prompt cannot be
      // dismissed without entering a name.
      onRequestClose={() => {}}
    >
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        style={[styles.root, { backgroundColor: colors.background }]}
      >
        <View style={styles.inner}>
          <BrandWordmark size={32} align="center" />
          <View style={{ height: 32 }} />
          <Text style={[styles.heading, { color: colors.foreground }]}>
            One last thing
          </Text>
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>
            What should we call you? You can always change this later from your profile.
          </Text>
          <View style={{ height: 28 }} />
          <View
            style={[
              styles.inputWrap,
              {
                borderColor: error ? "#ef4444" : colors.border,
                backgroundColor: colors.card,
                borderRadius: colors.radius,
              },
            ]}
          >
            <TextInput
              style={[styles.input, { color: colors.foreground }]}
              placeholder="Your name"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="words"
              autoCorrect={false}
              autoFocus
              value={name}
              onChangeText={(t) => {
                setName(t);
                if (error) setError(null);
              }}
              onSubmitEditing={handleSave}
              returnKeyType="done"
            />
          </View>
          {error ? (
            <Text style={[styles.errorText, { color: "#ef4444" }]}>
              {error}
            </Text>
          ) : null}
          <View style={{ height: 20 }} />
          <Button
            label={busy ? "" : "Continue"}
            variant="cta"
            onPress={handleSave}
            disabled={busy}
          />
          {busy ? (
            <ActivityIndicator
              color={colors.primary}
              style={{ marginTop: 12 }}
            />
          ) : null}
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    justifyContent: "center",
  },
  inner: {
    paddingHorizontal: 28,
    paddingBottom: 40,
  },
  heading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    letterSpacing: -0.4,
    marginBottom: 8,
  },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
  },
  inputWrap: {
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 2,
  },
  input: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 16,
    paddingVertical: 14,
  },
  errorText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    marginTop: 6,
  },
});
