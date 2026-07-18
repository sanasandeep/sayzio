import { describe, it, expect } from 'vitest';
import {
  shouldExcludeField,
  categorizeField,
  mapFieldToCard,
  buildAutofillScript,
  BLOCKED_INPUT_TYPES,
  BLOCKED_AUTOCOMPLETE_VALUES,
  BLOCKED_NAME_PATTERN,
  type AutofillCard,
  type FieldAttributes,
  type FieldKind,
} from '../src/shared/form-autofill';

// ── shouldExcludeField ────────────────────────────────────────────────────────

describe('shouldExcludeField', () => {
  it('excludes password fields', () => {
    expect(shouldExcludeField({ type: 'password' })).toBe(true);
  });

  it('excludes hidden fields', () => {
    expect(shouldExcludeField({ type: 'hidden' })).toBe(true);
  });

  it('excludes submit/reset/button types', () => {
    expect(shouldExcludeField({ type: 'submit' })).toBe(true);
    expect(shouldExcludeField({ type: 'reset' })).toBe(true);
    expect(shouldExcludeField({ type: 'button' })).toBe(true);
  });

  it('excludes file, checkbox, radio', () => {
    expect(shouldExcludeField({ type: 'file' })).toBe(true);
    expect(shouldExcludeField({ type: 'checkbox' })).toBe(true);
    expect(shouldExcludeField({ type: 'radio' })).toBe(true);
  });

  it('excludes payment autocomplete values', () => {
    expect(shouldExcludeField({ autocomplete: 'cc-number' })).toBe(true);
    expect(shouldExcludeField({ autocomplete: 'cc-exp' })).toBe(true);
    expect(shouldExcludeField({ autocomplete: 'cc-csc' })).toBe(true);
    expect(shouldExcludeField({ autocomplete: 'cc-name' })).toBe(true);
    expect(shouldExcludeField({ autocomplete: 'new-password' })).toBe(true);
    expect(shouldExcludeField({ autocomplete: 'current-password' })).toBe(true);
    expect(shouldExcludeField({ autocomplete: 'one-time-code' })).toBe(true);
  });

  it('excludes fields whose name matches payment keywords', () => {
    expect(shouldExcludeField({ name: 'card_number' })).toBe(true);
    expect(shouldExcludeField({ name: 'credit_card' })).toBe(true);
    expect(shouldExcludeField({ name: 'cvv' })).toBe(true);
    expect(shouldExcludeField({ name: 'cvc' })).toBe(true);
    expect(shouldExcludeField({ id: 'expiry_date' })).toBe(true);
  });

  it('does not exclude safe text fields', () => {
    expect(shouldExcludeField({ type: 'text', name: 'full_name' })).toBe(false);
    expect(shouldExcludeField({ type: 'email', name: 'email' })).toBe(false);
    expect(shouldExcludeField({ type: 'tel', name: 'phone' })).toBe(false);
  });

  it('is case-insensitive for type', () => {
    expect(shouldExcludeField({ type: 'PASSWORD' })).toBe(true);
    expect(shouldExcludeField({ type: 'Hidden' })).toBe(true);
  });

  it('has a consistent set of BLOCKED_INPUT_TYPES', () => {
    expect(BLOCKED_INPUT_TYPES.has('password')).toBe(true);
    expect(BLOCKED_INPUT_TYPES.has('hidden')).toBe(true);
    expect(BLOCKED_INPUT_TYPES.has('text')).toBe(false);
  });

  it('has a consistent BLOCKED_AUTOCOMPLETE_VALUES set', () => {
    expect(BLOCKED_AUTOCOMPLETE_VALUES.has('cc-number')).toBe(true);
    expect(BLOCKED_AUTOCOMPLETE_VALUES.has('email')).toBe(false);
  });

  it('BLOCKED_NAME_PATTERN matches known payment keywords', () => {
    expect(BLOCKED_NAME_PATTERN.test('card')).toBe(true);
    expect(BLOCKED_NAME_PATTERN.test('credit')).toBe(true);
    expect(BLOCKED_NAME_PATTERN.test('cvv')).toBe(true);
    expect(BLOCKED_NAME_PATTERN.test('cvc')).toBe(true);
    expect(BLOCKED_NAME_PATTERN.test('expiry')).toBe(true);
    expect(BLOCKED_NAME_PATTERN.test('payment')).toBe(true);
    expect(BLOCKED_NAME_PATTERN.test('firstname')).toBe(false);
    expect(BLOCKED_NAME_PATTERN.test('email')).toBe(false);
  });
});

