import { apiFetch } from "@/lib/api";

export type Project = {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  created_at?: string | null;
};

export type ProjectPayload = {
  name: string;
  description?: string | null;
  color?: string | null;
};

export async function listProjects(): Promise<Project[]> {
  const res = await apiFetch<{ data: { items: Project[] } }>("/projects");
  return res.data.items;
}

export async function createProject(p: ProjectPayload): Promise<Project> {
  const res = await apiFetch<{ data: { project: Project } }>("/projects", {
    method: "POST",
    body: JSON.stringify(p),
  });
  return res.data.project;
}

export async function updateProject(id: number, p: Partial<ProjectPayload>): Promise<Project> {
  const res = await apiFetch<{ data: { project: Project } }>(`/projects/${id}`, {
    method: "PATCH",
    body: JSON.stringify(p),
  });
  return res.data.project;
}

export async function deleteProject(id: number): Promise<void> {
  await apiFetch(`/projects/${id}`, { method: "DELETE" });
}
