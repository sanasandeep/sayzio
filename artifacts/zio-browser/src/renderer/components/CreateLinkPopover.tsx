/**
 * CreateLinkPopover — "Create any link type" popover for the browser chrome.
 *
 * Supports all API-creatable link types with per-type minimal forms.
 * After creation:
 *  - navigates the active browser tab to the link's live public URL (right pane
 *    in split mode, main content in browser mode)
 *  - passes the URL to the onNavigate callback so the caller can drive the tab
 *
 * Types whose full editing needs the web app (biolink, conversational, slides,
 * ai_chat, resume, paid_page, social) are created with sensible defaults and
 * an "Open editor" affordance is shown in the success state.
 */
import { useState, useEffect, useCallback, useRef } from 'react';
import { forwardRef } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient } from '../../shared/api-client';
import type { ApiDomain, ApiLink, AliasCheckResult, CreateLinkPayload } from '../../shared/api-client';
import { suggestAlias } from '../../shared/link-tools';

// ── Type catalogue ────────────────────────────────────────────────────────────

interface LinkTypeMeta {
  type: string;
  label: string;
  icon: string;
  desc: string;
  needsEditor?: boolean;
  webOnly?: boolean;
}

const LINK_TYPES: LinkTypeMeta[] = [
  { type: 'short',         label: 'Short Link',    icon: '🔗', desc: 'Redirect to any URL' },
  { type: 'biolink',       label: 'Biolink',        icon: '👤', desc: 'Personal mini-website',     needsEditor: true },
  { type: 'qr',            label: 'QR Code',        icon: '⬛', desc: 'Scannable QR code' },
  { type: 'event',         label: 'Event',          icon: '📅', desc: 'Event page with RSVP' },
  { type: 'vcard',         label: 'vCard',          icon: '📇', desc: 'Digital business card' },
  { type: 'wifi',          label: 'WiFi',           icon: '📶', desc: 'Share WiFi credentials' },
  { type: 'sms',           label: 'SMS',            icon: '💬', desc: 'Pre-filled text message' },
  { type: 'pdf',           label: 'PDF',            icon: '📄', desc: 'Link to a PDF document' },
  { type: 'social',        label: 'Social',         icon: '🌐', desc: 'Social media profiles',     needsEditor: true },
  { type: 'file',          label: 'File',           icon: '📁', desc: 'File link',                 webOnly: true },
  { type: 'conversational',label: 'Conversational', icon: '🗣️', desc: 'Guided conversation',      needsEditor: true },
  { type: 'slides',        label: 'Slides',         icon: '🖼️', desc: 'Story slides',             needsEditor: true },
  { type: 'ai_chat',       label: 'AI Chat',        icon: '🤖', desc: 'AI companion page',         needsEditor: true },
  { type: 'resume',        label: 'Resume',         icon: '📋', desc: 'Shareable resume',          needsEditor: true },
  { type: 'paid_page',     label: 'Paid Page',      icon: '💰', desc: 'Gated content page',        needsEditor: true },
];

// ── Per-type field state ──────────────────────────────────────────────────────

interface FieldState {
  title: string;
  url: string;
  eventStart: string;
  eventEnd: string;
  vcardGivenName: string;
  vcardFamilyName: string;
  vcardEmail: string;
  vcardPhone: string;
  vcardOrg: string;
  wifiSsid: string;
  wifiPassword: string;
  wifiSecurity: 'WPA' | 'WEP' | '';
  smsPhone: string;
  smsBody: string;
  pdfUrl: string;
}

const defaultFields = (): FieldState => ({
  title: '',
  url: '',
  eventStart: '',
  eventEnd: '',
  vcardGivenName: '',
  vcardFamilyName: '',
  vcardEmail: '',
  vcardPhone: '',
  vcardOrg: '',
  wifiSsid: '',
  wifiPassword: '',
  wifiSecurity: 'WPA',
  smsPhone: '',
  smsBody: '',
  pdfUrl: '',
});

