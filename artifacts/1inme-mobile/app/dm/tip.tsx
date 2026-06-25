import { useMutation } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import { Alert, Linking, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { dmTip } from "@/lib/api/dm";

const SUGGESTED = [3, 5, 10, 20, 50, 100];

/**
 * In-DM tipping (Task #1210). Pops a sheet from the DM thread, hands
 * off to the hosted-checkout URL via WebBrowser, and bounces back to
 * the thread on confirm.
 */
export default function DmTipScreen() {
  const colors = useColors();
  const router = useRouter();
  const { conversationId = "", handle = "", name = "" } = useLocalSearchParams<{
    conversationId?: string;
    handle?: string;
    name?: string;
  }>();
  const [amount, setAmount] = useState("5");
  const [note, setNote] = useState("");

  const tipM = useMutation({
    mutationFn: () => dmTip(Number(conversationId), Math.max(100, Math.round((Number(amount) || 0) * 100)), note || undefined),
    onSuccess: async (r) => {
      if (r.checkout_url) {
        try { await WebBrowser.openBrowserAsync(r.checkout_url); }
        catch { Linking.openURL(r.checkout_url); }
        router.back();
      }
    },
    onError: (e: Error) => Alert.alert("Couldn't send tip", e.message || "Try again"),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: `Tip ${name ? `@${name}` : ""}`.trim() }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
        <Text style={{ color: colors.text, fontSize: 16, fontWeight: "700" }}>
          Send a tip{handle ? ` to @${handle}` : ""}
        </Text>
        <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
          100% goes to the creator. Sayzio takes 0%.
        </Text>

        <View style={styles.grid}>
          {SUGGESTED.map((v) => (
            <Pressable
              key={v}
              onPress={() => setAmount(String(v))}
              style={[
                styles.chip,
                {
                  backgroundColor: amount === String(v) ? "#fda4af" : colors.card,
                  borderColor: amount === String(v) ? "#f43f5e" : colors.border,
                },
              ]}
            >
              <Text style={{ color: colors.text, fontWeight: "700" }}>${v}</Text>
            </Pressable>
          ))}
        </View>

        <View>
          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginBottom: 4 }}>Amount ($)</Text>
          <TextInput
            value={amount}
            onChangeText={setAmount}
            keyboardType="decimal-pad"
            style={{
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: 10,
              paddingHorizontal: 12,
              paddingVertical: 10,
              color: colors.text,
              backgroundColor: colors.card,
              fontSize: 18,
              fontWeight: "700",
            }}
          />
        </View>

        <View>
          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginBottom: 4 }}>Message (optional)</Text>
          <TextInput
            value={note}
            onChangeText={setNote}
            placeholder="Say something nice…"
            placeholderTextColor={colors.mutedForeground}
            multiline
            maxLength={280}
            style={{
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: 10,
              paddingHorizontal: 12,
              paddingVertical: 10,
              color: colors.text,
              backgroundColor: colors.card,
              minHeight: 70,
              textAlignVertical: "top",
            }}
          />
        </View>

        <Button label="Send tip" variant="primary" onPress={() => tipM.mutate()} loading={tipM.isPending} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
    borderRadius: 999,
    minWidth: 64,
    alignItems: "center",
  },
});
