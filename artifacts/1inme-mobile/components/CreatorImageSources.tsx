import { useState } from "react";
import { Image, Pressable, Text, View } from "react-native";
import * as ImagePicker from "expo-image-picker";
import {
  useMutation,
  useInfiniteQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { listVaultFiles } from "@/lib/api/files";
import {
  updateCreatorProfile,
  uploadCreatorImage,
} from "@/lib/api/creatorProfile";
import { showAlert } from "@/lib/webAlert";

export function CreatorImageSources({
  profileId,
  field,
  current,
}: {
  profileId: number;
  field: "creator_avatar" | "cover_image";
  current: string | null;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const [source, setSource] = useState<"url" | "upload" | "files">("url");
  const [url, setUrl] = useState("");
  const files = useInfiniteQuery({
    queryKey: ["creator-image-files"],
    queryFn: ({ pageParam }) =>
      listVaultFiles({ type: "image", perPage: 50, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (page) =>
      page.pagination.current_page < page.pagination.last_page
        ? page.pagination.current_page + 1
        : undefined,
    enabled: source === "files",
  });
  const save = useMutation({
    mutationFn: (value: string) =>
      updateCreatorProfile({
        creator_profile_id: profileId,
        ...(field === "creator_avatar"
          ? { creator_avatar_url: value }
          : { cover_image_url: value }),
      }),
    onSuccess: refresh,
    onError: error,
  });
  const remove = useMutation({
    mutationFn: () =>
      updateCreatorProfile({
        creator_profile_id: profileId,
        ...(field === "creator_avatar"
          ? { creator_avatar_remove: "1" }
          : { cover_image_remove: "1" }),
      }),
    onSuccess: refresh,
    onError: error,
  });
  const availableFiles = files.data?.pages.flatMap((page) => page.files) ?? [];
  const upload = useMutation({
    mutationFn: async () => {
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ["images"],
        quality: 0.9,
      });
      if (result.canceled) return;
      const image = result.assets[0];
      const limit = field === "creator_avatar" ? 2 : 5;
      if (image.fileSize && image.fileSize > limit * 1024 * 1024)
        throw new Error(`Choose an image under ${limit} MB.`);
      await uploadCreatorImage(profileId, field, image);
    },
    onSuccess: refresh,
    onError: error,
  });
  function refresh() {
    void qc.invalidateQueries({ queryKey: ["creator-profile-settings"] });
    void qc.invalidateQueries({ queryKey: ["creator-profile"] });
    void qc.invalidateQueries({ queryKey: ["cp-preview-url"] });
  }
  function error(e: Error) {
    showAlert("Could not save image", e.message);
  }
  const pending = save.isPending || upload.isPending || remove.isPending;
  return (
    <View style={{ gap: 10 }}>
      <Text style={{ color: colors.foreground, fontWeight: "700" }}>
        {field === "creator_avatar" ? "Profile photo" : "Cover photo"}
      </Text>
      {current ? (
        <Image
          source={{ uri: current }}
          accessibilityLabel="Current profile image"
          style={{
            width: field === "creator_avatar" ? 80 : "100%",
            height: 80,
            borderRadius: colors.radius,
          }}
        />
      ) : null}
      <View style={{ flexDirection: "row", gap: 10 }}>
        {(["url", "upload", "files"] as const).map((v) => (
          <Pressable
            key={v}
            accessibilityRole="tab"
            accessibilityState={{ selected: source === v }}
            aria-selected={source === v}
            onPress={() => setSource(v)}
            style={{
              padding: 12,
              borderRadius: colors.radius,
              backgroundColor: colors.card,
              borderWidth: 1,
              borderColor: source === v ? colors.primary : colors.border,
            }}
          >
            <Text style={{ color: colors.foreground }}>
              {v === "url" ? "URL" : v === "upload" ? "Upload" : "My files"}
            </Text>
          </Pressable>
        ))}
      </View>
      {source === "url" ? (
        <>
          <TextField
            label="Image URL"
            value={url}
            onChangeText={setUrl}
            autoCapitalize="none"
            placeholder="https://…"
          />
          <Button
            label="Apply image URL"
            loading={pending}
            disabled={!/^https?:\/\//i.test(url)}
            onPress={() => save.mutate(url.trim())}
          />
        </>
      ) : source === "upload" ? (
        <Button
          label="Choose image to upload"
          loading={pending}
          onPress={() => upload.mutate()}
        />
      ) : (
        <View style={{ gap: 8 }}>
          {files.isLoading ? (
            <Text style={{ color: colors.mutedForeground }}>
              Loading your images…
            </Text>
          ) : files.isError ? (
            <Button label="Retry files" onPress={() => files.refetch()} />
          ) : !availableFiles.length ? (
            <Text style={{ color: colors.mutedForeground }}>
              No images in your files yet.
            </Text>
          ) : (
            availableFiles.map((file) => (
              <Button
                key={file.id}
                variant="secondary"
                label={file.original_name || `Image ${file.id}`}
                disabled={pending}
                onPress={() => save.mutate(file.url)}
              />
            ))
          )}
          {files.hasNextPage ? (
            <Button
              label="Load more images"
              loading={files.isFetchingNextPage}
              onPress={() => files.fetchNextPage()}
            />
          ) : null}
        </View>
      )}
      {current ? (
        <Button
          label={
            field === "creator_avatar"
              ? "Use account photo"
              : "Restore gradient cover"
          }
          variant="secondary"
          disabled={pending}
          onPress={() => remove.mutate()}
        />
      ) : null}
    </View>
  );
}
