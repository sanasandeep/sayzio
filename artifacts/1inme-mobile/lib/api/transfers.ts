import { apiFetch } from "@/lib/api";

/**
 * Admin-granted asset transfers — Bearer-token client for the Laravel
 * /api/v1 transfer endpoints. Mirrors the web links-index / workspace
 * settings "Transfer to another user" actions: a capability probe (drives
 * action visibility) plus instant link / workspace transfer by recipient
 * email. All authorization is server-side.
 */

export type TransferCapability = {
  can_transfer: boolean;
  granted_at: string | null;
};

export type TransferResult = {
  id: number;
  kind: "link" | "workspace";
  asset_id: number;
  asset_label: string | null;
  to_email: string | null;
  created_at: string | null;
};

export async function getTransferCapability(): Promise<TransferCapability> {
  const res = await apiFetch("/me/transfer-capability");
  return (res as { data: TransferCapability }).data;
}

export async function transferLink(linkId: number, recipientEmail: string): Promise<TransferResult> {
  const res = await apiFetch(`/links/${linkId}/transfer`, {
    method: "POST",
    body: JSON.stringify({ recipient_email: recipientEmail }),
  });
  return (res as { data: TransferResult }).data;
}

export async function transferWorkspace(workspaceId: number, recipientEmail: string): Promise<TransferResult> {
  const res = await apiFetch(`/workspaces/${workspaceId}/transfer`, {
    method: "POST",
    body: JSON.stringify({ recipient_email: recipientEmail }),
  });
  return (res as { data: TransferResult }).data;
}
