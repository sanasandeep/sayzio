import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  browseCloud,
  disconnectCloud,
  getCloudConnections,
  getCloudLibrary,
  removeFromCloudLibrary,
  saveToCloudLibrary,
  startCloudConnect,
  type CloudConnection,
  type CloudProviderInfo,
  type CloudRemoteFile,
} from "@/lib/api/cloudFiles";
import { showAlert } from "@/lib/webAlert";

const PROVIDER_ICON: Record<string, keyof typeof Feather.glyphMap> = {
  google_drive: "hard-drive",
  dropbox: "box",
  onedrive: "cloud",
};

/**
 * Mobile parity for the web Cloud File Library (/user/cloud-files).
 * Connect a provider via OAuth (in-app browser → deep link bounce), browse
 * the provider's folders, save files into the shared workspace library, and
 * manage the library. Attaching library files to posts/tasks/inbox replies is
 * handled in the respective composers via lib/api/cloudFiles.
 */
export default function CloudFilesScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [browsing, setBrowsing] = useState<CloudConnection | null>(null);

  const connections = useQuery({
    queryKey: ["cloud-connections"],
    queryFn: getCloudConnections,
  });
  const library = useQuery({
    queryKey: ["cloud-library"],
    queryFn: () => getCloudLibrary(),
  });

  const connect = useMutation({
    mutationFn: (provider: string) => startCloudConnect(provider),
    onSuccess: async (r) => {
      try {
        const result = await WebBrowser.openAuthSessionAsync(
          r.authorize_url,
          "sayzio://cloud-oauth",
        );
        if (result.type === "success" && result.url) {
          const q = parseQuery(result.url);
          if (q.error) {
            showAlert("Connect failed", friendlyError(q.error));
          } else if (q.status === "connected") {
            qc.invalidateQueries({ queryKey: ["cloud-connections"] });
          }
        }
      } catch {
        Linking.openURL(r.authorize_url);
      } finally {
        qc.invalidateQueries({ queryKey: ["cloud-connections"] });
      }
    },
    onError: (e: any) =>
      showAlert("Connect failed", e?.message ?? "Unknown error"),
  });

  const disconnect = useMutation({
    mutationFn: (id: number) => disconnectCloud(id),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["cloud-connections"] }),
  });

  const removeFile = useMutation({
    mutationFn: (id: number) => removeFromCloudLibrary(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["cloud-library"] }),
  });

  const data = connections.data;
  const connByProvider = new Map<string, CloudConnection>(
    (data?.connections ?? []).map((c) => [c.provider, c] as const),
  );

  const confirmDisconnect = (c: CloudConnection) => {
    const go = () => disconnect.mutate(c.id);
    if (Platform.OS === "web") {
      if (confirm(`Disconnect ${c.provider_label}?`)) go();
    } else {
      showAlert("Disconnect?", c.provider_label, [
        { text: "Cancel", style: "cancel" },
        { text: "Disconnect", style: "destructive", onPress: go },
      ]);
    }
  };

  const confirmRemove = (id: number, name: string) => {
    const go = () => removeFile.mutate(id);
    if (Platform.OS === "web") {
      if (confirm(`Remove ${name} from the library?`)) go();
    } else {
      showAlert("Remove from library?", name, [
        { text: "Cancel", style: "cancel" },
        { text: "Remove", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Cloud files" }} />
      <FlatList
        data={library.data?.files ?? []}
        keyExtractor={(f) => String(f.id)}
        contentContainerStyle={{ padding: 16, paddingBottom: 60, gap: 10 }}
        refreshControl={
          <RefreshControl
            refreshing={
              (connections.isFetching && !connections.isLoading) ||
              (library.isFetching && !library.isLoading)
            }
            onRefresh={() => {
              connections.refetch();
              library.refetch();
            }}
            tintColor={colors.primary}
          />
        }
        ListHeaderComponent={
          <View style={{ gap: 12, marginBottom: 6 }}>
            <Text
              style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}
            >
              Connect a cloud account to browse and save files into your shared
              workspace library. Files stay in the provider; Sayzio only keeps a
              link.
            </Text>

            {connections.isLoading ? (
              <ActivityIndicator color={colors.primary} />
            ) : (
              (data?.providers ?? []).map((p) => (
                <ProviderRow
                  key={p.provider}
                  provider={p}
                  conn={connByProvider.get(p.provider)}
                  colors={colors}
                  connecting={connect.isPending}
                  onConnect={() => connect.mutate(p.provider)}
                  onBrowse={(c) => setBrowsing(c)}
                  onDisconnect={confirmDisconnect}
                />
              ))
            )}

            <Text
              style={{
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_700Bold",
                fontSize: 16,
                marginTop: 8,
              }}
            >
              Workspace library
              {library.data?.meta?.total
                ? ` (${library.data.meta.total})`
                : ""}
            </Text>
          </View>
        }
        renderItem={({ item }) => (
          <View
            style={[
              styles.row,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <View
              style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}
            >
              <Feather
                name={PROVIDER_ICON[item.provider] ?? "file"}
                size={18}
                color={colors.primary}
              />
            </View>
            <Pressable
              style={{ flex: 1, gap: 2 }}
              onPress={() => Linking.openURL(item.link)}
            >
              <Text
                style={[styles.name, { color: colors.foreground }]}
                numberOfLines={1}
              >
                {item.name}
              </Text>
              <Text
                style={[styles.sub, { color: colors.mutedForeground }]}
                numberOfLines={1}
              >
                {item.provider_label} • {item.human_size}
                {item.added_by ? ` • ${item.added_by}` : ""}
              </Text>
            </Pressable>
            <Pressable
              onPress={() => confirmRemove(item.id, item.name)}
              hitSlop={6}
            >
              <Feather name="trash-2" size={18} color={colors.destructive} />
            </Pressable>
          </View>
        )}
        ListEmptyComponent={
          library.isLoading ? null : (
            <EmptyState
              icon="cloud"
              title="No files saved yet"
              body="Connect a provider above, then browse and save files into your workspace library."
            />
          )
        }
      />

      {browsing ? (
        <BrowseModal
          connection={browsing}
          onClose={() => setBrowsing(null)}
          onSaved={() => {
            qc.invalidateQueries({ queryKey: ["cloud-library"] });
          }}
        />
      ) : null}
    </View>
  );
}

function ProviderRow({
  provider,
  conn,
  colors,
  connecting,
  onConnect,
  onBrowse,
  onDisconnect,
}: {
  provider: CloudProviderInfo;
  conn?: CloudConnection;
  colors: any;
  connecting: boolean;
  onConnect: () => void;
  onBrowse: (c: CloudConnection) => void;
  onDisconnect: (c: CloudConnection) => void;
}) {
  return (
    <View
      style={[
        styles.providerCard,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
        <View
          style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}
        >
          <Feather
            name={PROVIDER_ICON[provider.provider] ?? "cloud"}
            size={18}
            color={colors.primary}
          />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={[styles.name, { color: colors.foreground }]}>
            {provider.label}
          </Text>
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>
            {conn
              ? conn.is_broken
                ? "Reconnect required"
                : conn.account_email || conn.account_label || "Connected"
              : provider.configured
                ? "Not connected"
                : "Not set up by workspace owner"}
          </Text>
        </View>
      </View>

      <View style={{ flexDirection: "row", gap: 8, marginTop: 12, flexWrap: "wrap" }}>
        {conn && !conn.is_broken ? (
          <Button label="Browse" onPress={() => onBrowse(conn)} />
        ) : null}
        {provider.configured ? (
          <Button
            label={conn ? "Reconnect" : "Connect"}
            variant={conn && !conn.is_broken ? "secondary" : "primary"}
            onPress={onConnect}
            disabled={connecting}
          />
        ) : null}
        {conn ? (
          <Button
            label="Disconnect"
            variant="ghost"
            onPress={() => onDisconnect(conn)}
          />
        ) : null}
      </View>
    </View>
  );
}

function BrowseModal({
  connection,
  onClose,
  onSaved,
}: {
  connection: CloudConnection;
  onClose: () => void;
  onSaved: () => void;
}) {
  const colors = useColors();
  const [folder, setFolder] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const [selected, setSelected] = useState<Record<string, CloudRemoteFile>>({});

  const browse = useQuery({
    queryKey: ["cloud-browse", connection.id, folder, appliedSearch],
    queryFn: () =>
      browseCloud(connection.id, {
        folder,
        search: appliedSearch || null,
      }),
  });

  const save = useMutation({
    mutationFn: () =>
      saveToCloudLibrary({
        connection_id: connection.id,
        items: Object.values(selected),
      }),
    onSuccess: (r) => {
      onSaved();
      setSelected({});
      showAlert(
        "Saved",
        `${r.added} file${r.added === 1 ? "" : "s"} added to the library.`,
      );
    },
    onError: (e: any) =>
      showAlert("Could not save", e?.message ?? "Unknown error"),
  });

  const selectedCount = Object.keys(selected).length;

  return (
    <Modal animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <View
          style={[styles.modalHeader, { borderColor: colors.border }]}
        >
          <Text
            style={{
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_700Bold",
              fontSize: 17,
              flex: 1,
            }}
            numberOfLines={1}
          >
            {connection.provider_label}
          </Text>
          <Pressable onPress={onClose} hitSlop={8}>
            <Feather name="x" size={22} color={colors.foreground} />
          </Pressable>
        </View>

        <View style={{ flexDirection: "row", gap: 8, padding: 12 }}>
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder="Search files…"
            placeholderTextColor={colors.mutedForeground}
            onSubmitEditing={() => {
              setFolder(null);
              setAppliedSearch(search.trim());
            }}
            style={[
              styles.search,
              {
                color: colors.foreground,
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          />
          {appliedSearch ? (
            <Button
              label="Clear"
              variant="secondary"
              onPress={() => {
                setSearch("");
                setAppliedSearch("");
              }}
            />
          ) : null}
        </View>

        {browse.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : browse.error ? (
          <View style={styles.center}>
            <Text style={{ color: colors.destructive, textAlign: "center" }}>
              {(browse.error as any)?.message ?? "Could not list files."}
            </Text>
          </View>
        ) : (
          <FlatList
            data={[
              ...(appliedSearch ? [] : browse.data?.folders ?? []).map((f) => ({
                kind: "folder" as const,
                folder: f,
              })),
              ...(browse.data?.files ?? []).map((f) => ({
                kind: "file" as const,
                file: f,
              })),
            ]}
            keyExtractor={(it, i) =>
              it.kind === "folder" ? `d-${it.folder.id}` : `f-${it.file.id}-${i}`
            }
            contentContainerStyle={{ padding: 12, gap: 8, paddingBottom: 90 }}
            renderItem={({ item }) => {
              if (item.kind === "folder") {
                return (
                  <Pressable
                    style={[
                      styles.row,
                      { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                    ]}
                    onPress={() => {
                      setFolder(item.folder.id);
                      setAppliedSearch("");
                      setSearch("");
                    }}
                  >
                    <Feather name="folder" size={18} color={colors.primary} />
                    <Text
                      style={[styles.name, { color: colors.foreground, flex: 1 }]}
                      numberOfLines={1}
                    >
                      {item.folder.name}
                    </Text>
                    <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                  </Pressable>
                );
              }
              const f = item.file;
              const picked = !!selected[f.id];
              return (
                <Pressable
                  style={[
                    styles.row,
                    {
                      backgroundColor: colors.card,
                      borderColor: picked ? colors.primary : colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                  onPress={() =>
                    setSelected((s) => {
                      const next = { ...s };
                      if (next[f.id]) delete next[f.id];
                      else next[f.id] = f;
                      return next;
                    })
                  }
                >
                  <Feather
                    name={picked ? "check-square" : "square"}
                    size={18}
                    color={picked ? colors.primary : colors.mutedForeground}
                  />
                  <View style={{ flex: 1 }}>
                    <Text
                      style={[styles.name, { color: colors.foreground }]}
                      numberOfLines={1}
                    >
                      {f.name}
                    </Text>
                  </View>
                </Pressable>
              );
            }}
            ListHeaderComponent={
              folder && !appliedSearch ? (
                <Pressable
                  style={{ flexDirection: "row", alignItems: "center", gap: 6, paddingVertical: 6 }}
                  onPress={() => setFolder(null)}
                >
                  <Feather name="corner-left-up" size={16} color={colors.primary} />
                  <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
                    Back to top
                  </Text>
                </Pressable>
              ) : null
            }
            ListEmptyComponent={
              <EmptyState icon="folder" title="Nothing here" body="This folder is empty." />
            }
          />
        )}

        {selectedCount > 0 ? (
          <View
            style={[
              styles.saveBar,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Button
              label={
                save.isPending
                  ? "Saving…"
                  : `Save ${selectedCount} to library`
              }
              onPress={() => save.mutate()}
              disabled={save.isPending}
            />
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

function parseQuery(url: string): Record<string, string> {
  const out: Record<string, string> = {};
  const qi = url.indexOf("?");
  if (qi < 0) return out;
  for (const pair of url.slice(qi + 1).split("&")) {
    const [k, v] = pair.split("=");
    if (k) out[decodeURIComponent(k)] = decodeURIComponent(v ?? "");
  }
  return out;
}

function friendlyError(code: string): string {
  const map: Record<string, string> = {
    access_denied: "You cancelled the connection.",
    expired: "The connection request expired. Please try again.",
    invalid_state: "The connection request was invalid. Please try again.",
    forbidden: "You don't have permission to manage files in this workspace.",
    provider_not_configured:
      "This provider isn't set up by the workspace owner yet.",
    exchange_failed: "The provider rejected the connection. Please try again.",
  };
  return map[code] ?? code;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  providerCard: { borderWidth: 1, borderRadius: 14, padding: 14 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  modalHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
    paddingTop: 52,
    borderBottomWidth: 1,
  },
  search: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  saveBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    padding: 16,
    borderTopWidth: 1,
  },
});
