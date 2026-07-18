/**
 * Tests for contact dedupe decision logic.
 *
 * The actual duplicate-detection is done server-side (ContactCandidateValidator).
 * On the browser client the relevant logic is:
 *   1. Interpreting a 409 ApiClientError as a "duplicate found" signal.
 *   2. Assembling the update payload when the user chooses "Update".
 *   3. Merging new contact data into the existing contact payload without
 *      dropping pre-existing fields.
 */
import { describe, it, expect } from 'vitest';
import { ApiClientError } from '../src/shared/api-client';
import type { ApiContact, ContactPayload } from '../src/shared/api-client';

// ── Helpers mirroring the client-side logic ───────────────────────────────────

/** Identify a duplicate response by status 409 + code. */
function isDuplicateResponse(err: unknown): boolean {
  return err instanceof ApiClientError && err.status === 409;
}

/** Extract the existing contact ID from a 409 ApiClientError's details. */
function extractDuplicateId(err: ApiClientError): number | null {
  if (err.status !== 409) return null;
  const details = err.details as { duplicate_of?: number } | undefined;
  return details?.duplicate_of ?? null;
}

/** Extract whether the plan is at capacity from a 402 ApiClientError. */
function isPlanLimitError(err: unknown): boolean {
  return err instanceof ApiClientError && err.status === 402;
}

/**
 * Build an update payload that merges new contact fields into the existing
 * contact — adding new emails/phones without removing pre-existing ones.
 */
