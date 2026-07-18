/**
 * Form autofill engine — pure shared module.
 *
 * Split into two layers:
 *  1. Pure TS functions (categorizeField, shouldExcludeField, mapFieldToCard)
 *     — testable in Node/vitest with no DOM dependency.
 *  2. buildAutofillScript(card) — serialises the logic into a self-contained
 *     IIFE string injected into the page via webContents.executeJavaScript().
 *
 * Security rules enforced at both layers:
 *   • Password, payment/credit-card, hidden, and OTP fields are never touched.
 *   • Only empty fields are filled (never overwrites user-typed content).
 *   • No auto-submit — the user must review and submit manually.
 */

// ── Types ────────────────────────────────────────────────────────────────────

/** Data from the user's digital card / Sayzio profile used to fill forms. */
export interface AutofillCard {
  full_name?: string;
  given_name?: string;
  family_name?: string;
  email?: string;
  phone?: string;
  organization?: string;
  job_title?: string;
  website?: string;
}

/** Detected semantic category of a form field. */
export type FieldKind =
  | 'full_name'
  | 'given_name'
  | 'family_name'
  | 'email'
  | 'phone'
  | 'organization'
  | 'job_title'
  | 'website'
  | 'address_line1'
  | 'city'
  | 'state'
  | 'zip'
  | 'country'
  | 'unknown';

/** Subset of HTML element attributes used for field classification. */
export interface FieldAttributes {
  type?: string;
  name?: string;
  id?: string;
  autocomplete?: string;
  placeholder?: string;
  /** Text of the associated <label>, aria-label, or nearest label-like sibling. */
  label?: string;
}

/** Result returned by the injected autofill script. */
export interface AutofillResult {
  filled: number;
  filled_fields: string[];
}

// ── Exclusion rules ──────────────────────────────────────────────────────────

/**
 * Input types that must never be touched.
 * Exported for reference in tests and the injected script.
 */
export const BLOCKED_INPUT_TYPES = new Set([
  'password',
  'hidden',
  'submit',
  'reset',
  'button',
  'file',
  'image',
  'checkbox',
  'radio',
  'range',
  'color',
  'date',
  'datetime-local',
  'month',
  'time',
  'week',
]);

/**
 * autocomplete attribute values that belong to payment/security fields.
 * Exported for reference in tests and the injected script.
 */
export const BLOCKED_AUTOCOMPLETE_VALUES = new Set([
  'cc-number',
  'cc-exp',
  'cc-csc',
  'cc-name',
  'cc-type',
  'cc-exp-month',
  'cc-exp-year',
  'new-password',
  'current-password',
  'one-time-code',
]);

/** Pattern matching payment/card/security-related field names. */
export const BLOCKED_NAME_PATTERN =
  /\b(card|credit|cvv|cvc|expir|ccnum|cc_num|ssn|social.?sec|passport|^pin$|payment|billing\.?num)/i;

/**
 * Returns true if this field should be skipped during autofill.
 * Pure function — no DOM access.
 */
export function shouldExcludeField(attrs: FieldAttributes): boolean {
  if (attrs.type && BLOCKED_INPUT_TYPES.has(attrs.type.toLowerCase())) return true;
  if (attrs.autocomplete && BLOCKED_AUTOCOMPLETE_VALUES.has(attrs.autocomplete.toLowerCase())) return true;
  const combined = [attrs.name, attrs.id, attrs.autocomplete].filter(Boolean).join(' ');
  if (BLOCKED_NAME_PATTERN.test(combined)) return true;
  return false;
}

// ── Field categorization ─────────────────────────────────────────────────────

/**
 * Determine what kind of data a form field expects.
 * Checks (in order): autocomplete attribute → type attribute → name/id/placeholder/label heuristics.
 * Pure function — no DOM access.
 */
