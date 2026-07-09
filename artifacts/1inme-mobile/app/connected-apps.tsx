import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import {
  connectedApps,
  type ConnectedAppProvider,
} from "@/lib/api/connectedApps";
import { showAlert } from "@/lib/webAlert";

/**
 * Mobile parity for the web "/user/connected-apps" area (Task #3163).
 * Lists every provider (Salesforce, HubSpot, Zoho, Google Analytics 4),
 * lets the creator connect CRMs via the shared stateless OAuth flow
 * (opened in the system browser, returning to the sayzio:// deep link),
 * connect GA4 by pasting a Measurement ID + API secret, toggle
 * push/pull/pause, pull now, and disconnect. Everything provider-specific
 * comes from the data-driven registry surfaced by the API.
 */
export default function ConnectedAppsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const plan = usePlanFeatures();
  const locked = plan.isFeatureLocked("connected_apps");
  const params = useLocalSearchParams<{
    oauth_status?: string | string[];
    oauth_message?: string | string[];
  }>();
  const shownOauth = useRef(false);

  const query = useQuery({
    queryKey: ["connected-apps"],
    queryFn: connectedApps.list,
  });

  // Surface the outcome of an OAuth round-trip bounced back from
  // oauth-callback.tsx, then refresh so a freshly-connected CRM shows.
  useEffect(() => {
    const status = Array.isArray(params.oauth_status)
      ? params.oauth_status[0]
      : params.oauth_status;
    if (!status || shownOauth.current) return;
    shownOauth.current = true;
    const message = Array.isArray(params.oauth_message)
      ? params.oauth_message[0]
      : params.oauth_message;
    qc.invalidateQueries({ queryKey: ["connected-apps"] });
    showAlert(
      status === "ok" ? "Connected" : "Connection failed",
      message || (status === "ok" ? "The app is now connected." : "Something went wrong."),
    );
  }, [params.oauth_status, params.oauth_message, qc]);

  const promptUpgrade = () =>
    showUpgradePrompt({
      message:
        "Connected Apps (CRM sync and Google Analytics forwarding) is a plan feature. Upgrade to connect your tools.",
    });

  const connect = useMutation({
    mutationFn: (provider: string) => connectedApps.connectUrl(provider),
    onSuccess: async (url) => {
      try {
        await WebBrowser.openBrowserAsync(url);
      } catch {
        Linking.openURL(url);
      }
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert("Connect failed", e?.message ?? "Unknown error");
    },
  });

  const patch = useMutation({
    mutationFn: (v: {
      id: number;
      body: {
        push_enabled?: boolean;
        pull_enabled?: boolean;
        paused?: boolean;
        field_mappings?: Record<string, string>;
      };
    }) => connectedApps.update(v.id, v.body),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["connected-apps"] }),
    onError: (e: any) => showAlert("Update failed", e?.message ?? "Unknown error"),
  });

  const pull = useMutation({
    mutationFn: (id: number) => connectedApps.syncNow(id),
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ["connected-apps"] });
      showAlert("Sync complete", `${r.imported} contact(s) imported.`);
    },
    onError: (e: any) => showAlert("Sync failed", e?.message ?? "Unknown error"),
  });

  const remove = useMutation({
    mutationFn: (id: number) => connectedApps.disconnect(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["connected-apps"] }),
    onError: (e: any) => showAlert("Disconnect failed", e?.message ?? "Unknown error"),
  });

  const confirmRemove = (id: number, label: string) =>
    showAlert("Disconnect", `Disconnect ${label}? Synced data is kept.`, [
      { text: "Cancel", style: "cancel" },
      { text: "Disconnect", style: "destructive", onPress: () => remove.mutate(id) },
    ]);

  const providers = query.data?.providers ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Connected Apps" }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 60, gap: 12 }}>
        <Text style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}>
          Connect your CRM to sync leads and contacts both ways, or forward click
          events to Google Analytics 4. Credentials are stored encrypted.
        </Text>

        {locked ? (
          <TouchableOpacity
            onPress={promptUpgrade}
            style={[styles.lockCard, { backgroundColor: colors.primary + "12", borderColor: colors.primary + "44" }]}
          >
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={{ color: colors.text, fontWeight: "700", fontSize: 14 }}>
                Connected Apps is a plan feature
              </Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12, lineHeight: 16 }}>
                Upgrade to connect Salesforce, HubSpot, Zoho and GA4. Tap to see options.
              </Text>
            </View>
            <UpgradeLockBadge />
          </TouchableOpacity>
        ) : null}

        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : query.isError ? (
          <Text style={{ color: colors.destructive }}>Could not load connected apps.</Text>
        ) : (
          providers.map((p) => (
            <ProviderCard
              key={p.key}
              provider={p}
              locked={locked}
              busy={
                (connect.isPending && connect.variables === p.key) ||
                patch.isPending ||
                pull.isPending ||
                remove.isPending
              }
              onConnect={() => (locked ? promptUpgrade() : connect.mutate(p.key))}
              onSaveGa={async (mid, secret) => {
                if (locked) return promptUpgrade();
                try {
                  await connectedApps.saveGoogleAnalytics(mid, secret);
                  qc.invalidateQueries({ queryKey: ["connected-apps"] });
                  showAlert("Saved", "Google Analytics connected.");
                } catch (e: any) {
                  if (!handlePlanLockedError(e)) {
                    showAlert("Save failed", e?.message ?? "Unknown error");
                  }
                }
              }}
              onTogglePush={(v) =>
                p.connection && patch.mutate({ id: p.connection.id, body: { push_enabled: v } })
              }
              onTogglePull={(v) =>
                p.connection && patch.mutate({ id: p.connection.id, body: { pull_enabled: v } })
              }
              onTogglePause={(v) =>
                p.connection && patch.mutate({ id: p.connection.id, body: { paused: v } })
              }
              onSaveMappings={(m) => {
                if (!p.connection) return;
                patch.mutate({ id: p.connection.id, body: { field_mappings: m } });
                showAlert("Saved", "Field mapping updated.");
              }}
              onPull={() => p.connection && pull.mutate(p.connection.id)}
              onDisconnect={() =>
                p.connection && confirmRemove(p.connection.id, p.label)
              }
            />
          ))
        )}
      </ScrollView>
    </View>
  );
}

