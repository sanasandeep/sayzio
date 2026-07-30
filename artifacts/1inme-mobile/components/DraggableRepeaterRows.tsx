// Long-press drag-to-reorder wrapper for editor repeater rows
// (gallery images, list items, pricing rows, profile socials).
//
// Unlike the resume screen's DraggableItemList (which activates the
// gesture anywhere on the row), these rows are full editing cards with
// text inputs, so the pan gesture is attached ONLY to a dedicated drag
// handle that `renderRow` places wherever it fits. The whole card still
// lifts and follows the finger.
//
// Items have no stable ids (parents key by index), so this component
// maintains its own parallel array of generated uids. On commit it
// reorders that uid array in lockstep with the permutation it reports
// to the parent, keeping React keys stable across drags (no remount
// flash). Length changes (add/remove) re-sync uids by position, which
// matches the parents' existing index-key semantics.
//
// Row heights are assumed uniform within one repeater (all rows render
// the same fields); the first row is measured and the parent's `gap`
// is added when converting finger travel into slot indexes.
import * as Haptics from "expo-haptics";
import { Feather } from "@expo/vector-icons";
import React, {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { View, type StyleProp, type ViewStyle } from "react-native";
import { Gesture, GestureDetector } from "react-native-gesture-handler";
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withTiming,
} from "react-native-reanimated";

let uidCounter = 0;
function nextUid() {
  uidCounter += 1;
  return `dr-${uidCounter}`;
}

// Pure slot-shift math shared between the UI-thread gesture worklet and the
// source-driven unit test (scripts/test-repeater-drag-reorder.mjs). Given
// the current uid→slot map, move `uid` (whose ORIGINAL index is `origIdx`)
// to visual slot `target`, sliding the rows in between by one. Returns a
// new map, or null when nothing changes.
export function shiftSlots(
  slots: Record<string, number>,
  uid: string,
  origIdx: number,
  target: number,
): Record<string, number> | null {
  "worklet";
  const myCurrent = slots[uid] ?? origIdx;
  if (target === myCurrent) return null;
  const next: Record<string, number> = { ...slots };
  if (target > myCurrent) {
    for (const k of Object.keys(next)) {
      const v = next[k];
      if (k === uid) continue;
      if (v > myCurrent && v <= target) next[k] = v - 1;
    }
  } else {
    for (const k of Object.keys(next)) {
      const v = next[k];
      if (k === uid) continue;
      if (v >= target && v < myCurrent) next[k] = v + 1;
    }
  }
  next[uid] = target;
  return next;
}

// Pure commit math: turn the final uid→slot map into a permutation of
// ORIGINAL indexes in their new visual order (what onReorder receives).
export function slotsToPermutation(
  uids: string[],
  slots: Record<string, number>,
): number[] {
  return [...uids]
    .map((u, origIdx) => ({ origIdx, slot: slots[u] ?? origIdx }))
    .sort((a, b) => a.slot - b.slot)
    .map((e) => e.origIdx);
}

export function DraggableRepeaterRows<T>({
  items,
  gap = 8,
  handleColor = "#888",
  renderRow,
  onReorder,
}: {
  items: T[];
  /** Vertical gap the parent container puts between rows. */
  gap?: number;
  handleColor?: string;
  /**
   * Render one row. `dragHandle` is a ready-made long-press handle
   * element — place it inside the row's header strip.
   */
  renderRow: (item: T, idx: number, dragHandle: React.ReactNode) => React.ReactNode;
  /**
   * Called with the permutation of ORIGINAL indexes in their new
   * order; parent applies it as `perm.map((i) => prev[i])`.
   */
  onReorder: (perm: number[]) => void;
}) {
  const [uids, setUids] = useState<string[]>(() => items.map(() => nextUid()));
  const [rowH, setRowH] = useState(0);

  // Keep the uid array length in sync with the items array. Adds
  // append fresh uids; removals trim from the end (positional, same
  // as the parents' index keys today).
  useEffect(() => {
    setUids((prev) => {
      if (prev.length === items.length) return prev;
      if (prev.length < items.length) {
        const next = [...prev];
        while (next.length < items.length) next.push(nextUid());
        return next;
      }
      return prev.slice(0, items.length);
    });
  }, [items.length]);

  const effectiveUids = useMemo(() => {
    if (uids.length === items.length) return uids;
    // Render fallback for the frame before the effect syncs lengths.
    const next = uids.slice(0, items.length);
    for (let i = next.length; i < items.length; i++) next.push(`tmp-${i}`);
    return next;
  }, [uids, items.length]);

  // uid -> current visual slot; mutated from the UI thread mid-drag.
  const slots = useSharedValue<Record<string, number>>(
    Object.fromEntries(effectiveUids.map((u, i) => [u, i])),
  );

  const uidsKey = effectiveUids.join(",");
  useEffect(() => {
    slots.value = Object.fromEntries(effectiveUids.map((u, i) => [u, i]));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uidsKey]);

  const uidsRef = useRef(effectiveUids);
  uidsRef.current = effectiveUids;

  const commit = useCallback(() => {
    const current = uidsRef.current;
    const perm = slotsToPermutation(current, slots.value);
    const changed = perm.some((v, i) => v !== i);
    if (!changed) return;
    // Reorder our uid array in lockstep so keys stay stable and the
    // reset-to-identity slots match the new visual order (no flash).
    setUids(perm.map((i) => current[i]));
    onReorder(perm);
  }, [onReorder, slots]);

  if (items.length <= 1) {
    return (
      <>
        {items.map((it, idx) => (
          <View key={effectiveUids[idx]}>
            {renderRow(it, idx, <View style={{ width: 0 }} />)}
          </View>
        ))}
      </>
    );
  }

  return (
    <>
      {items.map((it, idx) => (
        <DraggableRepeaterRow
          key={effectiveUids[idx]}
          uid={effectiveUids[idx]}
          origIdx={idx}
          totalItems={items.length}
          rowH={rowH > 0 ? rowH + gap : 0}
          slots={slots}
          handleColor={handleColor}
          onMeasureRow={idx === 0 ? setRowH : undefined}
          onCommit={commit}
          renderRow={(handle) => renderRow(it, idx, handle)}
        />
      ))}
    </>
  );
}

