import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
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
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getLink, updateLink } from "@/lib/api/links";

type FieldDef = {
  key: string;
  label: string;
  kind?: "text" | "url" | "multiline" | "switch" | "choice";
  options?: string[];
  hint?: string;
};

export function SettingsForm({
  linkId,
  group,
  fields,
  blurb,
}: {
  linkId: number;
  group: string;
  fields: FieldDef[];
  blurb?: string;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const [values, setValues] = useState<Record<string, any>>({});
  useEffect(() => {
    if (!q.data) return;
    const sub = ((q.data.settings as Record<string, any>) ?? {})[group] ?? {};
    setValues(sub);
  }, [q.data, group]);

  const save = useMutation({
    mutationFn: () =>
      updateLink(linkId, {
        settings: { [group]: values } as any,
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["link", linkId] }),
  });

  if (q.isLoading) {
    return (
      <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
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
                onValueChange={(nv) =>
                  setValues((p) => ({ ...p, [f.key]: nv }))
                }
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
                      key={opt}
                      onPress={() =>
                        setValues((p) => ({ ...p, [f.key]: opt }))
                      }
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
                            color: on ? colors.primary : colors.mutedForeground,
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
        return (
          <TextField
            key={f.key}
            label={f.label}
            hint={f.hint}
            value={typeof v === "string" ? v : v != null ? String(v) : ""}
            onChangeText={(t) => setValues((p) => ({ ...p, [f.key]: t }))}
            keyboardType={f.kind === "url" ? "url" : "default"}
            autoCapitalize={f.kind === "url" ? "none" : "sentences"}
            multiline={f.kind === "multiline"}
            numberOfLines={f.kind === "multiline" ? 4 : 1}
            style={
              f.kind === "multiline"
                ? { height: 120, textAlignVertical: "top", paddingTop: 12 }
                : undefined
            }
          />
        );
      })}

      <Button
        label="Save"
        onPress={() => save.mutate()}
        loading={save.isPending}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 14, paddingBottom: 40 },
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
});