const SAYZIO_FIELD_LABELS: Record<string, string> = {
  email: "Email",
  first_name: "First name",
  last_name: "Last name",
  phone: "Phone",
  company: "Company",
  display_name: "Full name",
};

function ProviderCard({
  provider,
  locked,
  busy,
  onConnect,
  onSaveGa,
  onTogglePush,
  onTogglePull,
  onTogglePause,
  onSaveMappings,
  onPull,
  onDisconnect,
}: {
  provider: ConnectedAppProvider;
  locked: boolean;
  busy: boolean;
  onConnect: () => void;
  onSaveGa: (measurementId: string, apiSecret: string) => void;
  onTogglePush: (v: boolean) => void;
  onTogglePull: (v: boolean) => void;
  onTogglePause: (v: boolean) => void;
  onSaveMappings: (mappings: Record<string, string>) => void;
  onPull: () => void;
  onDisconnect: () => void;
}) {
  const colors = useColors();
  const conn = provider.connection;
  const isGa = provider.connect_type === "config";
  const isCrm = provider.kind === "crm";
  const [mid, setMid] = useState(conn?.measurement_id ?? "");
  const [secret, setSecret] = useState("");
  const [showMap, setShowMap] = useState(false);
  const [mappings, setMappings] = useState<Record<string, string>>(
    conn?.field_mappings ?? {},
  );

  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
        <View style={{ flex: 1 }}>
          <Text style={{ color: colors.text, fontWeight: "700", fontSize: 16 }}>
            {provider.label}
          </Text>
          <Text style={{ color: colors.mutedForeground, fontSize: 12, lineHeight: 16, marginTop: 2 }}>
            {provider.blurb}
          </Text>
        </View>
        <StatusPill provider={provider} />
      </View>

      {!conn && !provider.available ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 10 }}>
          {provider.label} isn't available yet — check back soon.
        </Text>
      ) : !conn ? (
        isGa ? (
          <View style={{ gap: 8, marginTop: 12 }}>
            <TextInput
              value={mid}
              onChangeText={setMid}
              placeholder="Measurement ID (G-XXXXXXXXXX)"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="characters"
              style={[styles.input, { color: colors.text, borderColor: colors.border, backgroundColor: colors.background }]}
            />
            <TextInput
              value={secret}
              onChangeText={setSecret}
              placeholder="Measurement Protocol API secret"
              placeholderTextColor={colors.mutedForeground}
              secureTextEntry
              style={[styles.input, { color: colors.text, borderColor: colors.border, backgroundColor: colors.background }]}
            />
            <Button
              label="Connect Google Analytics"
              onPress={() => onSaveGa(mid.trim(), secret.trim())}
              disabled={busy || locked || !mid.trim() || !secret.trim()}
            />
          </View>
        ) : (
          <View style={{ marginTop: 12 }}>
            <Button
              label={`Connect ${provider.label}`}
              onPress={onConnect}
              disabled={busy || locked}
            />
          </View>
        )
      ) : (
        <View style={{ gap: 10, marginTop: 12 }}>
          {conn.account_label ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {isGa ? "Property: " : "Account: "}
              <Text style={{ color: colors.text }}>{conn.account_label}</Text>
            </Text>
          ) : null}

          <SyncStatus conn={conn} isCrm={isCrm} />


          {provider.capabilities.push ? (
            <ToggleRow
              label="Push new leads & contacts"
              value={conn.push_enabled}
              onChange={onTogglePush}
              disabled={busy}
            />
          ) : null}
          {provider.capabilities.pull ? (
            <ToggleRow
              label="Pull contacts inbound"
              value={conn.pull_enabled}
              onChange={onTogglePull}
              disabled={busy}
            />
          ) : null}
          <ToggleRow
            label="Pause syncing"
            value={conn.paused}
            onChange={onTogglePause}
            disabled={busy}
          />

          {isCrm && Object.keys(mappings).length > 0 ? (
            <View style={{ gap: 8 }}>
              <TouchableOpacity
                onPress={() => setShowMap((s) => !s)}
                style={[styles.mapToggle, { borderColor: colors.border }]}
              >
                <Text style={{ color: colors.text, fontSize: 13, fontWeight: "600" }}>
                  Field mapping
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {showMap ? "Hide" : "Edit"}
                </Text>
              </TouchableOpacity>
              {showMap ? (
                <View style={{ gap: 8 }}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    Map each Sayzio field to the matching {provider.label} field.
                  </Text>
                  {Object.keys(mappings).map((key) => (
                    <View key={key} style={{ gap: 2 }}>
                      <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                        {SAYZIO_FIELD_LABELS[key] ?? key}
                      </Text>
                      <TextInput
                        value={mappings[key]}
                        onChangeText={(t) => setMappings((m) => ({ ...m, [key]: t }))}
                        autoCapitalize="none"
                        style={[
                          styles.input,
                          { color: colors.text, borderColor: colors.border, backgroundColor: colors.background },
                        ]}
                      />
                    </View>
                  ))}
                  <Button
                    label="Save mapping"
                    variant="outline"
                    onPress={() => onSaveMappings(mappings)}
                    disabled={busy}
                  />
                </View>
              ) : null}
            </View>
          ) : null}

          <View style={{ flexDirection: "row", gap: 8, marginTop: 4 }}>
            {provider.capabilities.pull ? (
              <View style={{ flex: 1 }}>
                <Button label="Pull now" variant="outline" onPress={onPull} disabled={busy} />
              </View>
            ) : null}
            <View style={{ flex: 1 }}>
              <Button label="Disconnect" variant="outline" onPress={onDisconnect} disabled={busy} />
            </View>
          </View>
        </View>
      )}
    </View>
  );
}

