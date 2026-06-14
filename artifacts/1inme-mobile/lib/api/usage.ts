import { apiFetch } from "@/lib/api";

export type ApiUsage = {
  api_access: boolean;
  period: string;
  allowance: number;
  unlimited: boolean;
  calls_used: number;
  overage_calls: number;
  coins_spent: number;
  percent_used: number;
  remaining: number | null;
  rate_per_min: number;
  calls_per_coin: number;
  wallet_enabled: boolean;
  coin_balance: number;
};

/**
 * Developer API-usage summary for the current period — mirrors the meter
 * on the web /user/api-keys page and the figures behind the
 * `api.usage_warning` alerts.
 */
export async function getApiUsage(): Promise<ApiUsage> {
  const res = await apiFetch<{ data: ApiUsage }>(`/me/api-usage`);
  return res.data;
}
