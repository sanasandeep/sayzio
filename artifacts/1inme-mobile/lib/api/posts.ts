import { apiFetch } from "@/lib/api";

export type Post = {
  id: number;
  title: string | null;
  body: string;
  image: string | null;
  scheduled_at: string | null;
  published_at: string | null;
  pinned_at: string | null;
  is_pinned: boolean;
  is_scheduled: boolean;
  status: string;
  created_at: string | null;
};

export async function listPosts(): Promise<{ items: Post[] }> {
  const res = await apiFetch<{ data: { items: Post[] } }>(`/posts`);
  return { items: res.data.items };
}

export async function createPost(payload: {
  title?: string | null;
  body: string;
  image?: string | null;
  scheduled_at?: string | null;
  is_pinned?: boolean;
}): Promise<Post> {
  const res = await apiFetch<{ data: { post: Post } }>(`/posts`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return res.data.post;
}

export async function updatePost(
  id: number,
  patch: { title?: string | null; body?: string; image?: string | null },
): Promise<Post> {
  const res = await apiFetch<{ data: { post: Post } }>(`/posts/${id}`, {
    method: "PATCH",
    body: JSON.stringify(patch),
  });
  return res.data.post;
}

export async function deletePost(id: number): Promise<void> {
  await apiFetch(`/posts/${id}`, { method: "DELETE" });
}

export async function pinPost(id: number): Promise<Post> {
  const res = await apiFetch<{ data: { post: Post } }>(`/posts/${id}/pin`, {
    method: "POST",
  });
  return res.data.post;
}

export async function unpinPost(id: number): Promise<Post> {
  const res = await apiFetch<{ data: { post: Post } }>(`/posts/${id}/unpin`, {
    method: "POST",
  });
  return res.data.post;
}