function timeAgo(iso?: string | null): string {
  if (!iso) return "Never synced";
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return "Never synced";
  const secs = Math.max(0, Math.round((Date.now() - then) / 1000));
  if (secs < 60) return "Just now";
  const mins = Math.round(secs / 60);
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.round(hrs / 24);
  return `${days}d ago`;
}

function SyncStatus({
  conn,
  isCrm,
}: {
  conn: NonNullable<ConnectedAppProvider["connection"]>;
  isCrm: boolean;
}) {
  const colors = useColors();
  const hasError = conn.last_sync_status === "error" || !!conn.last_sync_error;
  const lastSynced = isCrm
    ? conn.last_synced_at ?? conn.last_pull_at
    : conn.last_synced_at;
  const row = (label: string, value: string) => (
    <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
      <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>{label}</Text>
      <Text style={{ color: colors.text, fontSize: 12, fontWeight: "600" }}>{value}</Text>
    </View>
  );
  return (
    <View style={[styles.syncBox, { backgroundColor: colors.background, borderColor: colors.border }]}>
      {row("Last synced", timeAgo(lastSynced))}
      {isCrm ? (
        <>
          {row("Records sent", String(conn.records_sent ?? 0))}
          {row("Records pulled", String(conn.records_pulled ?? 0))}
        </>
      ) : (
        row("Events forwarded", String(conn.records_sent ?? 0))
      )}
      {hasError ? (
        <Text style={{ color: colors.destructive, fontSize: 11, marginTop: 2 }}>
          {conn.last_sync_error || "Last sync failed — try reconnecting."}
        </Text>
      ) : null}
    </View>
  );
}

function StatusPill({ provider }: { provider: ConnectedAppProvider }) {
  const colors = useColors();
  const conn = provider.connection;
  let label = provider.status.label;
  let color = colors.mutedForeground;
  if (conn) {
    if (conn.paused) {
      label = "Paused";
      color = colors.destructive;
    } else if (conn.status === "error") {
      label = "Error";
      color = colors.destructive;
    } else {
      label = "Connected";
      color = colors.success;
    }
  } else if (provider.available) {
    label = "Available";
    color = colors.success;
  }
  return (
    <View style={[styles.pill, { borderColor: color + "55", backgroundColor: color + "18" }]}>
      <Text style={{ color, fontSize: 11, fontWeight: "700" }}>{label}</Text>
    </View>
  );
}

function ToggleRow({
  label,
  value,
  onChange,
  disabled,
}: {
  label: string;
  value: boolean;
  onChange: (v: boolean) => void;
  disabled?: boolean;
}) {
  const colors = useColors();
  return (
    <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
      <Text style={{ color: colors.text, fontSize: 13, flex: 1 }}>{label}</Text>
      <Switch value={value} onValueChange={onChange} disabled={disabled} />
    </View>
  );
}

const styles = StyleSheet.create({
  lockCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
    borderRadius: 14,
  },
  card: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
  },
  pill: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
  },
  mapToggle: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderRadius: 10,
  },
  syncBox: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    gap: 4,
  },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
  },
});
