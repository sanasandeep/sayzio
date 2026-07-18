import React, { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useFocusEffect } from "expo-router";
import { useQueryClient } from "@tanstack/react-query";
import {
  Contact,
  DuplicateGroup,
  contactPrimaryEmail,
  contactPrimaryPhone,
  dismissDuplicates,
  fetchDuplicates,
  mergeAllDuplicates,
  mergeContacts,
} from "@/lib/api/contacts";

export default function ContactDuplicatesScreen() {
  const qc = useQueryClient();
  const [groups, setGroups] = useState<DuplicateGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [primaryIds, setPrimaryIds] = useState<Record<number, number>>({});
  const [busy, setBusy] = useState<Record<number, boolean>>({});
  const [bulkBusy, setBulkBusy] = useState(false);

  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    setError(null);
    try {
      const { groups: g } = await fetchDuplicates();
      setGroups(g);
      const defaults: Record<number, number> = {};
      g.forEach((group, idx) => {
        if (group.contacts.length > 0) {
          defaults[idx] = group.contacts[0].id;
        }
      });
      setPrimaryIds(defaults);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Could not load duplicates.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    load(true);
  }, [load]);

  const handleDismiss = useCallback(
    (groupIdx: number, group: DuplicateGroup) => {
      const message =
        "Mark these contacts as NOT duplicates? They won't appear here again.";
      const confirm = () => {
        setBusy((b) => ({ ...b, [groupIdx]: true }));
        const ids = group.ids;
        const pairs: string[] = [];
        for (let i = 0; i < ids.length; i++) {
          for (let j = i + 1; j < ids.length; j++) {
            const a = Math.min(ids[i], ids[j]);
            const b = Math.max(ids[i], ids[j]);
            pairs.push(`${a}:${b}`);
          }
        }
        dismissDuplicates(pairs)
          .then(() => {
            setGroups((prev) => prev.filter((_, i) => i !== groupIdx));
            // Keep the contacts-screen banner count in sync immediately.
            qc.invalidateQueries({ queryKey: ["contact-duplicate-count"] });
          })
          .catch((e: unknown) => {
            Alert.alert(
              "Error",
              e instanceof Error ? e.message : "Could not dismiss.",
            );
          })
          .finally(() => {
            setBusy((b) => ({ ...b, [groupIdx]: false }));
          });
      };

      if (typeof window !== "undefined" && window.confirm) {
        if (window.confirm(message)) confirm();
      } else {
        Alert.alert("Not duplicates?", message, [
          { text: "Cancel", style: "cancel" },
          { text: "Dismiss", style: "destructive", onPress: confirm },
        ]);
      }
    },
    [],
  );

  const handleMerge = useCallback(
    (groupIdx: number, group: DuplicateGroup) => {
      const primaryId = primaryIds[groupIdx] ?? group.contacts[0]?.id;
      if (!primaryId) return;
      const primary = group.contacts.find((c) => c.id === primaryId);
      const loserIds = group.ids.filter((id) => id !== primaryId);
      const loserNames = group.contacts
        .filter((c) => c.id !== primaryId)
        .map((c) => c.display_name ?? "Contact")
        .join(", ");
      const message = `Merge "${loserNames}" into "${primary?.display_name ?? "primary"}"? This cannot be undone.`;

      const confirm = () => {
        setBusy((b) => ({ ...b, [groupIdx]: true }));
        mergeContacts(primaryId, loserIds)
          .then(() => {
            setGroups((prev) => prev.filter((_, i) => i !== groupIdx));
            // Keep the contacts-screen banner count in sync immediately.
            qc.invalidateQueries({ queryKey: ["contact-duplicate-count"] });
          })
          .catch((e: unknown) => {
            Alert.alert(
              "Merge failed",
              e instanceof Error ? e.message : "Could not merge.",
            );
          })
          .finally(() => {
            setBusy((b) => ({ ...b, [groupIdx]: false }));
          });
      };

      if (typeof window !== "undefined" && window.confirm) {
        if (window.confirm(message)) confirm();
      } else {
        Alert.alert("Merge contacts?", message, [
          { text: "Cancel", style: "cancel" },
          { text: "Merge", style: "destructive", onPress: confirm },
        ]);
      }
    },
    [primaryIds],
  );

  const handleMergeAll = useCallback(() => {
    const count = groups.length;
    if (count === 0 || bulkBusy) return;
    const message = `Merge all ${count} duplicate ${count === 1 ? "group" : "groups"} at once? The first contact in each group keeps all data; the others are deleted. This cannot be undone.`;

    const confirmAction = () => {
      setBulkBusy(true);
      mergeAllDuplicates()
        .then((res) => {
          const summary = `${res.groups_merged} ${res.groups_merged === 1 ? "group" : "groups"} merged, ${res.contacts_removed} duplicate ${res.contacts_removed === 1 ? "contact" : "contacts"} removed.${res.groups_failed > 0 ? ` ${res.groups_failed} could not be merged.` : ""}`;
          if (typeof window !== "undefined" && window.alert) {
            window.alert(summary);
          } else {
            Alert.alert("Merge complete", summary);
          }
          return load(true);
        })
        .catch((e: unknown) => {
          Alert.alert(
            "Merge failed",
            e instanceof Error ? e.message : "Could not merge all duplicates.",
          );
        })
        .finally(() => {
          setBulkBusy(false);
        });
    };

    if (typeof window !== "undefined" && window.confirm) {
      if (window.confirm(message)) confirmAction();
    } else {
      Alert.alert("Merge all duplicates?", message, [
        { text: "Cancel", style: "cancel" },
        { text: "Merge all", style: "destructive", onPress: confirmAction },
      ]);
    }
  }, [groups.length, bulkBusy, load]);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#7c3aed" />
        <Text style={styles.loadingText}>Scanning for duplicates…</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>{error}</Text>
        <Pressable style={styles.retryBtn} onPress={() => load()}>
          <Text style={styles.retryBtnText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  if (groups.length === 0) {
    return (
      <View style={styles.center}>
        <Text style={styles.emptyIcon}>✓</Text>
        <Text style={styles.emptyTitle}>No duplicates found</Text>
        <Text style={styles.emptySubtitle}>
          Your address book looks clean! Pull down to re-check.
        </Text>
        <ScrollView
          style={{ flex: 0 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
        >
          <View style={{ height: 1 }} />
        </ScrollView>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <Text style={styles.header}>
        {groups.length} duplicate {groups.length === 1 ? "group" : "groups"} found
      </Text>
      <Text style={styles.subheader}>
        Select a primary contact in each group, then merge or dismiss.
      </Text>

      <Pressable
        style={[styles.mergeAllBtn, bulkBusy && styles.btnDisabled]}
        onPress={handleMergeAll}
        disabled={bulkBusy}
      >
        {bulkBusy ? (
          <ActivityIndicator size="small" color="#fff" />
        ) : (
          <Text style={styles.mergeAllBtnText}>
            ⚡ Merge all {groups.length} {groups.length === 1 ? "group" : "groups"}
          </Text>
        )}
      </Pressable>
      <Text style={styles.mergeAllHint}>
        The first contact in each group becomes the primary.
      </Text>

      {groups.map((group, gIdx) => (
        <View key={`${group.ids.join("-")}`} style={styles.card}>
          <View style={styles.cardHeader}>
            <View style={styles.reasonBadge}>
              <Text style={styles.reasonText}>{formatReason(group.reason)}</Text>
            </View>
          </View>

          {group.contacts.map((contact) => {
            const isPrimary = (primaryIds[gIdx] ?? group.contacts[0]?.id) === contact.id;
            return (
              <Pressable
                key={contact.id}
                style={[styles.contactRow, isPrimary && styles.contactRowPrimary]}
                onPress={() =>
                  setPrimaryIds((prev) => ({ ...prev, [gIdx]: contact.id }))
                }
              >
                <View style={styles.contactAvatar}>
                  {contact.photo_url ? (
                    <Image
                      source={{ uri: contact.photo_url }}
                      style={styles.avatarImg}
                    />
                  ) : (
                    <View style={[styles.avatarPlaceholder, isPrimary && styles.avatarPlaceholderPrimary]}>
                      <Text style={styles.avatarInitial}>
                        {(contact.display_name?.[0] ?? "?").toUpperCase()}
                      </Text>
                    </View>
                  )}
                  {isPrimary && (
                    <View style={styles.primaryBadge}>
                      <Text style={styles.primaryBadgeText}>✓</Text>
                    </View>
                  )}
                </View>

                <View style={styles.contactInfo}>
                  <Text style={[styles.contactName, isPrimary && styles.contactNamePrimary]}>
                    {contact.display_name ?? "Contact"}
                    {isPrimary ? " (primary)" : ""}
                  </Text>
                  {contact.organization ? (
                    <Text style={styles.contactMeta}>{contact.organization}</Text>
                  ) : null}
                  {contactPrimaryPhone(contact) ? (
                    <Text style={styles.contactMeta}>
                      📞 {contactPrimaryPhone(contact)}
                    </Text>
                  ) : null}
                  {contactPrimaryEmail(contact) ? (
                    <Text style={styles.contactMeta}>
                      ✉ {contactPrimaryEmail(contact)}
                    </Text>
                  ) : null}
                </View>
              </Pressable>
            );
          })}

          <View style={styles.actions}>
            <Pressable
              style={[styles.dismissBtn, busy[gIdx] && styles.btnDisabled]}
              onPress={() => handleDismiss(gIdx, group)}
              disabled={!!busy[gIdx]}
            >
              {busy[gIdx] ? (
                <ActivityIndicator size="small" color="#9ca3af" />
              ) : (
                <Text style={styles.dismissBtnText}>Not duplicates</Text>
              )}
            </Pressable>

            <Pressable
              style={[styles.mergeBtn, busy[gIdx] && styles.btnDisabled]}
              onPress={() => handleMerge(gIdx, group)}
              disabled={!!busy[gIdx]}
            >
              {busy[gIdx] ? (
                <ActivityIndicator size="small" color="#fff" />
              ) : (
                <Text style={styles.mergeBtnText}>Merge →</Text>
              )}
            </Pressable>
          </View>
        </View>
      ))}

      <View style={{ height: 40 }} />
    </ScrollView>
  );
}

function formatReason(reason: string): string {
  switch (reason) {
    case "phone":      return "Same phone number";
    case "email":      return "Same email address";
    case "name":       return "Same name";
    case "name+org":   return "Same name & organization";
    default:           return reason.replace(/_/g, " ");
  }
}

const PRIMARY_COLOR = "#7c3aed";
const SURFACE = "#1e1b4b";

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#0f0f23",
  },
  content: {
    padding: 16,
  },
  center: {
    flex: 1,
    backgroundColor: "#0f0f23",
    alignItems: "center",
    justifyContent: "center",
    padding: 32,
  },
  loadingText: {
    marginTop: 12,
    color: "#94a3b8",
    fontSize: 14,
  },
  errorText: {
    color: "#f87171",
    textAlign: "center",
    marginBottom: 16,
  },
  retryBtn: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
    backgroundColor: PRIMARY_COLOR,
  },
  retryBtnText: {
    color: "#fff",
    fontWeight: "600",
  },
  emptyIcon: {
    fontSize: 48,
    color: "#22c55e",
    marginBottom: 12,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#f8fafc",
    marginBottom: 6,
  },
  emptySubtitle: {
    fontSize: 13,
    color: "#94a3b8",
    textAlign: "center",
  },
  header: {
    fontSize: 18,
    fontWeight: "700",
    color: "#f8fafc",
    marginBottom: 4,
  },
  subheader: {
    fontSize: 13,
    color: "#94a3b8",
    marginBottom: 20,
  },
  mergeAllBtn: {
    paddingVertical: 12,
    borderRadius: 12,
    backgroundColor: PRIMARY_COLOR,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 6,
  },
  mergeAllBtnText: {
    fontSize: 14,
    fontWeight: "700",
    color: "#fff",
  },
  mergeAllHint: {
    fontSize: 11,
    color: "#64748b",
    textAlign: "center",
    marginBottom: 18,
  },
  card: {
    backgroundColor: SURFACE,
    borderRadius: 16,
    marginBottom: 16,
    overflow: "hidden",
    borderWidth: 1,
    borderColor: "rgba(124,58,237,0.20)",
  },
  cardHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
    flexDirection: "row",
    alignItems: "center",
  },
  reasonBadge: {
    backgroundColor: "rgba(124,58,237,0.15)",
    borderRadius: 20,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  reasonText: {
    fontSize: 11,
    fontWeight: "600",
    color: "#a78bfa",
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  contactRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
  },
  contactRowPrimary: {
    backgroundColor: "rgba(124,58,237,0.10)",
  },
  contactAvatar: {
    position: "relative",
    marginRight: 12,
  },
  avatarImg: {
    width: 44,
    height: 44,
    borderRadius: 22,
  },
  avatarPlaceholder: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: "rgba(255,255,255,0.10)",
    alignItems: "center",
    justifyContent: "center",
  },
  avatarPlaceholderPrimary: {
    backgroundColor: "rgba(124,58,237,0.30)",
  },
  avatarInitial: {
    fontSize: 18,
    fontWeight: "700",
    color: "#f8fafc",
  },
  primaryBadge: {
    position: "absolute",
    bottom: 0,
    right: 0,
    width: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: "#22c55e",
    alignItems: "center",
    justifyContent: "center",
  },
  primaryBadgeText: {
    fontSize: 9,
    color: "#fff",
    fontWeight: "700",
  },
  contactInfo: {
    flex: 1,
    gap: 2,
  },
  contactName: {
    fontSize: 14,
    fontWeight: "600",
    color: "#cbd5e1",
  },
  contactNamePrimary: {
    color: "#f8fafc",
  },
  contactMeta: {
    fontSize: 12,
    color: "#64748b",
  },
  actions: {
    flexDirection: "row",
    gap: 10,
    padding: 14,
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
  },
  dismissBtn: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.14)",
    alignItems: "center",
    justifyContent: "center",
  },
  dismissBtnText: {
    fontSize: 13,
    fontWeight: "600",
    color: "#94a3b8",
  },
  mergeBtn: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: PRIMARY_COLOR,
    alignItems: "center",
    justifyContent: "center",
  },
  mergeBtnText: {
    fontSize: 13,
    fontWeight: "700",
    color: "#fff",
  },
  btnDisabled: {
    opacity: 0.5,
  },
});
