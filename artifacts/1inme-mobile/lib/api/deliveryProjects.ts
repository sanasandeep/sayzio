import { apiFetch } from "@/lib/api";

export type DeliveryTaskStatus = "todo" | "in_progress" | "done";

export type DeliveryTask = {
  id: number;
  title: string;
  status: DeliveryTaskStatus;
  status_label: string;
  progress: number;
  assignee_user_id: number | null;
  assignee_name: string | null;
  start_date: string | null;
  due_date: string | null;
  position: number;
};

export type DeliveryProjectSummary = {
  id: number;
  title: string;
  description: string | null;
  status: string;
  status_label: string;
  source_label: string | null;
  client_name: string | null;
  client_email: string | null;
  progress: number;
  tasks_count: number;
  done_tasks_count: number | null;
  created_by: string | null;
  completed_at: string | null;
  warranty_expires_at: string | null;
  warranty_reminder_days: number | null;
  warranty_active: boolean;
  warranty_expired: boolean;
};

export type DeliveryProjectDetail = DeliveryProjectSummary & {
  tasks: DeliveryTask[];
};

export type DeliveryProjectMember = {
  id: number;
  user_id: number;
  name: string | null;
  avatar: string | null;
};

export type DeliveryProjectShow = {
  project: DeliveryProjectDetail;
  members: DeliveryProjectMember[];
  statuses: Record<string, string>;
  share_url: string;
};

export type DeliveryTaskInput = {
  title: string;
  assignee_user_id?: number | null;
  start_date?: string | null;
  due_date?: string | null;
};

export type DeliveryTaskUpdate = {
  title?: string;
  status?: DeliveryTaskStatus;
  progress?: number;
  assignee_user_id?: number | null;
  start_date?: string | null;
  due_date?: string | null;
};

export async function listDeliveryProjects(): Promise<DeliveryProjectSummary[]> {
  const res = await apiFetch<{ data: { items: DeliveryProjectSummary[] } }>(
    "/delivery-projects",
  );
  return res.data.items;
}

export async function getDeliveryProject(
  id: number,
): Promise<DeliveryProjectShow> {
  const res = await apiFetch<{ data: DeliveryProjectShow }>(
    `/delivery-projects/${id}`,
  );
  return res.data;
}

export async function createDeliveryTask(
  projectId: number,
  input: DeliveryTaskInput,
): Promise<DeliveryTask> {
  const res = await apiFetch<{ data: { task: DeliveryTask } }>(
    `/delivery-projects/${projectId}/tasks`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.task;
}

export async function updateDeliveryTask(
  taskId: number,
  input: DeliveryTaskUpdate,
): Promise<DeliveryTask> {
  const res = await apiFetch<{ data: { task: DeliveryTask } }>(
    `/delivery-projects/tasks/${taskId}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.task;
}

export async function deleteDeliveryTask(taskId: number): Promise<void> {
  await apiFetch<{ data: { deleted: boolean } }>(
    `/delivery-projects/tasks/${taskId}`,
    { method: "DELETE" },
  );
}
