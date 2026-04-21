import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  createProject,
  deleteProject,
  listProjects,
  type Project,
} from "@/lib/api/projects";

const SWATCHES = ["#7c3aed", "#06b6d4", "#22c55e", "#f59e0b", "#ec4899", "#3b82f6"];

export default function ProjectsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [showNew, setShowNew] = useState(false);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [color, setColor] = useState<string>(SWATCHES[0]);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const q = useQuery({ queryKey: ["projects"], queryFn: listProjects });

  const create = useMutation({
    mutationFn: () => createProject({ name, description: description || null, color }),
    onSuccess: () => {
      setShowNew(false);
      setName("");
      setDescription("");
      setErrors({});
      qc.invalidateQueries({ queryKey: ["projects"] });
    },
    onError: (e: any) => {
      if (e?.errors) {
        const flat: Record<string, string> = {};
        Object.entries(e.errors).forEach(([k, v]) => {
          flat[k] = Array.isArray(v) ? (v[0] as string) : String(v);
        });
        setErrors(flat);
      } else {
        Alert.alert("Could not create", e?.message ?? "Unknown error");
      }
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteProject(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["projects"] }),
  });

  const confirmDelete = (p: Project) => {
    const go = () => remove.mutate(p.id);
    if (Platform.OS === "web") {
      if (confirm(`Delete project “${p.name}”?`)) go();
    } else {
      Alert.alert("Delete project?", `“${p.name}” will be removed.`, [
        { text: "Cancel", style: "cancel" },
        { text: "Delete", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Projects",
          headerRight: () => (
            <Pressable onPress={() => setShowNew(true)} hitSlop={8} style={{ paddingRight: 12 }}>
              <Feather name="plus" size={20} color={colors.primary} />
            </Pressable>
          ),
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<Project>
          data={q.data ?? []}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.dot, { backgroundColor: item.color || colors.primary }]} />
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                {item.description ? (
                  <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={2}>
                    {item.description}
                  </Text>
                ) : null}
              </View>
              <Pressable onPress={() => confirmDelete(item)} hitSlop={6}>
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="folder"
              title="No projects yet"
              body="Group your links, QR codes and splash pages into projects to keep things organised."
              action={<Button label="New project" onPress={() => setShowNew(true)} />}
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}

      <Modal visible={showNew} animationType="slide" transparent onRequestClose={() => setShowNew(false)}>
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>New project</Text>
            <TextField label="Name" value={name} onChangeText={setName} error={errors.name} />
            <TextField
              label="Description"
              value={description}
              onChangeText={setDescription}
              multiline
              numberOfLines={3}
              style={{ minHeight: 80, paddingVertical: 12, textAlignVertical: "top" }}
            />
            <View style={{ gap: 8 }}>
              <Text style={[styles.lbl, { color: colors.mutedForeground }]}>Colour</Text>
              <View style={styles.swatches}>
                {SWATCHES.map((c) => (
                  <Pressable
                    key={c}
                    onPress={() => setColor(c)}
                    style={[
                      styles.swatch,
                      {
                        backgroundColor: c,
                        borderColor: c === color ? colors.foreground : "transparent",
                      },
                    ]}
                  />
                ))}
              </View>
            </View>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button label="Cancel" variant="outline" onPress={() => setShowNew(false)} style={{ flex: 1 }} />
              <Button
                label="Create"
                onPress={() => create.mutate()}
                loading={create.isPending}
                disabled={!name.trim()}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  dot: { width: 12, height: 12, borderRadius: 999 },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "flex-end",
  },
  modalCard: { padding: 20, gap: 14, borderTopWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  lbl: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  swatches: { flexDirection: "row", gap: 10, flexWrap: "wrap" },
  swatch: { width: 32, height: 32, borderRadius: 999, borderWidth: 2 },
});