// ── categorizeField ───────────────────────────────────────────────────────────

describe('categorizeField', () => {
  // Autocomplete-based
  it('categorizes given-name via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'given-name' })).toBe('given_name');
  });

  it('categorizes family-name via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'family-name' })).toBe('family_name');
  });

  it('categorizes full name via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'name' })).toBe('full_name');
  });

  it('categorizes email via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'email' })).toBe('email');
  });

  it('categorizes phone via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'tel' })).toBe('phone');
  });

  it('categorizes organization via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'organization' })).toBe('organization');
  });

  it('categorizes job title via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'organization-title' })).toBe('job_title');
  });

  it('categorizes url via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'url' })).toBe('website');
  });

  it('categorizes address-line1 via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'address-line1' })).toBe('address_line1');
  });

  it('categorizes city via autocomplete (address-level2)', () => {
    expect(categorizeField({ autocomplete: 'address-level2' })).toBe('city');
  });

  it('categorizes state via autocomplete (address-level1)', () => {
    expect(categorizeField({ autocomplete: 'address-level1' })).toBe('state');
  });

  it('categorizes postal code via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'postal-code' })).toBe('zip');
  });

  it('categorizes country via autocomplete', () => {
    expect(categorizeField({ autocomplete: 'country' })).toBe('country');
    expect(categorizeField({ autocomplete: 'country-name' })).toBe('country');
  });

  // Type-based shortcuts
  it('categorizes email via type attribute', () => {
    expect(categorizeField({ type: 'email' })).toBe('email');
  });

  it('categorizes phone via tel type', () => {
    expect(categorizeField({ type: 'tel' })).toBe('phone');
  });

  it('categorizes website via url type', () => {
    expect(categorizeField({ type: 'url' })).toBe('website');
  });

  // Name/id/placeholder heuristics
  it('categorizes first name via name attribute', () => {
    expect(categorizeField({ type: 'text', name: 'firstName' })).toBe('given_name');
    expect(categorizeField({ type: 'text', name: 'fname' })).toBe('given_name');
    expect(categorizeField({ type: 'text', name: 'given_name' })).toBe('given_name');
  });

  it('categorizes last name via name attribute', () => {
    expect(categorizeField({ type: 'text', name: 'lastName' })).toBe('family_name');
    expect(categorizeField({ type: 'text', name: 'lname' })).toBe('family_name');
    expect(categorizeField({ type: 'text', name: 'surname' })).toBe('family_name');
    expect(categorizeField({ type: 'text', name: 'family_name' })).toBe('family_name');
  });

  it('categorizes full name via name attribute', () => {
    expect(categorizeField({ type: 'text', name: 'fullName' })).toBe('full_name');
    expect(categorizeField({ type: 'text', name: 'your_name' })).toBe('full_name');
    expect(categorizeField({ type: 'text', name: 'displayName' })).toBe('full_name');
  });

  it('categorizes bare "name" field as full_name', () => {
    expect(categorizeField({ type: 'text', name: 'name' })).toBe('full_name');
  });

  it('categorizes email via name attribute', () => {
    expect(categorizeField({ type: 'text', name: 'email' })).toBe('email');
    expect(categorizeField({ type: 'text', id: 'user_email' })).toBe('email');
  });

  it('categorizes phone via name attribute', () => {
    expect(categorizeField({ type: 'text', name: 'phone' })).toBe('phone');
    expect(categorizeField({ type: 'text', name: 'mobile' })).toBe('phone');
    expect(categorizeField({ type: 'text', name: 'telephone' })).toBe('phone');
    expect(categorizeField({ type: 'text', name: 'whatsapp' })).toBe('phone');
  });

  it('categorizes organization via name attribute', () => {
    expect(categorizeField({ type: 'text', name: 'company' })).toBe('organization');
    expect(categorizeField({ type: 'text', name: 'organization' })).toBe('organization');
    expect(categorizeField({ type: 'text', id: 'employer' })).toBe('organization');
  });

  it('categorizes website via name/id attribute', () => {
    expect(categorizeField({ type: 'text', name: 'website' })).toBe('website');
    expect(categorizeField({ type: 'text', id: 'homepage' })).toBe('website');
  });

  it('categorizes via placeholder when no name/id', () => {
    expect(categorizeField({ placeholder: 'Enter your email address' })).toBe('email');
    expect(categorizeField({ placeholder: 'Your phone number' })).toBe('phone');
    expect(categorizeField({ placeholder: 'Company name' })).toBe('organization');
  });

  it('categorizes via label text', () => {
    expect(categorizeField({ label: 'First Name' })).toBe('given_name');
    expect(categorizeField({ label: 'Last Name' })).toBe('family_name');
    expect(categorizeField({ label: 'Email Address' })).toBe('email');
    expect(categorizeField({ label: 'Phone Number' })).toBe('phone');
    expect(categorizeField({ label: 'Job Title' })).toBe('job_title');
    expect(categorizeField({ label: 'Company' })).toBe('organization');
    expect(categorizeField({ label: 'Website' })).toBe('website');
  });

  it('returns unknown for unrecognized fields', () => {
    expect(categorizeField({ type: 'text', name: 'coupon_code' })).toBe('unknown');
    expect(categorizeField({ type: 'text', name: 'message' })).toBe('unknown');
    expect(categorizeField({ type: 'text', name: 'subject' })).toBe('unknown');
  });

  it('autocomplete takes priority over name heuristics', () => {
    // Even if name says "company", autocomplete=name → full_name
    const result = categorizeField({ autocomplete: 'name', name: 'company' });
    expect(result).toBe('full_name');
  });
});

