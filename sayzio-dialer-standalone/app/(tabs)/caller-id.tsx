import { useRouter } from "expo-router";
import { useState } from "react";
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { ChannelActions } from "@/components/ChannelActions";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";

const E164 = /^\+[1-9]\d{6,14}$/;

/** Normalize loose user input toward E.164 ("+" + digits). */
function normalize(raw: string): string {
  const trimmed = raw.trim();
  const hasPlus = trimmed.startsWith("+");
  const digits = trimmed.replace(/[^\d]/g, "");
  if (!digits) return "";
  return hasPlus ? `+${digits}` : `+${digits}`;
}

export default function CallerIdScreen() {
  const colors = useColors();
  const router = useRouter();
  const [number, setNumber] = useState("");

  const normalized = normalize(number);
  const valid = E164.test(normalized);

  const lookup = () => {
    if (!valid) return;
    router.push({ pathname: "/dialer-profile", params: { number: normalized } });
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={[styles.title, { color: colors.foreground }]}>
          Who's calling?
        </Text>
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          Enter any phone number in international format to see who it belongs
          to, whether it's saved, spam or a Sayzio member — then log the call,
          add notes and set a follow-up.
        </Text>

        <View style={styles.field}>
          <TextField
            label="Phone number"
            placeholder="+1 555 123 4567"
            keyboardType="phone-pad"
            autoCapitalize="none"
            value={number}
            onChangeText={setNumber}
            onSubmitEditing={lookup}
            returnKeyType="search"
          />
          {number.length > 0 && !valid ? (
            <Text style={[styles.hint, { color: colors.destructive }]}>
              Enter a full international number, e.g. +15551234567
            </Text>
          ) : normalized ? (
            <Text style={[styles.hint, { color: colors.mutedForeground }]}>
              Looking up {normalized}
            </Text>
          ) : null}
          {valid ? (
            <View style={{ marginTop: 4 }}>
              <ChannelActions
                number={normalized}
                size="sm"
                align="flex-start"
              />
            </View>
          ) : null}
        </View>

        <Button label="Look up number" onPress={lookup} disabled={!valid} />

        <View style={styles.spacer} />
        <EmptyState
          icon="shield"
          title="Spam & block aware"
          body="Numbers you flag as spam or blocked are remembered across all your devices and shown here the next time they call."
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  content: { padding: 20, gap: 16 },
  title: { fontSize: 26, fontFamily: "SpaceGrotesk_700Bold" },
  sub: { fontSize: 15, lineHeight: 21, fontFamily: "SpaceGrotesk_400Regular" },
  field: { gap: 6 },
  hint: { fontSize: 13, fontFamily: "SpaceGrotesk_400Regular" },
  spacer: { height: 12 },
});