function DraggableRepeaterRow({
  uid,
  origIdx,
  totalItems,
  rowH,
  slots,
  handleColor,
  onMeasureRow,
  onCommit,
  renderRow,
}: {
  uid: string;
  origIdx: number;
  totalItems: number;
  rowH: number;
  slots: ReturnType<typeof useSharedValue<Record<string, number>>>;
  handleColor: string;
  onMeasureRow?: (h: number) => void;
  onCommit: () => void;
  renderRow: (dragHandle: React.ReactNode) => React.ReactNode;
}) {
  const isDragging = useSharedValue(false);
  const panY = useSharedValue(0);

  const triggerHaptic = useCallback(() => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
  }, []);

  const gesture = useMemo(() => {
    return Gesture.Pan()
      .activateAfterLongPress(300)
      .onStart(() => {
        "worklet";
        isDragging.value = true;
        panY.value = 0;
        runOnJS(triggerHaptic)();
      })
      .onUpdate((e) => {
        "worklet";
        if (rowH <= 0) return;
        panY.value = e.translationY;
        const target = Math.max(
          0,
          Math.min(totalItems - 1, origIdx + Math.round(e.translationY / rowH)),
        );
        const next = shiftSlots(slots.value, uid, origIdx, target);
        if (next) slots.value = next;
      })
      .onEnd(() => {
        "worklet";
        isDragging.value = false;
        panY.value = 0;
        runOnJS(onCommit)();
      })
      .onFinalize(() => {
        "worklet";
        isDragging.value = false;
        panY.value = 0;
      });
  }, [uid, origIdx, totalItems, rowH, slots, isDragging, panY, onCommit, triggerHaptic]);

  const animStyle = useAnimatedStyle(() => {
    const mySlot = slots.value[uid] ?? origIdx;
    if (isDragging.value) {
      return {
        transform: [{ translateY: panY.value }, { scale: 1.02 }],
        zIndex: 100,
        elevation: 10,
        shadowColor: "#000",
        shadowOpacity: 0.18,
        shadowRadius: 8,
        shadowOffset: { width: 0, height: 4 },
        opacity: 0.97,
      };
    }
    const target = (mySlot - origIdx) * rowH;
    return {
      transform: [{ translateY: withTiming(target, { duration: 180 }) }],
      zIndex: 1,
    };
  });

  const handleStyle: StyleProp<ViewStyle> = {
    padding: 6,
    alignItems: "center",
    justifyContent: "center",
  };

  const dragHandle = (
    <GestureDetector gesture={gesture}>
      <View
        style={handleStyle}
        accessibilityLabel={`Drag handle for row ${origIdx + 1}. Long-press and drag to reorder.`}
        testID={`drag-handle-${origIdx}`}
      >
        <Feather name="menu" size={15} color={handleColor} />
      </View>
    </GestureDetector>
  );

  return (
    <Animated.View
      style={animStyle}
      onLayout={
        onMeasureRow
          ? (e) => {
              const h = e.nativeEvent.layout.height;
              if (h > 0) onMeasureRow(h);
            }
          : undefined
      }
    >
      {renderRow(dragHandle)}
    </Animated.View>
  );
}