// ── mapFieldToCard ────────────────────────────────────────────────────────────

describe('mapFieldToCard', () => {
  const fullCard: AutofillCard = {
    full_name: 'Jane Doe',
    given_name: 'Jane',
    family_name: 'Doe',
    email: 'jane@example.com',
    phone: '+1-555-0100',
    organization: 'Acme Corp',
    job_title: 'Engineer',
    website: 'https://jane.example.com',
  };

  it('maps full_name correctly', () => {
    expect(mapFieldToCard('full_name', fullCard)).toBe('Jane Doe');
  });

  it('maps given_name correctly', () => {
    expect(mapFieldToCard('given_name', fullCard)).toBe('Jane');
  });

  it('maps family_name correctly', () => {
    expect(mapFieldToCard('family_name', fullCard)).toBe('Doe');
  });

  it('maps email correctly', () => {
    expect(mapFieldToCard('email', fullCard)).toBe('jane@example.com');
  });

  it('maps phone correctly', () => {
    expect(mapFieldToCard('phone', fullCard)).toBe('+1-555-0100');
  });

  it('maps organization correctly', () => {
    expect(mapFieldToCard('organization', fullCard)).toBe('Acme Corp');
  });

  it('maps job_title correctly', () => {
    expect(mapFieldToCard('job_title', fullCard)).toBe('Engineer');
  });

  it('maps website correctly', () => {
    expect(mapFieldToCard('website', fullCard)).toBe('https://jane.example.com');
  });

  it('returns null for address_line1 (not in AutofillCard)', () => {
    expect(mapFieldToCard('address_line1', fullCard)).toBeNull();
  });

  it('returns null for city', () => {
    expect(mapFieldToCard('city', fullCard)).toBeNull();
  });

  it('returns null for unknown', () => {
    expect(mapFieldToCard('unknown', fullCard)).toBeNull();
  });

  it('returns null when card field is not set', () => {
    const partialCard: AutofillCard = { email: 'hi@example.com' };
    expect(mapFieldToCard('full_name', partialCard)).toBeNull();
    expect(mapFieldToCard('phone', partialCard)).toBeNull();
    expect(mapFieldToCard('email', partialCard)).toBe('hi@example.com');
  });
});

// ── buildAutofillScript ───────────────────────────────────────────────────────

