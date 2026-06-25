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

export type FormLineItem = {
  field: string | null;
  label: string | null;
  detail: string | null;
  amount_cents: number;
};

export type FormSubmission = {
  id: number;
  data: Record<string, unknown> | null;
  ip: string | null;
  payment_status: string | null;
  amount_cents: number | null;
  currency: string | null;
  line_items: FormLineItem[] | null;
  paid_at: string | null;
  created_at: string | null;
};

export async function listForms(): Promise<{ items: Form[] }> {
  const res = await apiFetch<{ data: { items: Form[] } }>(`/forms`);
  return { items: res.data.items };
}

/** Starter templates offered when creating a form from the block editor. */
export const FORM_TEMPLATES: { value: string; label: string }[] = [
  { value: "contact", label: "Contact" },
  { value: "lead", label: "Lead capture" },
  { value: "survey", label: "Survey" },
  { value: "registration", label: "Registration" },
  { value: "feedback", label: "Feedback" },
  { value: "blank", label: "Blank" },
];

export async function createForm(payload: {
  title: string;
  template?: string;
}): Promise<Form> {
  const res = await apiFetch<{ data: { form: Form } }>(`/forms`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return res.data.form;
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
