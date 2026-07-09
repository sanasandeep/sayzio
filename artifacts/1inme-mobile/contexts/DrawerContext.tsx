import React, { createContext, useCallback, useContext, useState } from "react";
import { useSharedValue, withSpring } from "react-native-reanimated";
import type { SharedValue } from "react-native-reanimated";
import { useWindowDimensions } from "react-native";

const DRAWER_WIDTH_FRAC = 0.78;
const MAX_DRAWER_W = 320;

type DrawerContextValue = {
  isOpen: boolean;
  openDrawer: () => void;
  closeDrawer: () => void;
  contentProgress: SharedValue<number>;
  drawerW: number;
};

const SPRING = { damping: 22, stiffness: 200, mass: 0.9 };

const DrawerContext = createContext<DrawerContextValue>({
  isOpen: false,
  openDrawer: () => {},
  closeDrawer: () => {},
  contentProgress: { value: 0 } as SharedValue<number>,
  drawerW: 300,
});

export function DrawerProvider({ children }: { children: React.ReactNode }) {
  const { width } = useWindowDimensions();
  const drawerW = Math.min(width * DRAWER_WIDTH_FRAC, MAX_DRAWER_W);
  const contentProgress = useSharedValue(0);
  const [isOpen, setIsOpen] = useState(false);

  const openDrawer = useCallback(() => {
    setIsOpen(true);
    contentProgress.value = withSpring(1, SPRING);
  }, [contentProgress]);

  const closeDrawer = useCallback(() => {
    contentProgress.value = withSpring(0, SPRING);
    setTimeout(() => setIsOpen(false), 300);
  }, [contentProgress]);

  return (
    <DrawerContext.Provider value={{ isOpen, openDrawer, closeDrawer, contentProgress, drawerW }}>
      {children}
    </DrawerContext.Provider>
  );
}

export function useDrawer() {
  return useContext(DrawerContext);
}