describe('buildAutofillScript', () => {
  it('returns a non-empty string', () => {
    const script = buildAutofillScript({ email: 'test@example.com' });
    expect(typeof script).toBe('string');
    expect(script.length).toBeGreaterThan(100);
  });

  it('embeds the card values in the script', () => {
    const card: AutofillCard = {
      full_name: 'Alice Smith',
      email: 'alice@example.com',
      phone: '+1234567890',
    };
    const script = buildAutofillScript(card);
    expect(script).toContain('Alice Smith');
    expect(script).toContain('alice@example.com');
    expect(script).toContain('+1234567890');
  });

  it('script is a valid IIFE (starts with (function)', () => {
    const script = buildAutofillScript({});
    expect(script.trim().startsWith('(function()')).toBe(true);
  });

  it('script ends with a closing IIFE pattern', () => {
    const script = buildAutofillScript({});
    expect(script.trim().endsWith(')()')).toBe(true);
  });

  it('embeds blocked types to prevent password/payment field filling', () => {
    const script = buildAutofillScript({});
    expect(script).toContain('password');
    expect(script).toContain('cc-number');
    expect(script).toContain('cc-csc');
  });

  it('handles an empty card without throwing', () => {
    expect(() => buildAutofillScript({})).not.toThrow();
  });

  it('handles special characters in values without breaking the script syntax', () => {
    const card: AutofillCard = {
      full_name: "O'Brien & Associates \"Ltd\"",
      email: 'test+tag@example.co.uk',
    };
    expect(() => buildAutofillScript(card)).not.toThrow();
    const script = buildAutofillScript(card);
    // JSON.stringify safely escapes double-quotes; single quotes are valid JSON and stay literal
    expect(script).toContain('\\"Ltd\\"');
    // The script must not contain a raw unescaped </script> sequence
    expect(script).not.toContain('</script>');
    // The single quote in O'Brien stays as-is (JSON does not escape ')
    expect(script).toContain("O'Brien");
  });

  it('dispatches input and change events in the script body', () => {
    const script = buildAutofillScript({ email: 'x@y.com' });
    expect(script).toContain("new Event('input'");
    expect(script).toContain("new Event('change'");
  });

  it('does not include auto-submit logic', () => {
    const script = buildAutofillScript({ email: 'x@y.com' });
    expect(script).not.toContain('.submit()');
    expect(script).not.toContain('.requestSubmit()');
  });
});

// ── Integration: categorize → map ────────────────────────────────────────────

describe('field categorization → card mapping pipeline', () => {
  const card: AutofillCard = {
    full_name: 'Bob Jones',
    given_name: 'Bob',
    family_name: 'Jones',
    email: 'bob@example.com',
    phone: '555-9876',
    organization: 'Example Inc',
    job_title: 'Designer',
    website: 'https://bob.io',
  };

  function pipelineResult(attrs: FieldAttributes): string | null {
    const kind: FieldKind = categorizeField(attrs);
    return mapFieldToCard(kind, card);
  }

  it('flows from autocomplete=email to card email', () => {
    expect(pipelineResult({ autocomplete: 'email' })).toBe('bob@example.com');
  });

  it('flows from type=email to card email', () => {
    expect(pipelineResult({ type: 'email' })).toBe('bob@example.com');
  });

  it('flows from autocomplete=given-name to given_name', () => {
    expect(pipelineResult({ autocomplete: 'given-name' })).toBe('Bob');
  });

  it('flows from name=phone to card phone', () => {
    expect(pipelineResult({ type: 'text', name: 'phone' })).toBe('555-9876');
  });

  it('flows from name=company to card organization', () => {
    expect(pipelineResult({ type: 'text', name: 'company' })).toBe('Example Inc');
  });

  it('flows from label=Website to card website', () => {
    expect(pipelineResult({ label: 'Website' })).toBe('https://bob.io');
  });

  it('returns null for unrecognized field', () => {
    expect(pipelineResult({ type: 'text', name: 'coupon' })).toBeNull();
  });

  it('returns null for excluded field (password skips pipeline)', () => {
    // shouldExcludeField is checked before categorize in the actual script;
    // but if somehow called, the kind would be 'unknown' and map returns null
    const kind = categorizeField({ type: 'password' });
    expect(mapFieldToCard(kind, card)).toBeNull();
  });
});
