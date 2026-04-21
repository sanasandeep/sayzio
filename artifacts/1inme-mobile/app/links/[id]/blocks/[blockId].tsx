import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  blockKind,
  listBlocks,
  updateBlock,
  type Block,
} from "@/lib/api/blocks";

export default function EditBlockScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id: idParam, blockId: blockIdParam } = useLocalSearchParams<{
    id: string;
    blockId: string;
  }>();
  const id = Number(idParam);
  const blockId = Number(blockIdParam);

  const q = useQuery({
    queryKey: ["blocks", id],
    queryFn: () => listBlocks(id),
    enabled: Number.isFinite(id),
  });
  const block: Block | undefined = useMemo(
    () => q.data?.find((b) => b.id === blockId),
    [q.data, blockId],
  );

  const [active, setActive] = useState(true);
  const [values, setValues] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!block) return;
    setActive(block.is_active);
    const init: Record<string, string> = {};
    Object.entries(block.settings ?? {}).forEach(([k, v]) => {
      if (typeof v === "string") init[k] = v;
      else if (v != null) init[k] = String(v);
    });
    setValues(init);
  }, [block]);

  const save = useMutation({
    mutationFn: () =>
      updateBlock(id, blockId, {
        is_active: active,
        settings: values,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
      router.back();
    },
  });

  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (!block) {
    return (
      <View style={styles.center}>
        <Text style={{ color: colors.destructive }}>Block not found.</Text>
      </View>
    );
  }

  const meta = blockKind(block.type);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ headerShown: true, title: meta?.label || block.type }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        {meta ? (
          <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
            {meta.blurb}
          </Text>
        ) : null}

        {(meta?.fields ?? []).map((f) => (
          <TextField
            key={f.key}
            label={f.label}
            hint={f.hint}
            value={values[f.key] ?? ""}
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
        ))}

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
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>
            Visible on biolink
          </Text>
          <Switch
            value={active}
            onValueChange={setActive}
            trackColor={{ true: colors.primary, false: colors.border }}
          />
        </View>

        <Button
          label="Save block"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
  },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
