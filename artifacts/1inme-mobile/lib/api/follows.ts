import { apiFetch } from "@/lib/api";

export type FollowUser = {
  id: number;
  name: string | null;
  display_name: string | null;
  avatar: string | null;
  handle?: string | null;
};

export async function listFollowers(): Promise<{ items: FollowUser[] }> {
  const res = await apiFetch<{ data: { items: FollowUser[] } }>(
    `/follows/followers?per_page=100`,
  );
  return { items: res.data.items };
}

export async function listFollowing(): Promise<{ items: FollowUser[] }> {
  const res = await apiFetch<{ data: { items: FollowUser[] } }>(
    `/follows/following?per_page=100`,
  );
  return { items: res.data.items };
}

export async function follow(userId: number): Promise<void> {
  await apiFetch(`/follows/${userId}`, { method: "POST" });
}

export async function unfollow(userId: number): Promise<void> {
  await apiFetch(`/follows/${userId}`, { method: "DELETE" });
}