type PopoverView = 'types' | 'form' | 'success';

const BASE_URL = 'https://1in.me';

// ── Component ─────────────────────────────────────────────────────────────────

interface Props {
  pageUrl: string;
  pageTitle: string;
  baseUrl?: string;
  onClose: () => void;
  onOpenAuth: () => void;
  onNavigate?: (url: string) => void;
}

export function CreateLinkPopover({ pageUrl, pageTitle, baseUrl = BASE_URL, onClose, onOpenAuth, onNavigate }: Props) {
  const { token } = useAuthStore();
  const popoverRef = useRef<HTMLDivElement>(null);

  const [view, setView] = useState<PopoverView>('types');
  const [selectedType, setSelectedType] = useState<LinkTypeMeta | null>(null);

  const [fields, setFields] = useState<FieldState>(defaultFields);
  const [alias, setAlias] = useState('');
  const [domains, setDomains] = useState<ApiDomain[]>([]);
  const [selectedDomainId, setSelectedDomainId] = useState<number | null>(null);
  const [aliasCheck, setAliasCheck] = useState<AliasCheckResult | null>(null);
  const [checkingAlias, setCheckingAlias] = useState(false);

  const [createdLink, setCreatedLink] = useState<ApiLink | null>(null);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl, token });
  }, [token, baseUrl]);

  // Load available domains once
  useEffect(() => {
    if (!token) return;
    const client = getClient();
    if (!client) return;
    void client.listAvailableDomains().then(res => {
      setDomains(res.items);
      const primary = res.items.find(d => d.is_primary) ?? res.items[0];
      if (primary) setSelectedDomainId(primary.id);
    }).catch(() => { /* silent */ });
  }, [token, getClient]);

  // Debounced alias availability check
  useEffect(() => {
    if (!token || !alias) { setAliasCheck(null); return; }
    const client = getClient();
    if (!client) return;
    setCheckingAlias(true);
    const timer = setTimeout(() => {
      void client.checkAlias(alias).then(result => {
        setAliasCheck(result);
        setCheckingAlias(false);
      }).catch(() => { setCheckingAlias(false); });
    }, 400);
    return () => clearTimeout(timer);
  }, [alias, token, getClient]);

  // Close on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose]);

  const setField = useCallback(<K extends keyof FieldState>(key: K, value: FieldState[K]) => {
    setFields(prev => ({ ...prev, [key]: value }));
  }, []);

  const pickType = useCallback((meta: LinkTypeMeta) => {
    if (meta.webOnly) return;
    setSelectedType(meta);
    setFields(prev => ({
      ...defaultFields(),
      url: meta.type === 'short' ? pageUrl : '',
      title: meta.type === 'qr' ? pageTitle : prev.title,
    }));
    setAlias('');
    setAliasCheck(null);
    setError(null);
    setCreatedLink(null);
    setView('form');
  }, [pageUrl, pageTitle]);

  const buildPayload = useCallback(() => {
    if (!selectedType) return null;
    const { type } = selectedType;

    const base: Record<string, unknown> = {
      type: type === 'event' ? 'event' : type,
      alias: alias || undefined,
      domain_id: selectedDomainId || undefined,
    };

    switch (type) {
      case 'short':
        base.long_url = fields.url || pageUrl;
        base.title = fields.title || pageTitle || undefined;
        break;

      case 'biolink':
      case 'conversational':
      case 'slides':
      case 'ai_chat':
      case 'resume':
      case 'paid_page':
      case 'social':
        base.title = fields.title || undefined;
        break;

      case 'qr':
        base.title = fields.title || fields.url || pageTitle || undefined;
        base.long_url = fields.url || pageUrl;
        break;

      case 'file':
        base.title = fields.title || undefined;
        break;

      case 'event':
        base.title = fields.title || 'My Event';
        base.settings = {
          event: {
            start: fields.eventStart || new Date(Date.now() + 86400000).toISOString().slice(0, 16),
            end: fields.eventEnd || undefined,
          },
        };
        break;

      case 'vcard':
        base.title = `${fields.vcardGivenName} ${fields.vcardFamilyName}`.trim() || 'My vCard';
        base.settings = {
          vcard: {
            given_name: fields.vcardGivenName || undefined,
            family_name: fields.vcardFamilyName || undefined,
            organization: fields.vcardOrg || undefined,
            email: fields.vcardEmail || undefined,
            phone: fields.vcardPhone || undefined,
          },
        };
        break;

      case 'wifi':
        base.title = fields.wifiSsid ? `WiFi: ${fields.wifiSsid}` : 'WiFi';
        base.settings = {
          wifi: {
            ssid: fields.wifiSsid,
            password: fields.wifiPassword || undefined,
            security: fields.wifiSecurity || undefined,
          },
        };
        break;

      case 'sms': {
        const phone = fields.smsPhone.replace(/\s/g, '');
        const bodyPart = fields.smsBody ? `?body=${encodeURIComponent(fields.smsBody)}` : '';
        base.long_url = `sms:${phone}${bodyPart}`;
        base.title = `SMS to ${fields.smsPhone}`;
        break;
      }

      case 'pdf':
        base.long_url = fields.pdfUrl;
        base.title = fields.title || undefined;
        break;

      default:
        base.title = fields.title || undefined;
    }

    return base;
  }, [selectedType, alias, selectedDomainId, fields, pageUrl, pageTitle]);

  const validateForm = useCallback((): string | null => {
    if (!selectedType) return 'No type selected';
    const { type } = selectedType;

    if (type === 'short' && !fields.url && !pageUrl) return 'Destination URL is required';
    if (type === 'event' && !fields.title) return 'Event title is required';
    if (type === 'event' && !fields.eventStart) return 'Start date/time is required';
    if (type === 'vcard' && !fields.vcardGivenName && !fields.vcardFamilyName) return 'Name is required';
    if (type === 'wifi' && !fields.wifiSsid) return 'SSID (network name) is required';
    if (type === 'sms' && !fields.smsPhone) return 'Phone number is required';
    if (type === 'pdf' && !fields.pdfUrl) return 'PDF URL is required';
    if (type === 'qr' && !fields.url && !pageUrl) return 'Target URL is required';
    if (alias && aliasCheck && !aliasCheck.available) return aliasCheck.message;
    return null;
  }, [selectedType, fields, alias, aliasCheck, pageUrl]);

  const handleCreate = useCallback(async () => {
    if (!token) { onOpenAuth(); return; }
    const client = getClient();
    if (!client) return;

    const validationError = validateForm();
    if (validationError) { setError(validationError); return; }

    const payload = buildPayload();
    if (!payload) return;

    setCreating(true);
    setError(null);
    try {
      const res = await client.createLink(payload as unknown as CreateLinkPayload);
      setCreatedLink(res.link);
      setView('success');
      // Navigate the active browser tab to the live public URL
      if (res.link.short_url && onNavigate) {
        onNavigate(res.link.short_url);
      }
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to create link';
      setError(msg);
    } finally {
      setCreating(false);
    }
  }, [token, getClient, validateForm, buildPayload, onOpenAuth, onNavigate]);

  const handleCopy = useCallback(async (text: string) => {
    await window.zio.clipboard.write(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, []);

  const handleReset = useCallback(() => {
    setView('types');
    setSelectedType(null);
    setFields(defaultFields());
    setAlias('');
    setAliasCheck(null);
    setCreatedLink(null);
    setError(null);
  }, []);

  if (!token) {
    return (
      <PopoverShell ref={popoverRef} onClose={onClose}>
        <div style={{ padding: 20, textAlign: 'center' }}>
          <p style={{ fontSize: 13, marginBottom: 12 }}>Sign in to create links with Sayzio</p>
          <button onClick={() => { onClose(); onOpenAuth(); }} style={primaryBtn}>Sign in</button>
        </div>
      </PopoverShell>
    );
  }

  // ── Type picker ─────────────────────────────────────────────────────────────
  if (view === 'types') {
    return (
      <PopoverShell ref={popoverRef} onClose={onClose} wide>
        <div style={{ padding: '12px 14px 6px', borderBottom: '1px solid var(--color-border)' }}>
          <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--color-text)' }}>Create a link</div>
          <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2 }}>Choose a link type to get started</div>
        </div>
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(3, 1fr)',
          gap: 6,
          padding: 12,
          maxHeight: 360,
          overflowY: 'auto',
        }}>
          {LINK_TYPES.map(meta => (
            <button
              key={meta.type}
              onClick={() => pickType(meta)}
              disabled={meta.webOnly}
              title={meta.webOnly ? 'File upload requires the Sayzio web app' : meta.desc}
              style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: 4,
                padding: '10px 6px',
                borderRadius: 10,
                background: meta.webOnly ? 'transparent' : 'var(--color-bg-elevated)',
                border: `1px solid ${meta.webOnly ? 'var(--color-border)' : 'var(--color-border)'}`,
                cursor: meta.webOnly ? 'not-allowed' : 'pointer',
                opacity: meta.webOnly ? 0.35 : 1,
                transition: 'all 0.12s',
                textAlign: 'center',
              }}
            >
              <span style={{ fontSize: 20, lineHeight: 1 }}>{meta.icon}</span>
              <span style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text)', lineHeight: 1.2 }}>{meta.label}</span>
              <span style={{ fontSize: 9, color: 'var(--color-text-muted)', lineHeight: 1.2 }}>{meta.desc}</span>
            </button>
          ))}
        </div>
      </PopoverShell>
    );
  }

  // ── Success state ───────────────────────────────────────────────────────────
  if (view === 'success' && createdLink) {
    const meta = selectedType!;
    const editorUrl = `${baseUrl}/user/links`;
    return (
      <PopoverShell ref={popoverRef} onClose={onClose} wide>
        <div style={{ padding: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
            <span style={{ fontSize: 20 }}>{meta.icon}</span>
            <div>
              <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--color-text)' }}>{meta.label} created!</div>
              <div style={{ fontSize: 10, color: 'var(--color-text-muted)' }}>Live page loaded in browser</div>
            </div>
          </div>

          <div style={{ display: 'flex', gap: 6, alignItems: 'center', marginBottom: 10 }}>
            <input
              readOnly
              value={createdLink.short_url}
              style={{ ...inputStyle, flex: 1, background: 'var(--color-bg-elevated)', cursor: 'text', fontSize: 11 }}
            />
            <button
              onClick={() => void handleCopy(createdLink.short_url)}
              style={primaryBtn}
            >{copied ? '✓' : 'Copy'}</button>
          </div>

          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            {meta.needsEditor && (
              <button
                onClick={() => { onNavigate?.(editorUrl); onClose(); }}
                style={{ ...secondaryBtn, flex: 1, minWidth: 100 }}
              >✏️ Open editor</button>
            )}
            <button
              onClick={() => { onNavigate?.(createdLink.short_url); onClose(); }}
              style={{ ...secondaryBtn, flex: 1, minWidth: 100 }}
            >🌐 View live</button>
            <button
              onClick={handleReset}
              style={{ ...secondaryBtn, flex: 1, minWidth: 80 }}
            >+ New</button>
          </div>
        </div>
      </PopoverShell>
    );
  }

  // ── Form ────────────────────────────────────────────────────────────────────
  const meta = selectedType!;
  const aliasDisabled = creating || aliasCheck?.available === false;

  const suggestFromTitle = () => {
    const titleStr = fields.title ||
      (meta.type === 'vcard' ? `${fields.vcardGivenName} ${fields.vcardFamilyName}`.trim() : '') ||
      (meta.type === 'wifi' ? fields.wifiSsid : '') ||
      pageTitle;
    setAlias(suggestAlias(titleStr));
  };

  return (
    <PopoverShell ref={popoverRef} onClose={onClose} wide>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', borderBottom: '1px solid var(--color-border)' }}>
        <button
          onClick={() => setView('types')}
          style={{ fontSize: 14, color: 'var(--color-text-muted)', padding: '2px 4px' }}
        >←</button>
        <span style={{ fontSize: 16 }}>{meta.icon}</span>
        <div style={{ fontSize: 13, fontWeight: 700 }}>{meta.label}</div>
        {meta.needsEditor && (
          <span style={{
            fontSize: 9,
            padding: '2px 6px',
            borderRadius: 6,
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text-muted)',
            border: '1px solid var(--color-border)',
            marginLeft: 'auto',
          }}>Editor required</span>
        )}
      </div>

      <div style={{ padding: 14, display: 'flex', flexDirection: 'column', gap: 10, maxHeight: 440, overflowY: 'auto' }}>
        {/* ── Type-specific fields ─────────────────────────────────────────── */}

        {meta.type === 'short' && (
          <>
            <FormField label="Destination URL">
              <input
                value={fields.url}
                onChange={e => setField('url', e.target.value)}
                placeholder="https://example.com"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Title (optional)">
              <input
                value={fields.title}
                onChange={e => setField('title', e.target.value)}
                placeholder={pageTitle || 'Link title'}
                style={inputStyle}
              />
            </FormField>
          </>
        )}

        {(meta.type === 'biolink' || meta.type === 'conversational' || meta.type === 'slides' || meta.type === 'ai_chat' || meta.type === 'social') && (
          <>
            <FormField label="Title (optional)">
              <input
                value={fields.title}
                onChange={e => setField('title', e.target.value)}
                placeholder={`My ${meta.label}`}
                style={inputStyle}
              />
            </FormField>
            {meta.needsEditor && (
              <div style={{ fontSize: 10, color: 'var(--color-text-muted)', padding: '6px 8px', background: 'var(--color-bg-elevated)', borderRadius: 8, border: '1px solid var(--color-border)' }}>
                💡 A {meta.label} will be created with default content. Use the web editor to add and customise blocks.
              </div>
            )}
          </>
        )}

        {(meta.type === 'resume' || meta.type === 'paid_page') && (
          <>
            <FormField label="Title (optional)">
              <input
                value={fields.title}
                onChange={e => setField('title', e.target.value)}
                placeholder={`My ${meta.label}`}
                style={inputStyle}
              />
            </FormField>
            <div style={{ fontSize: 10, color: 'var(--color-text-muted)', padding: '6px 8px', background: 'var(--color-bg-elevated)', borderRadius: 8, border: '1px solid var(--color-border)' }}>
              💡 Created with default settings. Open the editor to complete your {meta.label}.
            </div>
          </>
        )}

        {meta.type === 'qr' && (
          <>
            <FormField label="Target URL">
              <input
                value={fields.url}
                onChange={e => setField('url', e.target.value)}
                placeholder={pageUrl || 'https://example.com'}
                style={inputStyle}
              />
            </FormField>
            <FormField label="Title (optional)">
              <input
                value={fields.title}
                onChange={e => setField('title', e.target.value)}
                placeholder={pageTitle || 'QR code title'}
                style={inputStyle}
              />
            </FormField>
          </>
        )}

        {meta.type === 'event' && (
          <>
            <FormField label="Event title *">
              <input
                value={fields.title}
                onChange={e => setField('title', e.target.value)}
                placeholder="My Event"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Start date & time *">
              <input
                type="datetime-local"
                value={fields.eventStart}
                onChange={e => setField('eventStart', e.target.value)}
                style={inputStyle}
              />
            </FormField>
            <FormField label="End date & time (optional)">
              <input
                type="datetime-local"
                value={fields.eventEnd}
                onChange={e => setField('eventEnd', e.target.value)}
                style={inputStyle}
              />
            </FormField>
          </>
        )}

        {meta.type === 'vcard' && (
          <>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
              <FormField label="First name *">
                <input
                  value={fields.vcardGivenName}
                  onChange={e => setField('vcardGivenName', e.target.value)}
                  placeholder="Jane"
                  style={inputStyle}
                />
              </FormField>
              <FormField label="Last name">
                <input
                  value={fields.vcardFamilyName}
                  onChange={e => setField('vcardFamilyName', e.target.value)}
                  placeholder="Smith"
                  style={inputStyle}
                />
              </FormField>
            </div>
            <FormField label="Organization">
              <input
                value={fields.vcardOrg}
                onChange={e => setField('vcardOrg', e.target.value)}
                placeholder="Acme Corp"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Email">
              <input
                type="email"
                value={fields.vcardEmail}
                onChange={e => setField('vcardEmail', e.target.value)}
                placeholder="jane@example.com"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Phone">
              <input
                type="tel"
                value={fields.vcardPhone}
                onChange={e => setField('vcardPhone', e.target.value)}
                placeholder="+1 555 000 0000"
                style={inputStyle}
              />
            </FormField>
          </>
        )}

        {meta.type === 'wifi' && (
          <>
            <FormField label="Network name (SSID) *">
              <input
                value={fields.wifiSsid}
                onChange={e => setField('wifiSsid', e.target.value)}
                placeholder="My WiFi Network"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Password">
              <input
                type="password"
                value={fields.wifiPassword}
                onChange={e => setField('wifiPassword', e.target.value)}
                placeholder="Network password"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Security">
              <select
                value={fields.wifiSecurity}
                onChange={e => setField('wifiSecurity', e.target.value as 'WPA' | 'WEP' | '')}
                style={inputStyle}
              >
                <option value="WPA">WPA / WPA2</option>
                <option value="WEP">WEP</option>
                <option value="">None / Open</option>
              </select>
            </FormField>
          </>
        )}

        {meta.type === 'sms' && (
          <>
            <FormField label="Phone number *">
              <input
                type="tel"
                value={fields.smsPhone}
                onChange={e => setField('smsPhone', e.target.value)}
                placeholder="+1 555 000 0000"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Pre-filled message (optional)">
              <textarea
                value={fields.smsBody}
                onChange={e => setField('smsBody', e.target.value)}
                placeholder="Hey! I wanted to reach out about…"
                rows={3}
                style={{ ...inputStyle, height: 'auto', padding: '6px 10px', resize: 'vertical' }}
              />
            </FormField>
          </>
        )}

        {meta.type === 'pdf' && (
          <>
            <FormField label="PDF URL *">
              <input
                value={fields.pdfUrl}
                onChange={e => setField('pdfUrl', e.target.value)}
                placeholder="https://example.com/document.pdf"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Title (optional)">
              <input
                value={fields.title}
                onChange={e => setField('title', e.target.value)}
                placeholder="Document title"
                style={inputStyle}
              />
            </FormField>
          </>
        )}

        {/* ── Shared: domain selector ─────────────────────────────────────── */}
        {domains.length > 0 && (
          <FormField label="Domain">
            <select
              value={selectedDomainId ?? ''}
              onChange={e => setSelectedDomainId(Number(e.target.value) || null)}
              style={inputStyle}
            >
              {domains.map(d => (
                <option key={d.id} value={d.id}>
                  {d.host}{d.is_primary ? ' (default)' : ''}
                </option>
              ))}
            </select>
          </FormField>
        )}

        {/* ── Shared: alias field ─────────────────────────────────────────── */}
        <FormField label="Custom alias (optional)">
          <div style={{ display: 'flex', gap: 6 }}>
            <input
              value={alias}
              onChange={e => setAlias(e.target.value)}
              placeholder="leave empty to auto-generate"
              style={{
                ...inputStyle,
                flex: 1,
                borderColor: aliasCheck
                  ? aliasCheck.available ? 'var(--color-success, #22c55e)' : 'var(--color-danger, #ef4444)'
                  : 'var(--color-border)',
              }}
            />
            <button
              onClick={suggestFromTitle}
              title="Suggest alias from title"
              style={{ ...secondaryBtn, padding: '0 8px', fontSize: 11, flexShrink: 0 }}
            >Suggest</button>
          </div>
          {alias && (
            <p style={{ fontSize: 10, marginTop: 3, color: aliasCheck?.available ? 'var(--color-success, #22c55e)' : 'var(--color-danger, #ef4444)' }}>
              {checkingAlias ? 'Checking…' : (aliasCheck?.message ?? '')}
            </p>
          )}
          {alias && !checkingAlias && aliasCheck?.available === false && (aliasCheck.suggestions?.length ?? 0) > 0 && (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginTop: 4 }}>
              {aliasCheck.suggestions!.map(s => (
                <button
                  key={s}
                  type="button"
                  onClick={() => { setAlias(s); setAliasCheck(null); }}
                  style={{ ...secondaryBtn, padding: '2px 8px', fontSize: 10, borderRadius: 999 }}
                >{s}</button>
              ))}
            </div>
          )}
        </FormField>

        {/* ── Error + submit ──────────────────────────────────────────────── */}
        {error && (
          <p style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)', wordBreak: 'break-word' }}>{error}</p>
        )}

        <button
          onClick={() => void handleCreate()}
          disabled={creating || aliasDisabled}
          style={{ ...primaryBtn, width: '100%', opacity: creating || aliasDisabled ? 0.5 : 1, padding: '8px 14px' }}
        >{creating ? 'Creating…' : `Create ${meta.label}`}</button>
      </div>
    </PopoverShell>
  );
}