export function categorizeField(attrs: FieldAttributes): FieldKind {
  const ac = (attrs.autocomplete ?? '').toLowerCase().trim();

  // Standard autocomplete values
  const acMap: Record<string, FieldKind> = {
    'given-name': 'given_name',
    'family-name': 'family_name',
    name: 'full_name',
    email: 'email',
    tel: 'phone',
    organization: 'organization',
    'organization-title': 'job_title',
    url: 'website',
    'address-line1': 'address_line1',
    'address-level2': 'city',
    'address-level1': 'state',
    'postal-code': 'zip',
    country: 'country',
    'country-name': 'country',
  };
  if (ac && acMap[ac]) return acMap[ac]!;

  // Type attribute shortcuts
  const type = (attrs.type ?? '').toLowerCase();
  if (type === 'email') return 'email';
  if (type === 'tel') return 'phone';
  if (type === 'url') return 'website';

  // Heuristic: combine name, id, placeholder, label and scan with patterns
  const combined = [attrs.name, attrs.id, attrs.placeholder, attrs.label]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();

  if (/\b(first.?name|given.?name|fname)\b/.test(combined)) return 'given_name';
  if (/\b(last.?name|surname|family.?name|lname)\b/.test(combined)) return 'family_name';
  if (/\b(full.?name|your.?name|display.?name)\b/.test(combined)) return 'full_name';
  // email — no leading word-boundary: catches user_email, emailAddress, email_address
  if (/email/.test(combined)) return 'email';
  if (/\b(phone|mobile|cell(?:ular)?|tel(?:ephone)?|whatsapp)\b/.test(combined)) return 'phone';
  // organization before bare "name" so "company name" → organization, not full_name
  if (/\b(company|organization|org|employer|business)\b/.test(combined)) return 'organization';
  if (/\b(job.?title|job.?role|position|designation)\b/.test(combined)) return 'job_title';
  // "title" alone only when not combined with other words that suggest a heading
  if (/(?:^|\s)title(?:\s|$)/.test(combined) && !/\b(page|post|article|news)\b/.test(combined)) return 'job_title';
  if (/\b(website|web.?site|homepage|domain|url)\b/.test(combined)) return 'website';
  if (/\b(address|street|addr(?:ess)?)\b/.test(combined)) return 'address_line1';
  if (/\b(city|town)\b/.test(combined)) return 'city';
  if (/\b(state|province|region)\b/.test(combined)) return 'state';
  if (/\b(zip|postal|post.?code)\b/.test(combined)) return 'zip';
  if (/\b(country)\b/.test(combined)) return 'country';
  // bare "name" last — only when no other category matched above
  if (/(?:^|\s)name(?:\s|$)/.test(combined)) return 'full_name';

  return 'unknown';
}

// ── Card value mapping ────────────────────────────────────────────────────────

/**
 * Return the value from the user's card that matches this field kind, or null.
 * Pure function — no DOM access.
 */
export function mapFieldToCard(kind: FieldKind, card: AutofillCard): string | null {
  switch (kind) {
    case 'full_name':   return card.full_name   ?? null;
    case 'given_name':  return card.given_name  ?? null;
    case 'family_name': return card.family_name ?? null;
    case 'email':       return card.email       ?? null;
    case 'phone':       return card.phone       ?? null;
    case 'organization': return card.organization ?? null;
    case 'job_title':   return card.job_title   ?? null;
    case 'website':     return card.website     ?? null;
    default:            return null;
  }
}

// ── Script builder ────────────────────────────────────────────────────────────

/**
 * Build a self-contained IIFE script string that, when executed in the page
 * via webContents.executeJavaScript(), fills form fields from the given card.
 *
 * Returns { filled: number, filled_fields: string[] }.
 *
 * Security guarantees baked in:
 *   - Excluded types/autocomplete/names are never touched.
 *   - Only empty fields are filled.
 *   - No auto-submit.
 *   - Dispatches synthetic input/change events so framework state machines
 *     (React, Vue, Angular) pick up the new values.
 *   - Highlights filled fields for 3 s then fades back.
 */
