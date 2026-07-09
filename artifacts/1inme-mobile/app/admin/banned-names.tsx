import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  bulkCreateBannedNames,
  createBannedName,
  deleteBannedName,
  exportBannedNames,
  getBannedNameConflicts,
  listBannedNames,
  restoreBannedNameDefaults,
  toggleBannedNameForceRename,
  type BannedName,
  type BannedNameConflictRow,
} from "@/lib/api/admin";
import { showAlert } from "@/lib/webAlert";

// Mobile parity for the web back-office "Banned names / reserved handles"
// page. Each entry blocks one exact name (case-insensitive) from being used
// as a profile handle or any link alias. Gated server-side behind the
// admin-guard `settings.manage` permission (403 otherwise).
export default function BannedNamesScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const query = useQuery({
    queryKey: ["admin-banned-names"],
    queryFn: listBannedNames,
  });

  const [name, setName] = useState("");
  const [note, setNote] = useState("");
  const [forceRename, setForceRename] = useState(false);
  const [bulk, setBulk] = useState("");
  const [showBulk, setShowBulk] = useState(false);
  const [conflictsFor, setConflictsFor] = useState<{
    item: BannedName;
    rows: BannedNameConflictRow[];
  } | null>(null);

  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["admin-banned-names"] });

  const add = useMutation({
    mutationFn: () => createBannedName(name.trim(), note, forceRename),
    onSuccess: () => {
      invalidate();
      setName("");
      setNote("");
      setForceRename(false);
    },
    onError: (e: any) =>
      showAlert("Couldn't add name", e?.message ?? "Try again."),
  });

  const toggleForce = useMutation({
    mutationFn: (id: number) => toggleBannedNameForceRename(id),
    onSuccess: () => invalidate(),
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const bulkAdd = useMutation({
    mutationFn: () => bulkCreateBannedNames(bulk, note),
    onSuccess: (res) => {
      invalidate();
      setBulk("");
      setShowBulk(false);
      showAlert(
        "Bulk import complete",
        `Added ${res.stats.imported}, skipped ${res.stats.duplicates} duplicate(s), rejected ${res.stats.rejected}.`,
      );
    },
    onError: (e: any) =>
      showAlert("Couldn't import", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteBannedName(id),
    onSuccess: invalidate,
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const restore = useMutation({
    mutationFn: () => restoreBannedNameDefaults(),
    onSuccess: (res) => {
      invalidate();
      showAlert("Defaults restored", res.message);
    },
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const loadConflicts = useMutation({
    mutationFn: (id: number) => getBannedNameConflicts(id),
    onSuccess: (res) => setConflictsFor(res),
    onError: (e: any) => showAlert("Error", e?.message ?? "Try again."),
  });

  const doExport = useMutation({
    mutationFn: () => exportBannedNames(),
    onSuccess: async (res) => {
      try {
        await Share.share({
          title: res.filename,
          message: res.csv,
        });
      } catch {
        showAlert("Export", `${res.count} name(s) ready but sharing failed.`);
      }
    },
    onError: (e: any) => showAlert("Couldn't export", e?.message ?? "Try again."),
  });

  const confirmRemove = (item: BannedName) =>
    showAlert(
      "Remove reserved name?",
      `"${item.name}" will become available again.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Remove",
          style: "destructive",
          onPress: () => remove.mutate(item.id),
        },
      ],
    );

  const data = query.data;
  const items = data?.items ?? [];
  const nameValid = /^[A-Za-z0-9_-]+$/.test(name.trim());

  if (conflictsFor) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen
          options={{ title: conflictsFor.item.name, headerBackTitle: "Back" }}
        />
        <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 64 }}>
          <Pressable
            onPress={() => setConflictsFor(null)}
            style={{ flexDirection: "row", alignItems: "center", gap: 6 }}
          >
            <Feather name="arrow-left" size={16} color={colors.primary} />
            <Text style={{ color: colors.primary, fontWeight: "600" }}>
              Back to list
            </Text>
          </Pressable>
          <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
            Existing accounts and links already using this name.
          </Text>
          {conflictsFor.rows.length === 0 ? (
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Feather name="check-circle" size={18} color={colors.success} />
              <Text style={{ color: colors.foreground, marginTop: 6 }}>
                No conflicts — nothing currently uses this name.
              </Text>
            </View>
          ) : (
            conflictsFor.rows.map((r) => (
              <View
                key={`${r.kind}:${r.id}`}
                style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border, padding: 12 }]}
              >
                <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                  <Feather
                    name={r.kind === "user" ? "user" : "link"}
                    size={13}
                    color={colors.primary}
                  />
                  <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                    {r.label}
                  </Text>
                  {r.acknowledged ? (
                    <View style={[styles.ackPill, { backgroundColor: colors.muted }]}>
                      <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
                        acknowledged
                      </Text>
                    </View>
                  ) : null}
                </View>
                <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 3 }}>
                  {r.detail}
                </Text>
                {r.owner ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                    Owner: {r.owner.name ?? "—"}
                    {r.owner.handle ? ` (@${r.owner.handle})` : ""}
                  </Text>
                ) : null}
              </View>
            ))
          )}
        </ScrollView>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Reserved names", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You don't have permission to manage reserved names."
                : "Couldn't load reserved names."}
            </Text>
          </View>
        ) : (
          <>
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.head}>
                <Feather name="slash" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Reserved handles
                </Text>
              </View>
              <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                These names can't be used as profile handles or link aliases.
              </Text>
              <View style={{ flexDirection: "row", gap: 8, marginTop: 12, flexWrap: "wrap" }}>
                <Button
                  label="Restore defaults"
                  variant="outline"
                  onPress={() => restore.mutate()}
                  loading={restore.isPending}
                />
                <Button
                  label="Export"
                  variant="outline"
                  onPress={() => doExport.mutate()}
                  loading={doExport.isPending}
                />
              </View>
            </View>

            {/* Add single */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Add a reserved name
              </Text>
              <View style={{ gap: 10, marginTop: 10 }}>
                <TextField
                  label="Name"
                  placeholder="e.g. admin"
                  autoCapitalize="none"
                  autoCorrect={false}
                  value={name}
                  onChangeText={setName}
                />
                <TextField
                  label="Note (optional)"
                  placeholder="Why is this reserved?"
                  value={note}
                  onChangeText={setNote}
                />
                <View style={styles.switchRow}>
                  <View style={{ flex: 1, paddingRight: 12 }}>
                    <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 14 }}>
                      Force rename on login
                    </Text>
                    <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                      Prompt existing users with this name to rename next login.
                    </Text>
                  </View>
                  <Switch
                    value={forceRename}
                    onValueChange={setForceRename}
                    trackColor={{ true: colors.primary, false: colors.border }}
                  />
                </View>
                <Button
                  label="Add reserved name"
                  onPress={() => add.mutate()}
                  loading={add.isPending}
                  disabled={!nameValid}
                />
                <Pressable
                  onPress={() => setShowBulk((s) => !s)}
                  style={{ flexDirection: "row", alignItems: "center", gap: 6, marginTop: 2 }}
                >
                  <Feather
                    name={showBulk ? "chevron-up" : "chevron-down"}
                    size={15}
                    color={colors.primary}
                  />
                  <Text style={{ color: colors.primary, fontSize: 13, fontWeight: "600" }}>
                    Bulk import
                  </Text>
                </Pressable>
                {showBulk ? (
                  <View style={{ gap: 10 }}>
                    <TextField
                      label="Names (one per line or comma-separated)"
                      placeholder={"admin\nsupport\nhelp"}
                      autoCapitalize="none"
                      autoCorrect={false}
                      multiline
                      numberOfLines={5}
                      style={{ minHeight: 110, textAlignVertical: "top" }}
                      value={bulk}
                      onChangeText={setBulk}
                    />
                    <Button
                      label="Import names"
                      onPress={() => bulkAdd.mutate()}
                      loading={bulkAdd.isPending}
                      disabled={!bulk.trim()}
                    />
                  </View>
                ) : null}
              </View>
            </View>

            {/* List */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Reserved ({items.length})
              </Text>
              {items.length === 0 ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 8 }}>
                  No reserved names yet.
                </Text>
              ) : (
                items.map((item, i) => (
                  <View
                    key={item.id}
                    style={[
                      styles.row,
                      {
                        borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                        borderTopColor: colors.border,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text numberOfLines={1} style={{ color: colors.foreground, fontWeight: "600" }}>
                        {item.name}
                      </Text>
                      {item.note ? (
                        <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                          {item.note}
                        </Text>
                      ) : null}
                      {item.conflict_total && item.conflict_total > 0 ? (
                        <Pressable
                          onPress={() => loadConflicts.mutate(item.id)}
                          style={{ flexDirection: "row", alignItems: "center", gap: 4, marginTop: 3 }}
                        >
                          <Feather name="alert-circle" size={12} color={colors.warning} />
                          <Text style={{ color: colors.warning, fontSize: 12 }}>
                            {item.conflict_total} conflict(s) — view
                          </Text>
                        </Pressable>
                      ) : null}
                      <Pressable
                        onPress={() => toggleForce.mutate(item.id)}
                        disabled={toggleForce.isPending}
                        style={{ flexDirection: "row", alignItems: "center", gap: 4, marginTop: 4 }}
                      >
                        <Feather
                          name={item.force_rename_on_login ? "check-square" : "square"}
                          size={13}
                          color={item.force_rename_on_login ? colors.primary : colors.mutedForeground}
                        />
                        <Text
                          style={{
                            color: item.force_rename_on_login ? colors.primary : colors.mutedForeground,
                            fontSize: 12,
                          }}
                        >
                          Force rename on login
                        </Text>
                      </Pressable>
                    </View>
                    <Pressable
                      onPress={() => confirmRemove(item)}
                      disabled={remove.isPending}
                      hitSlop={8}
                      style={({ pressed }) => [{ padding: 4, opacity: pressed ? 0.6 : 1 }]}
                    >
                      <Feather name="x-circle" size={20} color={colors.destructive} />
                    </Pressable>
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
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
  },
  ackPill: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 999,
  },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
});