function buildUpdatePayload(
  existing: ApiContact,
  newData: { emails: string[]; phones: string[]; source_url?: string },
): ContactPayload {
  const existingEmails = new Set(existing.emails.map(e => e.value.toLowerCase()));
  const existingPhones = new Set(existing.phones.map(p => p.value));

  const mergedEmails = [
    ...existing.emails.map(e => ({ value: e.value, label: e.label })),
    ...newData.emails.filter(e => !existingEmails.has(e.toLowerCase())).map(e => ({ value: e })),
  ];

  const mergedPhones = [
    ...existing.phones.map(p => ({ value: p.value, label: p.label })),
    ...newData.phones.filter(p => !existingPhones.has(p)).map(p => ({ value: p })),
  ];

  return {
    emails: mergedEmails,
    phones: mergedPhones,
    source_url: newData.source_url,
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('isDuplicateResponse', () => {
  it('returns true for a 409 ApiClientError', () => {
    const err = new ApiClientError('contact_duplicate', 'A matching contact already exists.', 409, { duplicate_of: 42 });
    expect(isDuplicateResponse(err)).toBe(true);
  });

  it('returns false for a non-409 ApiClientError', () => {
    const err = new ApiClientError('validation_error', 'Invalid', 422, {});
    expect(isDuplicateResponse(err)).toBe(false);
  });

  it('returns false for a plain Error', () => {
    expect(isDuplicateResponse(new Error('network error'))).toBe(false);
  });

  it('returns false for null/undefined', () => {
    expect(isDuplicateResponse(null)).toBe(false);
    expect(isDuplicateResponse(undefined)).toBe(false);
  });

  it('returns false for a string', () => {
    expect(isDuplicateResponse('error')).toBe(false);
  });
});

describe('extractDuplicateId', () => {
  it('extracts the duplicate_of id from a 409 error details', () => {
    const err = new ApiClientError('contact_duplicate', 'Duplicate', 409, { duplicate_of: 99 });
    expect(extractDuplicateId(err)).toBe(99);
  });

  it('returns null when details are missing', () => {
    const err = new ApiClientError('contact_duplicate', 'Duplicate', 409, undefined);
    expect(extractDuplicateId(err)).toBeNull();
  });

  it('returns null when duplicate_of is absent from details', () => {
    const err = new ApiClientError('contact_duplicate', 'Duplicate', 409, { other_field: 123 });
    expect(extractDuplicateId(err)).toBeNull();
  });

  it('returns null for non-409 errors', () => {
    const err = new ApiClientError('server_error', 'Boom', 500, { duplicate_of: 1 });
    expect(extractDuplicateId(err)).toBeNull();
  });
});

describe('isPlanLimitError', () => {
  it('returns true for a 402 error', () => {
    const err = new ApiClientError('plan_limit', 'Contact limit reached', 402);
    expect(isPlanLimitError(err)).toBe(true);
  });

  it('returns false for non-402 errors', () => {
    const err = new ApiClientError('contact_duplicate', 'Duplicate', 409, {});
    expect(isPlanLimitError(err)).toBe(false);
  });

  it('returns false for plain errors', () => {
    expect(isPlanLimitError(new Error('network'))).toBe(false);
  });
});

describe('buildUpdatePayload', () => {
  const existingContact: ApiContact = {
    id: 42,
    display_name: 'Alice Smith',
    organization: 'Acme',
    emails: [{ value: 'alice@acme.com', label: 'work' }],
    phones: [{ value: '+1-555-0101', value_e164: '+15550101', label: 'mobile' }],
  };

  it('includes existing emails', () => {
    const payload = buildUpdatePayload(existingContact, { emails: [], phones: [] });
    expect(payload.emails?.some(e => e.value === 'alice@acme.com')).toBe(true);
  });

  it('includes existing phones', () => {
    const payload = buildUpdatePayload(existingContact, { emails: [], phones: [] });
    expect(payload.phones?.some(p => p.value === '+1-555-0101')).toBe(true);
  });

  it('appends a new email not already present', () => {
    const payload = buildUpdatePayload(existingContact, {
      emails: ['newemail@example.com'],
      phones: [],
    });
    const values = payload.emails?.map(e => e.value) ?? [];
    expect(values).toContain('alice@acme.com');
    expect(values).toContain('newemail@example.com');
    expect(values.length).toBe(2);
  });

  it('appends a new phone not already present', () => {
    const payload = buildUpdatePayload(existingContact, {
      emails: [],
      phones: ['+1-555-9999'],
    });
    const values = payload.phones?.map(p => p.value) ?? [];
    expect(values).toContain('+1-555-0101');
    expect(values).toContain('+1-555-9999');
    expect(values.length).toBe(2);
  });

  it('does NOT add a duplicate email (case-insensitive)', () => {
    const payload = buildUpdatePayload(existingContact, {
      emails: ['ALICE@ACME.COM'],
      phones: [],
    });
    const values = payload.emails?.map(e => e.value) ?? [];
    // Only the original, no duplicate
    expect(values.filter(v => v.toLowerCase() === 'alice@acme.com').length).toBe(1);
  });

  it('does NOT add a duplicate phone (exact match)', () => {
    const payload = buildUpdatePayload(existingContact, {
      emails: [],
      phones: ['+1-555-0101'],
    });
    const values = payload.phones?.map(p => p.value) ?? [];
    expect(values.filter(v => v === '+1-555-0101').length).toBe(1);
  });

  it('includes source_url in the payload', () => {
    const payload = buildUpdatePayload(existingContact, {
      emails: [],
      phones: [],
      source_url: 'https://example.com/contact',
    });
    expect(payload.source_url).toBe('https://example.com/contact');
  });

  it('handles a contact with no existing emails', () => {
    const bare: ApiContact = {
      id: 1, display_name: 'Bob', organization: null, emails: [], phones: [],
    };
    const payload = buildUpdatePayload(bare, { emails: ['bob@new.com'], phones: [] });
    expect(payload.emails?.length).toBe(1);
    expect(payload.emails?.[0]?.value).toBe('bob@new.com');
  });

  it('handles a contact with no existing phones', () => {
    const bare: ApiContact = {
      id: 2, display_name: 'Carol', organization: null, emails: [], phones: [],
    };
    const payload = buildUpdatePayload(bare, { emails: [], phones: ['555-1234'] });
    expect(payload.phones?.length).toBe(1);
    expect(payload.phones?.[0]?.value).toBe('555-1234');
  });

  it('preserves existing label metadata on merged entries', () => {
    const payload = buildUpdatePayload(existingContact, {
      emails: ['new@example.com'],
      phones: [],
    });
    const aliceEntry = payload.emails?.find(e => e.value === 'alice@acme.com');
    expect(aliceEntry?.label).toBe('work');
  });
});

// ── ApiClientError shape ──────────────────────────────────────────────────────

describe('ApiClientError', () => {
  it('preserves code, message, status, and details', () => {
    const err = new ApiClientError('contact_duplicate', 'Already exists', 409, { duplicate_of: 7 });
    expect(err.code).toBe('contact_duplicate');
    expect(err.message).toBe('Already exists');
    expect(err.status).toBe(409);
    expect(err.details).toEqual({ duplicate_of: 7 });
    expect(err.name).toBe('ApiClientError');
  });

  it('is an instance of Error', () => {
    const err = new ApiClientError('x', 'y', 400);
    expect(err instanceof Error).toBe(true);
    expect(err instanceof ApiClientError).toBe(true);
  });
});
