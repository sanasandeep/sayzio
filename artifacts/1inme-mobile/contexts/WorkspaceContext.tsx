import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import AsyncStorage from "@react-native-async-storage/async-storage";

import {
  listWorkspaces,
  switchWorkspace as apiSwitchWorkspace,
  type Workspace,
} from "@/lib/api/workspaces";
import { useForegroundRefresh } from "@/hooks/useForegroundRefresh";

const ACTIVE_WS_KEY = "sayzio_active_workspace_id";

type WorkspaceContextValue = {
  workspaces: Workspace[];
  activeWorkspace: Workspace | null;
  isLoading: boolean;
  switchWorkspace: (ws: Workspace) => Promise<void>;
  refresh: () => void;
};

const WorkspaceContext = createContext<WorkspaceContextValue>({
  workspaces: [],
  activeWorkspace: null,
  isLoading: false,
  switchWorkspace: async () => {},
  refresh: () => {},
});

export function WorkspaceProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient();
  const [activeId, setActiveId] = useState<number | null>(null);

  const { data: workspaces = [], isLoading, refetch } = useQuery({
    queryKey: ["workspaces-list"],
    queryFn: listWorkspaces,
    staleTime: 60_000,
    retry: false,
  });

  useEffect(() => {
    AsyncStorage.getItem(ACTIVE_WS_KEY).then((raw) => {
      const parsed = raw ? parseInt(raw, 10) : null;
      if (parsed && !isNaN(parsed)) setActiveId(parsed);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    if (!activeId && workspaces.length > 0) {
      const personal = workspaces.find((w) => w.is_personal) ?? workspaces[0];
      setActiveId(personal.id);
    }
  }, [workspaces, activeId]);

  const activeWorkspace =
    workspaces.find((w) => w.id === activeId) ??
    workspaces.find((w) => w.is_personal) ??
    workspaces[0] ??
    null;

  const switchWorkspace = useCallback(
    async (ws: Workspace) => {
      setActiveId(ws.id);
      await AsyncStorage.setItem(ACTIVE_WS_KEY, String(ws.id));
      try {
        // Persists users.active_workspace_id server-side; the API links list
        // and dashboard are scoped by it, and the web session honours it too,
        // so switching here keeps both surfaces in sync.
        await apiSwitchWorkspace(ws.id);
      } catch (e) {
        if (__DEV__) console.warn("workspace activate failed", e);
      }
      // Server-side scoping depends on the active workspace — refetch every
      // workspace-scoped surface so lists/counters match the new scope.
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["workspaces-list"] }),
        queryClient.invalidateQueries({ queryKey: ["links"] }),
        queryClient.invalidateQueries({ queryKey: ["dashboard"] }),
      ]);
    },
    [queryClient],
  );

  const refresh = useCallback(() => {
    void refetch();
  }, [refetch]);

  // A rename/restyle/delete happens on the web (opened via the switcher's gear
  // button); refresh the list when the app returns to the foreground so those
  // changes show up without a manual pull-to-refresh.
  useForegroundRefresh(refresh);

  return (
    <WorkspaceContext.Provider
      value={{ workspaces, activeWorkspace, isLoading, switchWorkspace, refresh }}
    >
      {children}
    </WorkspaceContext.Provider>
  );
}

export function useWorkspace() {
  return useContext(WorkspaceContext);
}
