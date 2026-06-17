// Mirrors app/Modules/User/Support/PaidPageTemplates.php ids + a single
// representative accent swatch for the picker. The full theme is applied
// server-side on the public page.
export const PAID_PAGE_TEMPLATES: { id: string; name: string; swatch: string }[] =
  [
    { id: "aurora", name: "Aurora", swatch: "#a855f7" },
    { id: "sunset", name: "Sunset Blvd", swatch: "#fb7185" },
    { id: "electric", name: "Electric", swatch: "#22d3ee" },
    { id: "mono", name: "Mono Bold", swatch: "#f43f5e" },
    { id: "candy", name: "Candy Pop", swatch: "#e879f9" },
  ];

export const PAID_PAGE_DEFAULT_TEMPLATE = "aurora";

export function paidPageTemplateId(value: unknown): string {
  const id = typeof value === "string" ? value : "";
  return PAID_PAGE_TEMPLATES.some((t) => t.id === id)
    ? id
    : PAID_PAGE_DEFAULT_TEMPLATE;
}
