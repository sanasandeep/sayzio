import { apiFetch } from "@/lib/api";

// Mirrors ConnectedApp::toPublicArray() on the server.
export type ConnectedAppConnection = {
  id: number;
  provider: string;
  label: string;
  kind: string;
  icon: string;
  status: string;
  active: boolean;
  paused: boolean;
  account_label: string | null;
  push_enabled: boolean;
  pull_enabled: boolean;
  measurement_id?: string | null;
  field_mappings?: Record<string, string>;
  records_sent?: number;
  records_pulled?: number;
  last_synced_at?: string | null;
  last_pull_at?: string | null;
  last_sync_status?: string | null;
  last_sync_error?: string | null;
};

export type ConnectedAppConfigField = {
  key: string;
  label: string;
  placeholder?: string;
  secret?: boolean;
};

// Mirrors ConnectedAppRegistry entries as surfaced by the index endpoint.
export type ConnectedAppProvider = {
  key: string;
  label: string;
  kind: string;
  icon: string;
  color: string;
  blurb: string;
  connect_type: "oauth" | "config";
  capabilities: Record<string, boolean>;
  config_fields: ConnectedAppConfigField[];
  available: boolean;
  status: { key: string; label: string; tone: string };
  connection: ConnectedAppConnection | null;
};

export type ConnectedAppsIndex = {
  providers: ConnectedAppProvider[];
  connected_apps: boolean;
};

export const connectedApps = {
  list: async (): Promise<ConnectedAppsIndex> => {
    const res = await apiFetch<{ data: ConnectedAppsIndex }>("/connected-apps");
    return res.data;
  },

  connectUrl: async (provider: string): Promise<string> => {
    const res = await apiFetch<{ data: { authorization_url: string } }>(
      `/connected-apps/${provider}/connect-url`,
      { method: "POST" },
    );
    return res.data.authorization_url;
  },

  saveGoogleAnalytics: async (
    measurementId: string,
    apiSecret: string,
  ): Promise<ConnectedAppConnection> => {
    const res = await apiFetch<{ data: { connection: ConnectedAppConnection } }>(
      "/connected-apps/google-analytics",
      {
        method: "POST",
        body: JSON.stringify({
          measurement_id: measurementId,
          api_secret: apiSecret,
        }),
      },
    );
    return res.data.connection;
  },

  update: async (
    id: number,
    patch: {
      push_enabled?: boolean;
      pull_enabled?: boolean;
      paused?: boolean;
      field_mappings?: Record<string, string>;
    },
  ): Promise<ConnectedAppConnection> => {
    const res = await apiFetch<{ data: { connection: ConnectedAppConnection } }>(
      `/connected-apps/${id}`,
      { method: "PATCH", body: JSON.stringify(patch) },
    );
    return res.data.connection;
  },

  syncNow: async (
    id: number,
  ): Promise<{ imported: number; connection: ConnectedAppConnection }> => {
    const res = await apiFetch<{
      data: { imported: number; connection: ConnectedAppConnection };
    }>(`/connected-apps/${id}/sync`, { method: "POST" });
    return res.data;
  },

  disconnect: async (id: number): Promise<void> => {
    await apiFetch(`/connected-apps/${id}`, { method: "DELETE" });
  },
};