export function buildAutofillScript(card: AutofillCard): string {
  const cardJson = JSON.stringify(card);
  const blockedTypesJson = JSON.stringify([...BLOCKED_INPUT_TYPES]);
  const blockedAcJson = JSON.stringify([...BLOCKED_AUTOCOMPLETE_VALUES]);
  // Embed the exclusion pattern source without flags — we re-add the flag inside the script.
  const blockedNameSrc = BLOCKED_NAME_PATTERN.source;

  return `(function() {
  var card = ${cardJson};
  var BLOCKED_TYPES = new Set(${blockedTypesJson});
  var BLOCKED_AC = new Set(${blockedAcJson});
  var BLOCKED_NAME = new RegExp(${JSON.stringify(blockedNameSrc)}, 'i');

  function shouldExclude(el) {
    var type = (el.type || '').toLowerCase();
    if (BLOCKED_TYPES.has(type)) return true;
    var ac = (el.getAttribute('autocomplete') || '').toLowerCase();
    if (BLOCKED_AC.has(ac)) return true;
    var combined = [el.name, el.id, ac].filter(Boolean).join(' ');
    if (BLOCKED_NAME.test(combined)) return true;
    return false;
  }

  function getLabelText(el) {
    var ariaLabel = el.getAttribute('aria-label');
    if (ariaLabel) return ariaLabel;
    var labelledBy = el.getAttribute('aria-labelledby');
    if (labelledBy) {
      var lbEl = document.getElementById(labelledBy);
      if (lbEl) return lbEl.textContent || '';
    }
    if (el.id) {
      try {
        var forLabel = document.querySelector('label[for="' + CSS.escape(el.id) + '"]');
        if (forLabel) return forLabel.textContent || '';
      } catch(e) {}
    }
    var parent = el.closest('label');
    if (parent) return parent.textContent || '';
    var prev = el.previousElementSibling;
    if (prev && /^(LABEL|SPAN|P|DIV)$/.test(prev.tagName)) {
      return prev.textContent || '';
    }
    return '';
  }

  function categorize(el) {
    var ac = (el.getAttribute('autocomplete') || '').toLowerCase().trim();
    var acMap = {
      'given-name': 'given_name', 'family-name': 'family_name',
      'name': 'full_name', 'email': 'email', 'tel': 'phone',
      'organization': 'organization', 'organization-title': 'job_title',
      'url': 'website', 'address-line1': 'address_line1',
      'address-level2': 'city', 'address-level1': 'state',
      'postal-code': 'zip', 'country': 'country', 'country-name': 'country'
    };
    if (ac && acMap[ac]) return acMap[ac];
    var type = (el.type || '').toLowerCase();
    if (type === 'email') return 'email';
    if (type === 'tel') return 'phone';
    if (type === 'url') return 'website';
    var label = getLabelText(el);
    var combined = [el.name, el.id, el.placeholder, label].filter(Boolean).join(' ').toLowerCase();
    if (/\\b(first.?name|given.?name|fname)\\b/.test(combined)) return 'given_name';
    if (/\\b(last.?name|surname|family.?name|lname)\\b/.test(combined)) return 'family_name';
    if (/\\b(full.?name|your.?name|display.?name)\\b/.test(combined)) return 'full_name';
    if (/email/.test(combined)) return 'email';
    if (/\\b(phone|mobile|cell(?:ular)?|tel(?:ephone)?|whatsapp)\\b/.test(combined)) return 'phone';
    if (/\\b(company|organization|org|employer|business)\\b/.test(combined)) return 'organization';
    if (/\\b(job.?title|job.?role|position|designation)\\b/.test(combined)) return 'job_title';
    if (/(?:^|\\s)title(?:\\s|$)/.test(combined) && !/\\b(page|post|article)\\b/.test(combined)) return 'job_title';
    if (/\\b(website|web.?site|homepage|domain|url)\\b/.test(combined)) return 'website';
    if (/\\b(address|street|addr)\\b/.test(combined)) return 'address_line1';
    if (/\\b(city|town)\\b/.test(combined)) return 'city';
    if (/\\b(state|province|region)\\b/.test(combined)) return 'state';
    if (/\\b(zip|postal|post.?code)\\b/.test(combined)) return 'zip';
    if (/\\b(country)\\b/.test(combined)) return 'country';
    if (/(?:^|\\s)name(?:\\s|$)/.test(combined)) return 'full_name';
    return null;
  }

  function getCardValue(kind) {
    var map = {
      full_name: card.full_name, given_name: card.given_name,
      family_name: card.family_name, email: card.email, phone: card.phone,
      organization: card.organization, job_title: card.job_title, website: card.website
    };
    return map[kind] || null;
  }

  var inputs = document.querySelectorAll('input, textarea');
  var filled = 0;
  var filled_fields = [];

  for (var i = 0; i < inputs.length; i++) {
    var el = inputs[i];
    if (shouldExclude(el)) continue;
    var kind = categorize(el);
    if (!kind) continue;
    var value = getCardValue(kind);
    if (!value) continue;
    if (el.value && el.value.trim()) continue;

    el.value = value;

    try {
      var nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
      if (nativeInputValueSetter && nativeInputValueSetter.set) {
        nativeInputValueSetter.set.call(el, value);
      }
    } catch(e) {}

    el.dispatchEvent(new Event('input',  { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));

    el.style.outline = '2px solid #6366f1';
    el.style.outlineOffset = '1px';
    (function(target) {
      setTimeout(function() {
        target.style.outline = '';
        target.style.outlineOffset = '';
      }, 3000);
    })(el);

    filled++;
    filled_fields.push(kind);
  }

  return { filled: filled, filled_fields: filled_fields };
})()`;
}
