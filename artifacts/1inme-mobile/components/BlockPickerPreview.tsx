import { Feather } from "@expo/vector-icons";
import { Image, StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

// Mini visual preview for each block kind shown in the "Add block"
// modal (Task #1202). Mirrors the web partial
// resources/views/user/links/partials/block-picker-preview.blade.php.
//
// We render seeded shapes/text per type rather than wiring up the full
// block renderer — the editor already has its own preview screen, and
// the picker only needs a glanceable thumbnail showing what kind of
// thing the user is about to add.

type Palette = ReturnType<typeof useColors>;

const PLACEHOLDER_IMG =
  "https://images.unsplash.com/photo-1517309230475-6736d926b979?w=400";

function Line({
  width,
  color,
  height = 4,
}: {
  width: string | number;
  color: string;
  height?: number;
}) {
  return (
    <View
      style={{
        width: width as any,
        height,
        borderRadius: 2,
        backgroundColor: color,
      }}
    />
  );
}

export function BlockPickerPreview({ type }: { type: string }) {
  const colors = useColors();
  const muted = colors.mutedForeground + "55";
  const accent = colors.primary;

  // Each branch below targets the small set of kinds in BLOCK_KINDS
  // (lib/api/blocks.ts). Anything outside that set falls through to a
  // generic icon placeholder.
  const inner = (() => {
    switch (type) {
      case "header":
        return (
          <View style={{ gap: 4, alignItems: "flex-start" }}>
            <Text
              style={{
                color: colors.foreground,
                fontSize: 13,
                fontWeight: "700",
              }}
              numberOfLines={1}
            >
              Section title
            </Text>
            <View
              style={{
                width: 28,
                height: 2,
                borderRadius: 1,
                backgroundColor: accent,
              }}
            />
            <Line width={"60%"} color={muted} height={3} />
          </View>
        );

      case "link":
        return (
          <View
            style={{
              backgroundColor: accent,
              borderRadius: 8,
              paddingVertical: 10,
              paddingHorizontal: 12,
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "center",
              gap: 6,
            }}
          >
            <Feather name="link" size={11} color="#fff" />
            <Text
              style={{ color: "#fff", fontSize: 11, fontWeight: "700" }}
              numberOfLines={1}
            >
              My Link
            </Text>
          </View>
        );

      case "text":
        return (
          <View style={{ gap: 5 }}>
            <Line width={"100%"} color={muted} />
            <Line width={"90%"} color={muted} />
            <Line width={"60%"} color={muted} />
          </View>
        );

      case "image":
        return (
          <Image
            source={{ uri: PLACEHOLDER_IMG }}
            style={{
              width: "100%",
              height: "100%",
              borderRadius: 6,
              backgroundColor: muted,
            }}
            resizeMode="cover"
          />
        );

      case "video":
        return (
          <View
            style={{
              flex: 1,
              borderRadius: 8,
              backgroundColor: "#0008",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <View
              style={{
                width: 28,
                height: 28,
                borderRadius: 14,
                backgroundColor: "#fff",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Feather name="play" size={12} color="#000" />
            </View>
          </View>
        );

      case "embed":
        return (
          <View
            style={{
              flex: 1,
              borderRadius: 8,
              borderWidth: 1,
              borderColor: muted,
              borderStyle: "dashed",
              alignItems: "center",
              justifyContent: "center",
              gap: 4,
            }}
          >
            <Feather name="code" size={16} color={accent} />
            <Text
              style={{ color: colors.mutedForeground, fontSize: 9 }}
            >
              Embed
            </Text>
          </View>
        );

      case "divider":
        return (
          <View
            style={{
              flex: 1,
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <View
              style={{
                width: "100%",
                height: 1,
                backgroundColor: colors.mutedForeground,
              }}
            />
          </View>
        );

      case "list":
        return (
          <View style={{ gap: 6 }}>
            {[80, 60, 90].map((w, i) => (
              <View
                key={i}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                }}
              >
                <View
                  style={{
                    width: 5,
                    height: 5,
                    borderRadius: 2.5,
                    backgroundColor: accent,
                  }}
                />
                <Line width={`${w}%` as unknown as string} color={muted} />
              </View>
            ))}
          </View>
        );

      case "list_numbered":
        return (
          <View style={{ gap: 6 }}>
            {[1, 2, 3].map((n, i) => (
              <View
                key={n}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                }}
              >
                <Text
                  style={{
                    color: accent,
                    fontSize: 9,
                    fontWeight: "700",
                    width: 8,
                  }}
                >
                  {n}.
                </Text>
                <Line
                  width={`${[80, 60, 90][i]}%` as unknown as string}
                  color={muted}
                />
              </View>
            ))}
          </View>
        );

      case "list_pricing":
        return (
          <View style={{ flexDirection: "row", gap: 4 }}>
            {[
              { p: "$9", featured: false },
              { p: "$29", featured: true },
              { p: "$99", featured: false },
            ].map((it, i) => (
              <View
                key={i}
                style={{
                  flex: 1,
                  paddingVertical: 6,
                  borderRadius: 4,
                  borderWidth: it.featured ? 1.5 : 1,
                  borderColor: it.featured ? accent : muted,
                  alignItems: "center",
                  gap: 3,
                  transform: [{ scale: it.featured ? 1.05 : 1 }],
                }}
              >
                <Text
                  style={{
                    color: colors.foreground,
                    fontSize: 11,
                    fontWeight: "700",
                  }}
                >
                  {it.p}
                </Text>
                <Line width={"60%"} color={muted} height={3} />
              </View>
            ))}
          </View>
        );

      default:
        return (
          <View
            style={{
              flex: 1,
              alignItems: "center",
              justifyContent: "center",
              backgroundColor: accent + "15",
              borderRadius: 6,
            }}
          >
            <Feather name="grid" size={20} color={accent} />
          </View>
        );
    }
  })();

  return (
    <View
      style={[
        styles.thumb,
        { backgroundColor: colors.background, borderColor: colors.border },
      ]}
      pointerEvents="none"
    >
      {inner}
    </View>
  );
}

const styles = StyleSheet.create({
  thumb: {
    height: 78,
    borderRadius: 8,
    borderWidth: 1,
    padding: 10,
    marginBottom: 8,
    overflow: "hidden",
    justifyContent: "center",
  },
});
