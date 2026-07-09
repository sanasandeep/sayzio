import React, {
  createContext,
  useCallback,
  useContext,
  useRef,
} from "react";
import { useSharedValue, withTiming } from "react-native-reanimated";
import type { SharedValue } from "react-native-reanimated";

export const TAB_BAR_H = 64;
export const CIRCLE_SIZE = 52;
export const CIRCLE_OVERFLOW = 10;
export const TAB_BOTTOM_MARGIN = 12;
export const TAB_SIDE_MARGIN = 20;

type TabBarContextValue = {
  translateY: SharedValue<number>;
  reportScroll: (offsetY: number) => void;
};

const TabBarContext = createContext<TabBarContextValue | null>(null);

export function TabBarProvider({ children }: { children: React.ReactNode }) {
  const translateY = useSharedValue(0);
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
        }
        return;
      }

      if (delta > 6 && !hidden.current) {
        hidden.current = true;
        translateY.value = withTiming(120, { duration: 220 });
      } else if (delta < -6 && hidden.current) {
        hidden.current = false;
        translateY.value = withTiming(0, { duration: 220 });
      }
    },
    [translateY],
  );

  return (
    <TabBarContext.Provider value={{ translateY, reportScroll }}>
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
