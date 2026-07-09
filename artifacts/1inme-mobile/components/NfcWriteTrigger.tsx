import { Feather } from "@expo/vector-icons";
import { useState } from "react";
import { Pressable, StyleSheet, Text } from "react-native";

import { NfcWriteSheet } from "@/components/NfcWriteSheet";
import { useColors } from "@/hooks/useColors";

/**
 * Reusable "Write NFC" entry point. Manages its own sheet visibility so any
 * screen can mount it without duplicating state or capability checks.
 *
 * variant="icon"   — a compact wifi-icon-only pressable (for link rows)
 * variant="button" — a labelled pill button (for section headers / analytics)
 */
export function NfcWriteTrigger({
  linkId,
  url,
  variant = "button",
  onWritten,
  infoUrl,
}: {
  linkId: number;
  url: string;
  variant?: "icon" | "button";
  onWritten?: () => void;
  infoUrl?: string;
}) {
  const colors = useColors();
  const [open, setOpen] = useState(false);

  if (variant === "icon") {
    return (
      <>
        <Pressable
          onPress={(e) => {
            e.stopPropagation?.();
            setOpen(true);
          }}
          hitSlop={10}
          accessibilityRole="button"
          accessibilityLabel="Write NFC tag"
          style={({ pressed }) => [
            styles.iconBtn,
            {
              backgroundColor: colors.primary + "1c",
              borderColor: colors.primary + "44",
              opacity: pressed ? 0.6 : 1,
            },
          ]}
        >
          <Feather name="wifi" size={14} color={colors.primary} />
        </Pressable>
        <NfcWriteSheet
          visible={open}
          onClose={() => setOpen(false)}
          linkId={linkId}
          url={url}
          onWritten={onWritten}
        />
      </>
    );
  }

  return (
    <>
      <Pressable
        onPress={() => setOpen(true)}
        accessibilityRole="button"
        accessibilityLabel="Write NFC tag"
        style={({ pressed }) => [
          styles.pillBtn,
          {
            backgroundColor: colors.primary + "15",
            borderColor: colors.primary + "40",
            opacity: pressed ? 0.6 : 1,
          },
        ]}
      >
        <Feather name="wifi" size={13} color={colors.primary} />
        <Text style={[styles.pillLabel, { color: colors.primary }]}>
          Write NFC
        </Text>
      </Pressable>
      <NfcWriteSheet
        visible={open}
        onClose={() => setOpen(false)}
        linkId={linkId}
        url={url}
        onWritten={onWritten}
      />
    </>
  );
}

const styles = StyleSheet.create({
  iconBtn: {
    width: 30,
    height: 30,
    borderRadius: 999,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  pillBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingHorizontal: 11,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    alignSelf: "flex-start",
  },
  pillLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
  },
});
