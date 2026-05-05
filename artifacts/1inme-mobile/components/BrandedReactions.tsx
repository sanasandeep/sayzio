import { Pressable, StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  BRANDED_REACTIONS,
  type ReactionKey,
} from "@/lib/api/creatorProfile";

type Props = {
  totals: Record<string, number>;
  myReaction: ReactionKey | null;
  onReact: (key: ReactionKey) => void;
  disabled?: boolean;
  catalog?: { key: string; label: string; emoji: string }[];
};

/**
 * Six-button branded reaction strip used on the mobile Creator Profile.
 * Mirrors `resources/views/common/partials/branded-reactions.blade.php`
 * so the picker order, labels, and emoji match the web exactly.
 */
export function BrandedReactions({
  totals,
  myReaction,
  onReact,
  disabled,
  catalog,
}: Props) {
  const colors = useColors();
  const items = (catalog ?? BRANDED_REACTIONS) as {
    key: string;
    label: string;
    emoji: string;
  }[];

  return (
    <View style={styles.row}>
      {items.map((r) => {
        const isMine = myReaction === r.key;
        const count = totals[r.key] ?? 0;
        return (
          <Pressable
            key={r.key}
            onPress={() => !disabled && onReact(r.key as ReactionKey)}
            disabled={disabled}
            style={[
              styles.btn,
              {
                backgroundColor: isMine ? colors.primary : colors.card,
                borderColor: isMine ? colors.primary : colors.border,
                opacity: disabled ? 0.6 : 1,
              },
            ]}
          >
            <Text style={styles.emoji}>{r.emoji}</Text>
            {count > 0 ? (
              <Text
                style={[
                  styles.count,
                  { color: isMine ? "#fff" : colors.foreground },
                ]}
              >
                {count}
              </Text>
            ) : null}
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
    marginTop: 10,
  },
  btn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    minHeight: 32,
  },
  emoji: { fontSize: 16 },
  count: { fontSize: 12, fontWeight: "700" },
});
