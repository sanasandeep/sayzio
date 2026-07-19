import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Image } from "expo-image";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { useForegroundRefresh } from "@/hooks/useForegroundRefresh";
import {
  dmSend,
  dmThread,
  dmUnlockAttachment,
  type DmAttachment,
  type DmMessage,
} from "@/lib/api/dm";
import { showAlert } from "@/lib/webAlert";

/**
 * Profile-scoped DM screen (Task #1210). Mirrors the Alpine widget on
 * the web `/@handle` page: render the thread, gate sending behind the
 * creator's DM access mode, and bounce out to the system browser when
 * a checkout URL comes back (pay-to-message or per-attachment unlock).
 */
export default function ProfileDmScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { handle: rawHandle = "" } = useLocalSearchParams<{ handle?: string }>();
  const handle = String(rawHandle || "").replace(/^@/, "");
  const [draft, setDraft] = useState("");

  const threadQ = useQuery({
    queryKey: ["dm-thread", handle],
    queryFn: () => dmThread(handle),
    refetchInterval: 7000,
    enabled: !!handle,
  });

  // JS timers (and the refetchInterval above) pause while the app is
  // backgrounded, so pull the thread immediately on resume.
  useForegroundRefresh(() => {
    if (handle) threadQ.refetch();
  });

  const sendM = useMutation({
    // For pay-to-message we still need a body to satisfy the
    // server validator (min:1) — the message itself is never
    // delivered until the hosted-checkout return URL fires
    // confirmDmPayToMessage, so this is just a non-empty stub.
    mutationFn: () =>
      dmSend(
        handle,
        policy.reason === "paid_required" ? (draft.trim() || "👋") : draft.trim(),
      ),
    onSuccess: async (r: any) => {
      if (r.checkout_url) {
        await openCheckout(r.checkout_url);
        return;
      }
      if (r.ok) {
        setDraft("");
        qc.invalidateQueries({ queryKey: ["dm-thread", handle] });
      } else {
        showAlert("Couldn't send", r.reason || "Try again");
      }
    },
    onError: async (e: any) => {
      const url = e?.body?.checkout_url;
      if (url) { await openCheckout(url); return; }
      showAlert("Couldn't send", e?.message || "Try again");
    },
  });

  const unlockM = useMutation({
    mutationFn: (a: DmAttachment) => dmUnlockAttachment(a.id),
    onSuccess: async (r) => {
      if (r.checkout_url) await openCheckout(r.checkout_url);
      else qc.invalidateQueries({ queryKey: ["dm-thread", handle] });
    },
    onError: (e: Error) => showAlert("Couldn't unlock", e.message || "Try again"),
  });

  if (threadQ.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: `DM @${handle}` }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (threadQ.isError || !threadQ.data) {
    const reason = (threadQ.error as any)?.body?.reason;
    if (reason === "login_required") {
      return (
        <View style={[styles.center, { backgroundColor: colors.background, padding: 24 }]}>
          <Stack.Screen options={{ title: "Sign in" }} />
          <Feather name="log-in" size={36} color={colors.mutedForeground} />
          <Text style={{ color: colors.foreground, marginTop: 12, textAlign: "center" }}>
            Sign in to message this creator.
          </Text>
        </View>
      );
    }
    return (
      <View style={[styles.center, { backgroundColor: colors.background, padding: 24 }]}>
        <Stack.Screen options={{ title: "DM" }} />
        <Feather name="alert-triangle" size={36} color={colors.mutedForeground} />
        <Text style={{ color: colors.foreground, marginTop: 12 }}>Could not open this conversation.</Text>
      </View>
    );
  }

  const { messages, policy, conversation_id } = threadQ.data;

  const renderItem = ({ item: m }: { item: DmMessage }) => {
    const mine = m.side === "viewer";
    const sysStyle = m.kind === "system";
    return (
      <View style={[mine ? styles.rowRight : styles.rowLeft]}>
        <View
          style={[
            styles.bubble,
            sysStyle && styles.bubbleSystem,
            mine && !sysStyle && { backgroundColor: colors.primary },
            !mine && !sysStyle && { backgroundColor: colors.card, borderColor: colors.border, borderWidth: 1 },
          ]}
        >
          {!!m.body && (
            <Text style={{ color: sysStyle ? "#92400e" : (mine ? "#fff" : colors.text), fontSize: 14 }}>
              {m.body}
            </Text>
          )}
          {m.attachments.map((a) => (
            <Pressable
              key={a.id}
              onPress={() => {
                if (a.is_locked) unlockM.mutate(a);
                else if (a.url) Linking.openURL(a.url);
              }}
              style={styles.attachment}
            >
              {a.thumb_url ? (
                <Image
                  source={{ uri: a.thumb_url }}
                  style={[styles.attImg, a.is_locked && { opacity: 0.55 }]}
                  contentFit="cover"
                />
              ) : (
                <View style={[styles.attImg, { backgroundColor: colors.muted, alignItems: "center", justifyContent: "center" }]}>
                  <Feather name="file" size={22} color={colors.mutedForeground} />
                </View>
              )}
              {a.is_locked && (
                <View style={styles.lockBadge}>
                  <Feather name="lock" size={12} color="#fff" />
                  <Text style={styles.lockText}>
                    Unlock ${(a.lock_price_cents / 100).toFixed(2)}
                  </Text>
                </View>
              )}
            </Pressable>
          ))}
        </View>
      </View>
    );
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined}>
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: `DM @${handle}` }} />
        <FlatList
          data={messages}
          renderItem={renderItem}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={{ padding: 12, gap: 6 }}
          ListEmptyComponent={
            <Text style={{ textAlign: "center", color: colors.mutedForeground, marginTop: 40 }}>
              No messages yet. Say hi 👋
            </Text>
          }
        />

        <View style={[styles.composer, { backgroundColor: colors.card, borderColor: colors.border }]}>
          {policy.reason === "subs_required" ? (
            <Pressable
              onPress={() => router.push(`/profile/${handle}/subscribe`)}
              style={[styles.cta, { backgroundColor: colors.primary }]}
            >
              <Text style={styles.ctaText}>
                Subscribe to message{policy.min_tier_name ? ` · ${policy.min_tier_name}` : ""}
              </Text>
            </Pressable>
          ) : policy.reason === "paid_required" ? (
            <Pressable
              onPress={() => sendM.mutate()}
              style={[styles.cta, { backgroundColor: "#e11d48" }]}
            >
              <Feather name="lock" size={14} color="#fff" />
              <Text style={styles.ctaText}>
                {" "}Pay ${(policy.price_cents / 100).toFixed(2)} to start chatting
              </Text>
            </Pressable>
          ) : policy.reason === "throttled" ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12, textAlign: "center" }}>
              Wait for a reply before sending more messages.
            </Text>
          ) : policy.reason === "closed" || policy.reason === "account_blocked" || policy.reason === "thread_blocked" ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12, textAlign: "center" }}>
              You can't message this creator right now.
            </Text>
          ) : (
            <View style={styles.composerRow}>
              <TextInput
                value={draft}
                onChangeText={setDraft}
                placeholder="Write a message…"
                placeholderTextColor={colors.mutedForeground}
                multiline
                maxLength={5000}
                style={[styles.input, { color: colors.text, borderColor: colors.border }]}
              />
              <Pressable
                onPress={() =>
                  router.push({
                    pathname: "/dm/tip",
                    params: { conversationId: String(conversation_id), handle, name: handle },
                  })
                }
                style={styles.tipBtn}
              >
                <Feather name="heart" size={18} color="#e11d48" />
              </Pressable>
              <Pressable
                onPress={() => sendM.mutate()}
                disabled={!draft.trim() || sendM.isPending}
                style={[styles.sendBtn, { backgroundColor: colors.primary, opacity: draft.trim() ? 1 : 0.4 }]}
              >
                <Feather name="send" size={16} color="#fff" />
              </Pressable>
            </View>
          )}
        </View>
      </View>
    </KeyboardAvoidingView>
  );
}

