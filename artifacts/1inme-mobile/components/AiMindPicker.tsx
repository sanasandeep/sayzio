import { Feather } from "@expo/vector-icons";
import { Stack } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AiDisabledNotice } from "@/components/AiDisabledNotice";
import { useColors } from "@/hooks/useColors";
import {
  type AiMindFeature,
  type AiMindList,
  aiMinds as aiMindsApi,
} from "@/lib/api";

type Props = {
  feature: AiMindFeature;
  title: string;
  subtitle: string;
  /** Feature label used for the "AI is off" explainer (e.g. "AI Growth Coach"). */
  disabledFeature?: string;
};

/**
 * Manages the user's default Mind selection for an AI feature
 * (Persona / Coach). On open, pre-checks the saved defaults so the
 * user doesn't have to re-pick. Mirrors the web Persona/Coach forms.
 */
export function AiMindPickerScreen({
  feature,
  title,
  subtitle,
  disabledFeature,
}: Props) {
  const colors = useColors();
  const insets = useSafeAreaInsets();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [clearing, setClearing] = useState(false);
  // Set when the AI engine is off (404) or this feature isn't on the
  // user's plan (403), so we can show the same explainer as the web app.
  const [disabled, setDisabled] = useState<"engine" | "plan" | null>(null);

  const [minds, setMinds] = useState<AiMindList>({ mine: [], platform: null });
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [includePlatform, setIncludePlatform] = useState(false);
  const [hasDefault, setHasDefault] = useState(false);

  // Snapshot of the last saved default so we can show "saved" state.
  const [savedSelection, setSavedSelection] = useState<{
    mind_ids: number[];
    include_platform: boolean;
  } | null>(null);

  const load = useCallback(async () => {
    try {
      const [list, defaults] = await Promise.all([
        aiMindsApi.list(),
        aiMindsApi.getDefaults(feature),
      ]);
      setMinds(list);
      setSelected(new Set(defaults.mind_ids));
      setIncludePlatform(defaults.include_platform);
      setHasDefault(defaults.has_default);
      setSavedSelection(
        defaults.has_default
          ? {
              mind_ids: [...defaults.mind_ids].sort((a, b) => a - b),
              include_platform: defaults.include_platform,
            }
          : null,
      );
    } catch (e: unknown) {
      const err = e as { status?: number; message?: string } | undefined;
      if (err?.status === 404) {
        setDisabled("engine");
      } else if (err?.status === 403) {
        setDisabled("plan");
      } else {
        Alert.alert("Couldn't load AI Knowledge Bases", err?.message ?? "Try again in a moment.");
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [feature]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggleMind = (id: number) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const currentSelection = useMemo(
    () => ({
      mind_ids: [...selected].sort((a, b) => a - b),
      include_platform: includePlatform,
    }),
    [selected, includePlatform],
  );

  const matchesSavedDefault = useMemo(() => {
    if (!savedSelection) return false;
    if (savedSelection.include_platform !== currentSelection.include_platform) {
      return false;
    }
    if (savedSelection.mind_ids.length !== currentSelection.mind_ids.length) {
      return false;
    }
    return savedSelection.mind_ids.every(
      (id, i) => id === currentSelection.mind_ids[i],
    );
  }, [savedSelection, currentSelection]);

  const saveDefault = async () => {
    setSaving(true);
    try {
      const r = await aiMindsApi.saveDefaults(feature, currentSelection);
      setHasDefault(true);
      setSavedSelection({
        mind_ids: [...r.mind_ids].sort((a, b) => a - b),
        include_platform: r.include_platform,
      });
      // Reflect the server's constrained selection (it strips stale ids).
      setSelected(new Set(r.mind_ids));
      setIncludePlatform(r.include_platform);
      Alert.alert(
        "Default saved",
        "This selection will be pre-filled next time you open this form.",
      );
    } catch (e: unknown) {
      const err = e as { message?: string } | undefined;
      Alert.alert("Couldn't save default", err?.message ?? "Try again.");
    } finally {
      setSaving(false);
    }
  };

  const clearDefault = async () => {
    setClearing(true);
    try {
      await aiMindsApi.clearDefaults(feature);
      setHasDefault(false);
      setSavedSelection(null);
      Alert.alert("Default cleared", "We won't pre-fill the picker for you anymore.");
    } catch (e: unknown) {
      const err = e as { message?: string } | undefined;
      Alert.alert("Couldn't clear default", err?.message ?? "Try again.");
    } finally {
      setClearing(false);
    }
  };

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (disabled) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title, headerShown: true }} />
        <AiDisabledNotice
          feature={disabledFeature ?? title}
          variant={disabled}
        />
      </View>
    );
  }

  const empty = minds.mine.length === 0 && !minds.platform;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title, headerShown: true }} />
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top,
          paddingHorizontal: 20,
          paddingBottom: 40,
          gap: 18,
        }}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              void load();
            }}
          />
        }
      >
        <View>
          <Text style={[styles.heading, { color: colors.foreground }]}>{title}</Text>
          <Text style={[styles.subtle, { color: colors.mutedForeground, marginTop: 4 }]}>
            {subtitle}
          </Text>
          {hasDefault && (
            <Text style={[styles.subtle, { color: colors.primary, marginTop: 8 }]}>
              ✓ Pre-filled from your saved default
            </Text>
          )}
        </View>

        {empty ? (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              You don't have any AI Knowledge Bases yet. Create one on the web to ground generations
              in your own knowledge base.
            </Text>
          </View>
        ) : (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.section, { color: colors.foreground }]}>Your AI Knowledge Bases</Text>
            {minds.mine.length === 0 ? (
              <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                You haven't created any AI Knowledge Bases yet.
              </Text>
            ) : (
              <View style={{ gap: 4 }}>
                {minds.mine.map((m) => {
                  const checked = selected.has(m.id);
                  return (
                    <Pressable
                      key={m.id}
                      onPress={() => toggleMind(m.id)}
                      style={({ pressed }) => [
                        styles.row,
                        { opacity: pressed ? 0.7 : 1 },
                      ]}
                    >
                      <View
                        style={[
                          styles.checkbox,
                          {
                            borderColor: checked ? colors.primary : colors.border,
                            backgroundColor: checked ? colors.primary : "transparent",
                          },
                        ]}
                      >
                        {checked && <Feather name="check" size={14} color="#fff" />}
                      </View>
                      <Text style={[styles.rowText, { color: colors.foreground }]}>
                        {m.name}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            )}

            {minds.platform && (
              <>
                <View
                  style={[styles.divider, { backgroundColor: colors.border }]}
                />
                <Text style={[styles.section, { color: colors.foreground }]}>
                  Platform AI Knowledge Base
                </Text>
                <Pressable
                  onPress={() => setIncludePlatform((v) => !v)}
                  style={({ pressed }) => [
                    styles.row,
                    { opacity: pressed ? 0.7 : 1 },
                  ]}
                >
                  <View
                    style={[
                      styles.checkbox,
                      {
                        borderColor: includePlatform ? colors.primary : colors.border,
                        backgroundColor: includePlatform ? colors.primary : "transparent",
                      },
                    ]}
                  >
                    {includePlatform && <Feather name="check" size={14} color="#fff" />}
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.rowText, { color: colors.foreground }]}>
                      {minds.platform.name}
                    </Text>
                    <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                      Opt in to the Sayzio default knowledge base.
                    </Text>
                  </View>
                </Pressable>
              </>
            )}
          </View>
        )}

        <View style={{ gap: 10 }}>
          <Pressable
            onPress={saveDefault}
            disabled={saving || empty || (hasDefault && matchesSavedDefault)}
            style={({ pressed }) => [
              styles.btn,
              {
                backgroundColor: colors.primary,
                borderRadius: colors.radius - 4,
                opacity:
                  saving || empty || (hasDefault && matchesSavedDefault)
                    ? 0.5
                    : pressed
                      ? 0.8
                      : 1,
              },
            ]}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.btnText}>
                {hasDefault && matchesSavedDefault
                  ? "Saved as default"
                  : "Use as default"}
              </Text>
            )}
          </Pressable>

          {hasDefault && (
            <Pressable
              onPress={clearDefault}
              disabled={clearing}
              style={({ pressed }) => [
                styles.btnGhost,
                {
                  borderColor: colors.border,
                  borderRadius: colors.radius - 4,
                  opacity: clearing ? 0.5 : pressed ? 0.7 : 1,
                },
              ]}
            >
              {clearing ? (
                <ActivityIndicator color={colors.foreground} />
              ) : (
                <Text style={[styles.btnGhostText, { color: colors.foreground }]}>
                  Clear default
                </Text>
              )}
            </Pressable>
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  heading: { fontSize: 22, fontWeight: "700" },
  subtle: { fontSize: 12 },
  card: { padding: 16, borderWidth: 1, gap: 12 },
  section: { fontSize: 14, fontWeight: "600" },
  row: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 10,
    gap: 12,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderWidth: 1.5,
    borderRadius: 4,
    alignItems: "center",
    justifyContent: "center",
  },
  rowText: { fontSize: 14, fontWeight: "500" },
  divider: { height: StyleSheet.hairlineWidth, marginVertical: 4 },
  btn: { paddingVertical: 14, alignItems: "center", justifyContent: "center" },
  btnText: { color: "#fff", fontWeight: "600", fontSize: 14 },
  btnGhost: {
    paddingVertical: 12,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
  },
  btnGhostText: { fontWeight: "600", fontSize: 14 },
});
