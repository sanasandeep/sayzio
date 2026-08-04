import React, {
  createContext,
  useCallback,
  useContext,
  useRef,
} from "react";
import { useSharedValue, withTiming } from "react-native-reanimated";
import type { SharedValue } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

export const TAB_BAR_H = 64;
export const CIRCLE_SIZE = 56;
export const CIRCLE_OVERFLOW = 18;
export const TAB_BOTTOM_MARGIN = 12;
export const TAB_SIDE_MARGIN = 20;

export const TOP_BAR_H = 56;

type TabBarContextValue = {
  translateY: SharedValue<number>;
  topTranslateY: SharedValue<number>;
  reportScroll: (offsetY: number) => void;
};

const TabBarContext = createContext<TabBarContextValue | null>(null);

export function TabBarProvider({ children }: { children: React.ReactNode }) {
  const translateY = useSharedValue(0);
  // Top bar slides UP out of view by its full height (safe-area inset + bar).
  const topTranslateY = useSharedValue(0);
  const insets = useSafeAreaInsets();
  const topBarTotalHeight = insets.top + TOP_BAR_H;
  const lastOffsetY = useRef(0);
  const hidden = useRef(false);

  const reportScroll = useCallback(
    (offsetY: number) => {
      const delta = offsetY - lastOffsetY.current;
      lastOffsetY.current = offsetY;

      if (offsetY < 40) {
        if (hidden.current) {
          hidden.current = false;
          translateY.value = withTiming(0, { duration: 220 });
          topTranslateY.value = withTiming(0, { duration: 220 });
        }
        return;
      }

      if (delta > 6 && !hidden.current) {
        hidden.current = true;
        translateY.value = withTiming(120, { duration: 220 });
        topTranslateY.value = withTiming(-topBarTotalHeight, { duration: 220 });
      } else if (delta < -6 && hidden.current) {
        hidden.current = false;
        translateY.value = withTiming(0, { duration: 220 });
        topTranslateY.value = withTiming(0, { duration: 220 });
      }
    },
    [translateY, topTranslateY, topBarTotalHeight],
  );

  return (
    <TabBarContext.Provider value={{ translateY, topTranslateY, reportScroll }}>
      {children}
    </TabBarContext.Provider>
  );
}

export function useTabBar() {
  const ctx = useContext(TabBarContext);
  if (!ctx) throw new Error("useTabBar must be used within TabBarProvider");
  return ctx;
}

export function useTabBarBottomInset() {
  return TAB_BAR_H + CIRCLE_OVERFLOW + TAB_BOTTOM_MARGIN + 16;
}

export function useTopBarInset() {
  const insets = useSafeAreaInsets();
  return insets.top + TOP_BAR_H;
}
