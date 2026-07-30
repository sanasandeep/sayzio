import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Feather } from "@expo/vector-icons";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { ColorSwatchRow } from "@/components/ColorSwatchRow";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { getLink, updateLink } from "@/lib/api/links";
import {
  rememberRecentColorFromTyping,
  rememberRecentColors,
} from "@/lib/recentColors";

type FieldDef = {
  key: string;
  label: string;
  kind?: "text" | "url" | "multiline" | "switch" | "choice" | "color";
  options?: string[];
  hint?: string;
};

export function SettingsForm({
  linkId,
  group,
  fields,
  blurb,
  extra,
}: {
  linkId: number;
  group: string;
  fields: FieldDef[];
  blurb?: string;
  // Extra content rendered inside the scroll area after the generic
  // fields — e.g. the Appearance screen's background preset gallery,
  // which manages its own save flow outside this form's Apply button.
  extra?: React.ReactNode;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const [values, setValues] = useState<Record<string, any>>({});
  const [baseline, setBaseline] = useState<Record<string, any>>({});
  const [applied, setApplied] = useState(false);
  const appliedTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!q.data) return;
    const sub = ((q.data.settings as Record<string, any>) ?? {})[group] ?? {};
    setValues(sub);
    setBaseline(sub);
  }, [q.data, group]);

  useEffect(
    () => () => {
      if (appliedTimer.current) clearTimeout(appliedTimer.current);
    },
    [],
  );

  const dirty = JSON.stringify(values) !== JSON.stringify(baseline);

  const save = useMutation({
    mutationFn: () =>
      updateLink(linkId, {
        settings: { [group]: values } as any,
      }),
    onSuccess: () => {
      // Remember applied custom colors so they surface as extra swatches
      // in every ColorSwatchRow (block editor + appearance/block-theme).
      rememberRecentColors(
        fields
          .filter((f) => f.kind === "color" || f.hint === "#hex")
          .map((f) => (typeof values[f.key] === "string" ? values[f.key] : "")),
      );
      setBaseline(values);
      setApplied(true);
      if (appliedTimer.current) clearTimeout(appliedTimer.current);
      appliedTimer.current = setTimeout(() => setApplied(false), 3000);
      qc.invalidateQueries({ queryKey: ["link", linkId] });
    },
  });

  const setValue = (key: string, v: any) => {
    setApplied(false);
    setValues((p) => ({ ...p, [key]: v }));
  };

  if (q.isLoading) {
    return (
      <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1 }}>
      <ScrollView contentContainerStyle={styles.body}>
        {blurb ? (
          <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
            {blurb}
          </Text>
        ) : null}

        {fields.map((f) => {
          const v = values[f.key];
          if (f.kind === "switch") {
            return (
              <View
                key={f.key}
                style={[
                  styles.row,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                    {f.label}
                  </Text>
                  {f.hint ? (
                    <Text
                      style={[styles.rowHint, { color: colors.mutedForeground }]}
                    >
                      {f.hint}
                    </Text>
                  ) : null}
                </View>
                <Switch
                  value={!!v}
                  onValueChange={(nv) => setValue(f.key, nv)}
                  trackColor={{ true: colors.primary, false: colors.border }}
                />
              </View>
            );
          }
          if (f.kind === "choice" && f.options) {
            return (
              <View key={f.key} style={{ gap: 8 }}>
                <Text
                  style={[styles.choiceLabel, { color: colors.mutedForeground }]}
                >
                  {f.label}
                </Text>
                <View
                  style={[
                    styles.segment,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  {f.options.map((opt) => {
                    const on = v === opt;
                    return (
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        key={opt}
                        onPress={() => setValue(f.key, opt)}
                        style={[
                          styles.segmentItem,
                          {
                            backgroundColor: on
                              ? colors.background
                              : "transparent",
                            borderRadius: colors.radius - 4,
                          },
                        ]}
                      >
                        <Text
                          style={[
                            styles.segmentText,
                            {
                              color: on
                                ? colors.primary
                                : colors.mutedForeground,
                            },
                          ]}
                        >
                          {opt}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              </View>
            );
          }
          const isColorField = f.kind === "color" || f.hint === "#hex";
          return (
            <View key={f.key} style={{ gap: 8 }}>
              {isColorField ? (
                <ColorSwatchRow
                  prefix={`settings-${f.key}-swatch`}
                  value={typeof v === "string" ? v : ""}
                  onPick={(c) => setValue(f.key, c)}
                />
              ) : null}
              <TextField
                label={f.label}
                hint={f.hint}
                value={typeof v === "string" ? v : v != null ? String(v) : ""}
                onChangeText={(t) => {
                  setValue(f.key, t);
                  if (isColorField) {
                    // Valid custom colors typed here join the recent set
                    // (debounced per field, invalid values ignored).
                    rememberRecentColorFromTyping(`settings-${group}-${f.key}`, t);
                  }
                }}
                keyboardType={f.kind === "url" ? "url" : "default"}
                autoCapitalize={f.kind === "url" || isColorField ? "none" : "sentences"}
                multiline={f.kind === "multiline"}
                numberOfLines={f.kind === "multiline" ? 4 : 1}
                style={
                  f.kind === "multiline"
                    ? { height: 120, textAlignVertical: "top", paddingTop: 12 }
                    : undefined
                }
              />
            </View>
          );
        })}
        {extra}
      </ScrollView>

      <View
        style={[
          styles.footer,
          { backgroundColor: colors.background, borderTopColor: colors.border },
        ]}
      >
        {save.isError ? (
          <Text style={[styles.feedback, { color: colors.destructive }]}>
            Couldn't apply changes.{" "}
            {(save.error as Error | null)?.message ?? "Please try again."}
          </Text>
        ) : applied ? (
          <View style={styles.appliedRow} testID="settings-applied">
            <Feather name="check-circle" size={15} color="#22c55e" />
            <Text style={[styles.feedback, { color: "#22c55e" }]}>
              Changes applied to your live page
            </Text>
          </View>
        ) : dirty ? (
          <Text style={[styles.feedback, { color: colors.mutedForeground }]}>
            You have unapplied changes
          </Text>
        ) : null}
        <Button
          label={applied && !dirty ? "Applied" : "Apply changes"}
          variant="cta"
          onPress={() => save.mutate()}
          loading={save.isPending}
          disabled={!dirty}
          testID="settings-apply"
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 14, paddingBottom: 24 },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
    gap: 12,
  },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
  choiceLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 10,
  },
  segmentText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    textTransform: "capitalize",
  },
  footer: {
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 24,
    borderTopWidth: StyleSheet.hairlineWidth,
    gap: 8,
  },
  appliedRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    justifyContent: "center",
  },
  feedback: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    textAlign: "center",
  },
});
