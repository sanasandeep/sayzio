import { apiFetch, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type Form = {
  id: number;
  title: string;
  slug: string;
  fields: { id: string; type: string; label?: string; required?: boolean }[];
  is_active: boolean;
  submissions_count: number;
  public_url: string | null;
  created_at: string | null;
};

export type FormSubmission = {
  id: number;
  data: Record<string, unknown> | null;
  ip: string | null;
  created_at: string | null;
};

export async function listForms(): Promise<{ items: Form[] }> {
  const res = await apiFetch<{ data: { items: Form[] } }>(`/forms`);
  return { items: res.data.items };
}

export async function getForm(id: number): Promise<Form> {
  const res = await apiFetch<{ data: { form: Form } }>(`/forms/${id}`);
  return res.data.form;
}

export async function listSubmissions(
  id: number,
): Promise<{ items: FormSubmission[]; total: number }> {
  const res = await apiFetch<{
    data: { items: FormSubmission[]; meta: { total: number } };
  }>(`/forms/${id}/submissions`);
  return { items: res.data.items, total: res.data.meta.total };
}

export async function fetchSubmissionsCsv(id: number): Promise<string> {
  const token = await getToken();
  const res = await fetch(
    `${getBaseUrl()}/api/v1/forms/${id}/submissions.csv`,
    {
      headers: {
        Accept: "text/csv",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    },
  );
  if (!res.ok) {
    throw new Error(`Export failed (${res.status})`);
  }
  return res.text();
}