async function openCheckout(url: string) {
  try { await WebBrowser.openBrowserAsync(url); }
  catch { Linking.openURL(url); }
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  rowLeft: { flexDirection: "row", justifyContent: "flex-start" },
  rowRight: { flexDirection: "row", justifyContent: "flex-end" },
  bubble: {
    maxWidth: "82%",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 16,
  },
  bubbleSystem: {
    backgroundColor: "#fef3c7",
    borderColor: "#fcd34d",
    borderWidth: 1,
  },
  attachment: { marginTop: 6, position: "relative", borderRadius: 10, overflow: "hidden" },
  attImg: { width: 180, height: 180, borderRadius: 10 },
  lockBadge: {
    position: "absolute", inset: 0,
    backgroundColor: "rgba(0,0,0,0.45)",
    alignItems: "center", justifyContent: "center",
    flexDirection: "row", gap: 4,
  },
  lockText: { color: "#fff", fontWeight: "700", fontSize: 12 },
  composer: { borderTopWidth: 1, padding: 10 },
  composerRow: { flexDirection: "row", alignItems: "flex-end", gap: 8 },
  input: {
    flex: 1, borderWidth: 1, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 8,
    minHeight: 40, maxHeight: 120, fontSize: 14,
  },
  tipBtn: {
    width: 40, height: 40, borderRadius: 12, alignItems: "center", justifyContent: "center",
    borderWidth: 1, borderColor: "#fda4af",
  },
  sendBtn: {
    width: 40, height: 40, borderRadius: 12, alignItems: "center", justifyContent: "center",
  },
  cta: { flexDirection: "row", alignItems: "center", justifyContent: "center", paddingVertical: 12, borderRadius: 12 },
  ctaText: { color: "#fff", fontWeight: "700", fontSize: 14 },
});
