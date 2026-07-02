import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react";

import {
  getThemePref,
  setThemePref as persistThemePref,
  type ThemePref,
} from "@/lib/secure";

type Ctx = {
  pref: ThemePref;
  setPref: (v: ThemePref) => Promise<void>;
};

const ThemeContext = createContext<Ctx>({
  pref: "system",
  setPref: async () => {},
});

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [pref, setPrefState] = useState<ThemePref>("system");

  useEffect(() => {
    let alive = true;
    getThemePref().then((v) => {
      if (alive) setPrefState(v);
    });
    return () => {
      alive = false;
    };
  }, []);

  const setPref = useCallback(async (v: ThemePref) => {
    setPrefState(v);
    await persistThemePref(v);
  }, []);

  return (
    <ThemeContext.Provider value={{ pref, setPref }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useThemePreference(): ThemePref {
  return useContext(ThemeContext).pref;
}

export function useThemeControls() {
  return useContext(ThemeContext);
}