// ── Sub-components ─────────────────────────────────────────────────────────────

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label style={labelStyle}>{label}</label>
      {children}
    </div>
  );
}

const PopoverShell = forwardRef<HTMLDivElement, { children: React.ReactNode; onClose: () => void; wide?: boolean }>(
  ({ children, wide = false }, ref) => (
    <div
      ref={ref}
      style={{
        position: 'absolute',
        top: 'calc(var(--chrome-height) + 4px)',
        left: '50%',
        transform: 'translateX(-50%)',
        width: wide ? 420 : 340,
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 12,
        boxShadow: '0 8px 32px rgba(0,0,0,0.28)',
        zIndex: 1000,
        overflow: 'hidden',
      }}
    >
      {children}
    </div>
  )
);
PopoverShell.displayName = 'PopoverShell';

// ── Micro styles ──────────────────────────────────────────────────────────────

const inputStyle: React.CSSProperties = {
  width: '100%',
  height: 30,
  borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
  color: 'var(--color-text)',
  padding: '0 10px',
  fontSize: 12,
  outline: 'none',
  boxSizing: 'border-box',
};

const primaryBtn: React.CSSProperties = {
  padding: '6px 14px',
  borderRadius: 8,
  background: 'var(--gradient-primary)',
  color: '#fff',
  fontSize: 12,
  fontWeight: 600,
  cursor: 'pointer',
  whiteSpace: 'nowrap',
};

const secondaryBtn: React.CSSProperties = {
  padding: '6px 14px',
  borderRadius: 8,
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  border: '1px solid var(--color-border)',
  fontSize: 12,
  cursor: 'pointer',
  whiteSpace: 'nowrap',
};

const labelStyle: React.CSSProperties = {
  display: 'block',
  fontSize: 10,
  fontWeight: 600,
  color: 'var(--color-text-muted)',
  textTransform: 'uppercase',
  letterSpacing: '0.05em',
  marginBottom: 4,
};
