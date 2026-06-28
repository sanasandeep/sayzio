import { apiFetch } from "@/lib/api";

// ── Billing companies ───────────────────────────────────────────────

export type BillingCompany = {
  id: number;
  name: string;
  legal_name: string | null;
  email: string | null;
  phone: string | null;
  website: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  tax_id_label: string | null;
  tax_id_value: string | null;
  secondary_tax_label: string | null;
  secondary_tax_value: string | null;
  default_currency: string | null;
  invoice_prefix: string | null;
  default_tax_rule_id: number | null;
  notes: string | null;
  is_default: boolean;
};

export type BillingCompanyInput = Partial<Omit<BillingCompany, "id">> & {
  name: string;
};

export async function listCompanies(): Promise<BillingCompany[]> {
  const res = await apiFetch<{ data: { items: BillingCompany[] } }>(
    "/billing/companies",
  );
  return res.data.items;
}

export async function createCompany(
  input: BillingCompanyInput,
): Promise<BillingCompany> {
  const res = await apiFetch<{ data: { company: BillingCompany } }>(
    "/billing/companies",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.company;
}

export async function updateCompany(
  id: number,
  input: BillingCompanyInput,
): Promise<BillingCompany> {
  const res = await apiFetch<{ data: { company: BillingCompany } }>(
    `/billing/companies/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.company;
}

export async function deleteCompany(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/companies/${id}`, { method: "DELETE" });
}

// ── Tax rules ───────────────────────────────────────────────────────

export type TaxRule = {
  id: number;
  name: string;
  rate_bps: number;
  billing_company_id: number | null;
  inclusive: boolean;
  is_compound: boolean;
  is_default: boolean;
  is_active: boolean;
};

export type TaxRuleInput = {
  name: string;
  rate_bps: number;
  billing_company_id?: number | null;
  inclusive?: boolean;
  is_compound?: boolean;
  is_default?: boolean;
  is_active?: boolean;
};

export async function listTaxRules(): Promise<TaxRule[]> {
  const res = await apiFetch<{ data: { items: TaxRule[] } }>(
    "/billing/tax-rules",
  );
  return res.data.items;
}

export async function createTaxRule(input: TaxRuleInput): Promise<TaxRule> {
  const res = await apiFetch<{ data: { tax_rule: TaxRule } }>(
    "/billing/tax-rules",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.tax_rule;
}

export async function updateTaxRule(
  id: number,
  input: TaxRuleInput,
): Promise<TaxRule> {
  const res = await apiFetch<{ data: { tax_rule: TaxRule } }>(
    `/billing/tax-rules/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.tax_rule;
}

export async function deleteTaxRule(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/tax-rules/${id}`, { method: "DELETE" });
}

// ── Catalog ─────────────────────────────────────────────────────────

export type CatalogCategory = { id: number; name: string; kind: string };

export type CatalogItem = {
  id: number;
  name: string;
  description: string | null;
  unit_price_minor: number;
  currency: string | null;
  category_id: number | null;
  tax_rule_id: number | null;
  billing_company_id: number | null;
  sku: string | null;
  unit_label: string | null;
  is_active: boolean;
};

export type CatalogItemInput = {
  name: string;
  description?: string | null;
  unit_price_minor: number;
  currency?: string;
  category_id?: number | null;
  tax_rule_id?: number | null;
  billing_company_id?: number | null;
  sku?: string | null;
  unit_label?: string | null;
  is_active?: boolean;
};

export async function getCatalog(): Promise<{
  categories: CatalogCategory[];
  items: CatalogItem[];
}> {
  const res = await apiFetch<{
    data: { categories: CatalogCategory[]; items: CatalogItem[] };
  }>("/billing/catalog");
  return res.data;
}

export async function createCategory(input: {
  name: string;
  kind?: string;
}): Promise<CatalogCategory> {
  const res = await apiFetch<{ data: { category: CatalogCategory } }>(
    "/billing/catalog/categories",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function deleteCategory(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/catalog/categories/${id}`, {
    method: "DELETE",
  });
}

export async function createItem(input: CatalogItemInput): Promise<CatalogItem> {
  const res = await apiFetch<{ data: { item: CatalogItem } }>(
    "/billing/catalog/items",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.item;
}

export async function updateItem(
  id: number,
  input: CatalogItemInput,
): Promise<CatalogItem> {
  const res = await apiFetch<{ data: { item: CatalogItem } }>(
    `/billing/catalog/items/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.item;
}

export async function deleteItem(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/catalog/items/${id}`, { method: "DELETE" });
}

// ── Expenses ────────────────────────────────────────────────────────

export type Expense = {
  id: number;
  billing_company_id: number | null;
  category_id: number | null;
  vendor: string | null;
  description: string | null;
  spent_at: string | null;
  amount_minor: number;
  tax_minor: number;
  currency: string | null;
  notes: string | null;
};

export type ExpenseInput = {
  billing_company_id?: number | null;
  category_id?: number | null;
  vendor?: string | null;
  description?: string | null;
  spent_at: string;
  amount_minor: number;
  tax_minor?: number;
  currency?: string;
  notes?: string | null;
};

export async function listExpenses(page = 1): Promise<{
  items: Expense[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const res = await apiFetch<{
    data: {
      items: Expense[];
      meta: { current_page: number; per_page: number; total: number; last_page: number };
    };
  }>(`/billing/expenses?page=${page}`);
  return res.data;
}

export async function createExpense(input: ExpenseInput): Promise<Expense> {
  const res = await apiFetch<{ data: { expense: Expense } }>(
    "/billing/expenses",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.expense;
}

export async function updateExpense(
  id: number,
  input: ExpenseInput,
): Promise<Expense> {
  const res = await apiFetch<{ data: { expense: Expense } }>(
    `/billing/expenses/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.expense;
}

export async function deleteExpense(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/expenses/${id}`, { method: "DELETE" });
}

// ── Recurring invoices ──────────────────────────────────────────────

export type RecurringLine = {
  label: string;
  amount_minor: number;
  quantity?: number;
  tax_rate_bps?: number;
  tax_name?: string;
  tax_inclusive?: boolean;
};

export type RecurringInvoice = {
  id: number;
  title: string | null;
  billing_company_id: number | null;
  vault_client_id: number | null;
  recipient_email: string | null;
  currency: string | null;
  discount_minor: number;
  tax_rule_id: number | null;
  notes_md: string | null;
  interval: string;
  interval_count: number;
  start_date: string | null;
  end_date: string | null;
  next_run_date: string | null;
  max_occurrences: number | null;
  occurrences_count: number;
  auto_send: boolean;
  status: string;
  line_items: RecurringLine[];
};

export type RecurringInvoiceInput = {
  title?: string | null;
  billing_company_id?: number | null;
  vault_client_id?: number | null;
  recipient_email?: string | null;
  currency?: string;
  discount_minor?: number;
  tax_rule_id?: number | null;
  notes_md?: string | null;
  interval: "weekly" | "monthly" | "quarterly" | "yearly";
  interval_count?: number;
  start_date: string;
  end_date?: string | null;
  max_occurrences?: number | null;
  auto_send?: boolean;
  status?: "active" | "paused" | "cancelled" | "completed";
  line_items: RecurringLine[];
};

export async function listRecurring(): Promise<RecurringInvoice[]> {
  const res = await apiFetch<{ data: { items: RecurringInvoice[] } }>(
    "/billing/recurring",
  );
  return res.data.items;
}

export async function createRecurring(
  input: RecurringInvoiceInput,
): Promise<RecurringInvoice> {
  const res = await apiFetch<{ data: { recurring: RecurringInvoice } }>(
    "/billing/recurring",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.recurring;
}

export async function updateRecurring(
  id: number,
  input: RecurringInvoiceInput,
): Promise<RecurringInvoice> {
  const res = await apiFetch<{ data: { recurring: RecurringInvoice } }>(
    `/billing/recurring/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.recurring;
}

export async function deleteRecurring(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/recurring/${id}`, { method: "DELETE" });
}

export async function runRecurring(
  id: number,
): Promise<{ invoice_id: number; number: string }> {
  const res = await apiFetch<{ data: { invoice_id: number; number: string } }>(
    `/billing/recurring/${id}/run`,
    { method: "POST" },
  );
  return res.data;
}

// ── Ledger report ───────────────────────────────────────────────────

export type LedgerReport = {
  range: { from: string; to: string };
  currency: string;
  totals: {
    income_minor: number;
    refunded_minor: number;
    net_income_minor: number;
    expense_minor: number;
    expense_tax_minor: number;
    tax_collected_minor: number;
    profit_minor: number;
    invoice_count: number;
    expense_count: number;
  };
  by_month: {
    month: string;
    income_minor: number;
    expense_minor: number;
    profit_minor: number;
  }[];
};

export async function getLedger(params: {
  from?: string;
  to?: string;
  company?: number;
} = {}): Promise<LedgerReport> {
  const qs = new URLSearchParams();
  if (params.from) qs.set("from", params.from);
  if (params.to) qs.set("to", params.to);
  if (params.company) qs.set("company", String(params.company));
  const suffix = qs.toString() ? `?${qs.toString()}` : "";
  const res = await apiFetch<{ data: { report: LedgerReport } }>(
    `/billing/ledger${suffix}`,
  );
  return res.data.report;
}
