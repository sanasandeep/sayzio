import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  createForward,
  deleteForward,
  listForwards,
  retryForwardDelivery,
  testForward,
  toggleForward,
  updateForward,
  type ForwardDestination,
  type ForwardInput,
} from "@/lib/api/inbox";
import { showAlert } from "@/lib/webAlert";

// Mobile parity for the web Inbox "Forwarding" page. Creators fan inbox
// events out to an email address or a webhook, with a delivery log and a
// manual retry for failed deliveries. Rules are scoped to the active
// workspace owner server-side.
export default function InboxForwardingScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const query = useQuery({
    queryKey: ["inbox", "forwards"],
    queryFn: listForwards,
  });

  const [editing, setEditing] = useState<ForwardDestination | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [label, setLabel] = useState("");
  const [type, setType] = useState<"email" | "webhook">("email");
  const [target, setTarget] = useState("");
  const [headerKey, setHeaderKey] = useState("");
  const [headerValue, setHeaderValue] = useState("");
  const [secret, setSecret] = useState("");

  const reset = () => {
    setEditing(null);
    setShowForm(false);
    setLabel("");
    setType("email");
    setTarget("");
    setHeaderKey("");
    setHeaderValue("");
    setSecret("");
  };

  const openCreate = () => {
    reset();
    setShowForm(true);
  };
  const openEdit = (d: ForwardDestination) => {
    setEditing(d);
    setLabel(d.label);
    setType(d.type);
    setTarget(d.target);
    setHeaderKey(d.header_key ?? "");
    setHeaderValue("");
    setSecret("");
    setShowForm(true);
  };

  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["inbox", "forwards"] });

  const saveRule = useMutation({
    mutationFn: () => {
      const input: ForwardInput = {
        label: label.trim(),
        type,
        target: target.trim(),
        header_key: headerKey.trim() || null,
        header_value: headerValue.trim() || null,
        secret: secret.trim() || null,
      };
      return editing ? updateForward(editing.id, input) : createForward(input);
    },
    onSuccess: () => {
      invalidate();
      reset();
    },
    onError: (e: any) =>
      showAlert("Couldn't save rule", e?.message ?? "Check the details."),
  });

  const toggle = useMutation({
    mutationFn: (id: number) => toggleForward(id),
    onSuccess: invalidate,
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteForward(id),
    onSuccess: invalidate,
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const test = useMutation({
    mutationFn: (id: number) => testForward(id),
    onSuccess: (res) => {
      invalidate();
      showAlert(res.sent ? "Test sent" : "Test attempted", res.message);
    },
    onError: (e: any) =>
      showAlert("Couldn't send test", e?.message ?? "Try again."),
  });

  const retry = useMutation({
    mutationFn: (id: number) => retryForwardDelivery(id),
    onSuccess: invalidate,
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const confirmDelete = (d: ForwardDestination) =>
    showAlert("Delete rule?", `"${d.label}" will stop forwarding.`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => remove.mutate(d.id),
      },
    ]);

  const data = query.data;
  const destinations = data?.destinations ?? [];
  const deliveries = data?.deliveries ?? [];

  const targetValid =
    type === "email"
      ? /\S+@\S+\.\S+/.test(target.trim())
      : /^https?:\/\/\S+/.test(target.trim());

  const statusColor = (s: string | null) => {
    if (s === "success") return colors.success;
    if (s === "failed" || s === "dead") return colors.destructive;
    return colors.mutedForeground;
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Forwarding", headerBackTitle: "Inbox" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              Couldn't load forwarding rules.
            </Text>
          </View>
        ) : (
          <>
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.head}>
                <Feather name="send" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Forward inbox events
                </Text>
              </View>
              <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                Send a copy of new inbox activity to an email address or a
                webhook endpoint.
              </Text>
            </View>

            {/* Form */}
            {showForm ? (
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                  {editing ? "Edit rule" : "New rule"}
                </Text>
                <View style={{ gap: 10, marginTop: 12 }}>
                  <TextField
                    label="Label"
                    placeholder="e.g. Sales team email"
                    value={label}
                    onChangeText={setLabel}
                  />

                  <View style={{ flexDirection: "row", gap: 8 }}>
                    {(["email", "webhook"] as const).map((t) => {
                      const active = type === t;
                      return (
                        <Pressable
                          key={t}
                          onPress={() => setType(t)}
                          style={[
                            styles.typePill,
                            {
                              backgroundColor: active
                                ? colors.primary + "1a"
                                : colors.muted,
                              borderColor: active
                                ? colors.primary + "66"
                                : colors.border,
                            },
                          ]}
                        >
                          <Feather
                            name={t === "email" ? "mail" : "link"}
                            size={14}
                            color={active ? colors.primary : colors.mutedForeground}
                          />
                          <Text
                            style={{
                              color: active ? colors.primary : colors.mutedForeground,
                              fontWeight: "600",
                              fontSize: 13,
                            }}
                          >
                            {t === "email" ? "Email" : "Webhook"}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </View>

                  <TextField
                    label={type === "email" ? "Email address" : "Webhook URL"}
                    placeholder={
                      type === "email"
                        ? "team@example.com"
                        : "https://example.com/hook"
                    }
                    autoCapitalize="none"
                    autoCorrect={false}
                    keyboardType={type === "email" ? "email-address" : "url"}
                    value={target}
                    onChangeText={setTarget}
                  />

                  {type === "webhook" ? (
                    <>
                      <TextField
                        label="Custom header key (optional)"
                        placeholder="X-Webhook-Token"
                        autoCapitalize="none"
                        value={headerKey}
                        onChangeText={setHeaderKey}
                      />
                      <TextField
                        label="Custom header value (optional)"
                        placeholder="value"
                        autoCapitalize="none"
                        value={headerValue}
                        onChangeText={setHeaderValue}
                      />
                      <TextField
                        label={
                          editing?.has_secret
                            ? "Signing secret (leave blank to keep)"
                            : "Signing secret (optional)"
                        }
                        placeholder="used to sign the payload"
                        autoCapitalize="none"
                        value={secret}
                        onChangeText={setSecret}
                      />
                    </>
                  ) : null}

                  <View style={{ flexDirection: "row", gap: 8, marginTop: 4 }}>
                    <View style={{ flex: 1 }}>
                      <Button
                        label={editing ? "Save changes" : "Create rule"}
                        onPress={() => saveRule.mutate()}
                        loading={saveRule.isPending}
                        disabled={!label.trim() || !targetValid}
                      />
                    </View>
                    <Button label="Cancel" variant="outline" onPress={reset} />
                  </View>
                </View>
              </View>
            ) : (
              <Button label="Add forwarding rule" onPress={openCreate} />
            )}

            {/* Rules list */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Rules ({destinations.length})
              </Text>
              {destinations.length === 0 ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 8 }}>
                  No forwarding rules yet.
                </Text>
              ) : (
                destinations.map((d, i) => (
                  <View
                    key={d.id}
                    style={[
                      styles.ruleRow,
                      {
                        borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                        borderTopColor: colors.border,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                        <Feather
                          name={d.type === "email" ? "mail" : "link"}
                          size={13}
                          color={colors.primary}
                        />
                        <Text numberOfLines={1} style={{ color: colors.foreground, fontWeight: "600" }}>
                          {d.label}
                        </Text>
                      </View>
                      <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                        {d.target}
                      </Text>
                      {d.last_status ? (
                        <Text style={{ color: statusColor(d.last_status), fontSize: 11, marginTop: 2 }}>
                          Last: {d.last_status}
                        </Text>
                      ) : null}
                    </View>
                    <View style={{ alignItems: "flex-end", gap: 6 }}>
                      <Pressable
                        onPress={() => toggle.mutate(d.id)}
                        style={[
                          styles.togglePill,
                          {
                            backgroundColor: d.is_active
                              ? colors.primary + "1a"
                              : colors.muted,
                            borderColor: d.is_active ? colors.primary + "55" : colors.border,
                          },
                        ]}
                      >
                        <Text
                          style={{
                            color: d.is_active ? colors.primary : colors.mutedForeground,
                            fontSize: 12,
                            fontWeight: "600",
                          }}
                        >
                          {d.is_active ? "Active" : "Off"}
                        </Text>
                      </Pressable>
                      <View style={{ flexDirection: "row", gap: 10 }}>
                        <Pressable onPress={() => openEdit(d)} hitSlop={8}>
                          <Feather name="edit-2" size={16} color={colors.mutedForeground} />
                        </Pressable>
                        <Pressable
                          onPress={() => test.mutate(d.id)}
                          disabled={!d.is_active || test.isPending}
                          hitSlop={8}
                        >
                          <Feather
                            name="zap"
                            size={16}
                            color={d.is_active ? colors.primary : colors.muted}
                          />
                        </Pressable>
                        <Pressable onPress={() => confirmDelete(d)} hitSlop={8}>
                          <Feather name="trash-2" size={16} color={colors.destructive} />
                        </Pressable>
                      </View>
                    </View>
                  </View>
                ))
              )}
            </View>

            {/* Recent deliveries */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Recent deliveries
              </Text>
              {deliveries.length === 0 ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 8 }}>
                  No deliveries logged yet.
                </Text>
              ) : (
                deliveries.map((dl, i) => (
                  <View
                    key={dl.id}
                    style={[
                      styles.ruleRow,
                      {
                        borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                        borderTopColor: colors.border,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text numberOfLines={1} style={{ color: colors.foreground, fontSize: 13 }}>
                        {dl.destination_label ?? "Rule"}
                        {dl.is_test ? " · test" : ""}
                      </Text>
                      <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                        {dl.source_type ?? "event"} · {dl.attempts} attempt(s)
                        {dl.last_response_code ? ` · HTTP ${dl.last_response_code}` : ""}
                      </Text>
                      {dl.last_error ? (
                        <Text numberOfLines={2} style={{ color: colors.destructive, fontSize: 11, marginTop: 2 }}>
                          {dl.last_error}
                        </Text>
                      ) : null}
                    </View>
                    <View style={{ alignItems: "flex-end", gap: 6 }}>
                      <Text style={{ color: statusColor(dl.status), fontSize: 12, fontWeight: "600" }}>
                        {dl.status}
                      </Text>
                      {dl.status === "failed" || dl.status === "dead" ? (
                        <Pressable
                          onPress={() => retry.mutate(dl.id)}
                          disabled={retry.isPending}
                          hitSlop={8}
                          style={{ flexDirection: "row", alignItems: "center", gap: 4 }}
                        >
                          <Feather name="refresh-cw" size={13} color={colors.primary} />
                          <Text style={{ color: colors.primary, fontSize: 12 }}>Retry</Text>
                        </Pressable>
                      ) : null}
                    </View>
                  </View>
                ))
              )}
            </View>
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  sectionTitle: { fontSize: 15, fontWeight: "700" },
  ruleRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    paddingVertical: 12,
  },
  typePill: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
  },
  togglePill: {
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
  },
});
