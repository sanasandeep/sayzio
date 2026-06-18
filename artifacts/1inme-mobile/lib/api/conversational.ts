import { apiFetch } from "@/lib/api";

export type CvStepKind =
  | "message"
  | "question"
  | "input"
  | "media"
  | "file_upload"
  | "rating"
  | "datetime"
  | "ai_freetext"
  | "end";

export type CvActionKind =
  | "open_link"
  | "show_block"
  | "capture_email"
  | "book_calendar"
  | "message";

export type CvCondition = {
  field?: string;
  op?: string;
  value?: string;
  goto?: string | null;
};

export type CvAiIntent = {
  value?: string;
  label?: string;
  examples?: string;
  next_step_key?: string | null;
};

export type CvStepSettings = {
  typing_delay_ms?: number | null;
  conditions?: CvCondition[];
  // input
  input_kind?: string;
  placeholder?: string;
  validation?: {
    min_length?: number | null;
    max_length?: number | null;
    regex?: string;
    error_message?: string;
  };
  // question (multi-select)
  multi_select?: boolean;
  min_choices?: number;
  max_choices?: number;
  // media
  media?: { kind?: string; url?: string; alt?: string };
  // file upload
  file?: { max_mb?: number; accept?: string };
  // rating
  rating?: { scale?: string; min?: number; max?: number };
  // datetime
  datetime?: { mode?: string; min?: string; max?: string };
  // ai free-text routing
  ai?: {
    intents?: CvAiIntent[];
    fallback_step_key?: string;
    min_confidence?: number;
  };
  [key: string]: unknown;
};

export type CvChoiceSettings = {
  condition?: CvCondition;
  [key: string]: unknown;
};

export type CvChoice = {
  label: string;
  value: string;
  next_step_key: string | null;
  action_client_id: string | null;
  settings: CvChoiceSettings;
};

export type CvStep = {
  key: string;
  kind: CvStepKind;
  message_text: string;
  answer_field: string | null;
  is_entry: boolean;
  skip_if_known: boolean;
  next_step_key: string | null;
  action_client_id: string | null;
  settings: CvStepSettings;
  choices: CvChoice[];
};

export type CvActionPayload = {
  url?: string;
  booking_url?: string;
  block_id?: number | null;
  text?: string;
  cta?: string;
  [key: string]: unknown;
};

export type CvAction = {
  client_id: string;
  kind: CvActionKind;
  label: string | null;
  payload: CvActionPayload | null;
};

export type CvFlowSettings = {
  default_typing_ms?: number;
  [key: string]: unknown;
};

export type CvFlow = {
  name: string | null;
  intro_message: string | null;
  is_published: boolean;
  settings: CvFlowSettings;
  actions: CvAction[];
  steps: CvStep[];
};

export type CvBlockOption = { id: number; type: string; label: string };

export type CvMeta = {
  link_id: number;
  alias: string;
  public_url: string;
  step_kinds: Record<string, string>;
  action_kinds: Record<string, string>;
  input_kinds: string[];
  condition_ops: string[];
  media_kinds: string[];
  rating_scales: string[];
  datetime_modes: string[];
  blocks: CvBlockOption[];
};

export type CvEditor = { flow: CvFlow; meta: CvMeta };

export type CvSavePayload = {
  name: string | null;
  intro_message: string | null;
  is_published: boolean;
  settings: CvFlowSettings;
  actions: CvAction[];
  steps: CvStep[];
};

export async function getConversationalFlow(id: number): Promise<CvEditor> {
  const res = await apiFetch<{ data: CvEditor }>(`/links/${id}/conversational`);
  return res.data;
}

export async function saveConversationalFlow(
  id: number,
  payload: CvSavePayload,
): Promise<CvEditor> {
  const res = await apiFetch<{ data: CvEditor }>(
    `/links/${id}/conversational`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
  );
  return res.data;
}
