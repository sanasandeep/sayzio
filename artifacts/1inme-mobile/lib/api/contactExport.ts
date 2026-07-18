import { apiFetch } from "@/lib/api";

export type ExportFormat = "csv" | "vcf";
export type ExportStatus = "pending" | "processing" | "completed" | "failed";

export type ContactExportRecord = {
  id: number;
  status: ExportStatus;
  contact_count: number;
  format: ExportFormat;
  in_progress?: boolean;
  download_url: string | null;
};

/**
 * Request a bulk address-book export.
 * Small address books (<= 500) are generated synchronously and a signed
 * download URL is returned immediately (status = 'completed').
 * Large ones are queued — poll exportStatus() until status = 'completed'.
 */
export async function requestContactExport(
  format: ExportFormat,
  opts?: { scope?: "all" | "filtered"; tab?: string; q?: string },
): Promise<ContactExportRecord> {
  const res = await apiFetch<{ data: ContactExportRecord }>(
    "/contacts/export",
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        format,
        scope: opts?.scope ?? "all",
        tab: opts?.tab,
        q: opts?.q,
      }),
    },
  );
  return res.data;
}

/**
 * Poll the status of a previously-queued export.
 * `download_url` is populated once `status === 'completed'`.
 */
export async function getExportStatus(id: number): Promise<ContactExportRecord> {
  const res = await apiFetch<{ data: ContactExportRecord }>(
    `/contacts/export/${id}/status`,
  );
  return res.data;
}
