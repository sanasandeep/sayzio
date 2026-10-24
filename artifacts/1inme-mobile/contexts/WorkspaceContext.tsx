import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";

import {
  listWorkspaces,
  switchWorkspace as apiSwitchWorkspace,
  type Workspace,
} from "@/lib/api/workspaces";
import { useForegroundRefresh } from "@/hooks/useForegroundRefresh";

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

  const {
    data: workspaces = [],
    isLoading,
    refetch,
  } = useQuery({
    queryKey: ["workspaces-list"],
    queryFn: listWorkspaces,
    staleTime: 60_000,
    retry: false,
  });

  useEffect(() => {
    const serverActive = workspaces.find((w) => w.is_active);
    if (serverActive) setActiveId(serverActive.id);
    else if (!activeId && workspaces.length)
      setActiveId((workspaces.find((w) => w.is_personal) ?? workspaces[0]).id);
  }, [workspaces]);

  const activeWorkspace =
    workspaces.find((w) => w.id === activeId) ??
    workspaces.find((w) => w.is_active) ??
    workspaces.find((w) => w.is_personal) ??
    workspaces[0] ??
    null;

  const switchWorkspace = useCallback(
    async (ws: Workspace) => {
      // Do not show a new workspace while the API still points at the old one.
      await apiSwitchWorkspace(ws.id);
      setActiveId(ws.id);
      // Server-side scoping depends on the active workspace — refetch every
      // workspace-scoped surface so lists/counters match the new scope.
      // Every workspace-aware module must refresh, including older billing/CRM screens.
      await queryClient.invalidateQueries();
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
      value={{
        workspaces,
        activeWorkspace,
        isLoading,
        switchWorkspace,
        refresh,
      }}
    >
      {children}
    </WorkspaceContext.Provider>
  );
}

export function useWorkspace() {
  return useContext(WorkspaceContext);
}
