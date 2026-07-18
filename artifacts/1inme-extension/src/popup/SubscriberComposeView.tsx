import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";
import { browser } from "../lib/browser";
import { ExtSettings } from "../lib/storage";

interface Props {
  tabUrl: string;
  tabTitle: string;
  settings: ExtSettings;
  onCancel: () => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

export function SubscriberComposeView({ tabUrl, tabTitle, settings, onCancel, showToast }: Props) {
  const [shortUrl, setShortUrl] = useState<string | null>(null);
  const [shorteningError, setShorteningError] = useState<string | null>(null);
  const [subject, setSubject] = useState(tabTitle ? tabTitle.slice(0, 120) : "");
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    if (!tabUrl) return;
    (async () => {
      try {
        const r = await api.createShortLink(tabUrl, tabTitle, settings.workspaceId);
        if (!mountedRef.current) return;
        const url = r.link.short_url || `${settings.webBaseUrl}/${r.link.alias}`;
        setShortUrl(url);
        if (!body) setBody(`${tabTitle ? tabTitle + "\n\n" : ""}${url}`);
      } catch (e: any) {
        if (!mountedRef.current) return;
        setShorteningError(e instanceof ApiError ? e.message : "Could not shorten page URL");
        setShortUrl(tabUrl);
        if (!body) setBody(`${tabTitle ? tabTitle + "\n\n" : ""}${tabUrl}`);
      }
    })();
    return () => { mountedRef.current = false; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const openWebCompose = () => {
    const params = new URLSearchParams();
    if (subject) params.set("subject", subject);
    if (body) params.set("body", body.slice(0, 2000));
    const url = `${settings.webBaseUrl}/dashboard/subscribers/broadcast?${params}`;
    browser.tabs.create({ url });
  };

  const copyLink = async () => {
    const url = shortUrl || tabUrl;
    if (!url) return;
    try {
      await navigator.clipboard.writeText(url);
      showToast({ kind: "success", text: `Copied: ${url}` });
    } catch {
      showToast({ kind: "info", text: url });
    }
  };

  return (
    <div className="body">
      <div className="preview-header">
        <strong>📣 Send to subscribers</strong>
        <button className="btn-link" onClick={onCancel}>← Back</button>
      </div>

      <div className="muted" style={{ marginBottom: 8, fontSize: 12 }}>
        Compose a broadcast email/WhatsApp message to your subscribers. A short link to this page will be included.
      </div>

      {shorteningError && (
        <div className="muted" style={{ color: "var(--warn, #f59e0b)", fontSize: 11, marginBottom: 6 }}>
          ⚠ {shorteningError} — using original URL.
        </div>
      )}

      <div className="field">
        <label>Short link</label>
        <div style={{ display: "flex", gap: 6, alignItems: "center" }}>
          <div className="url-card" style={{ flex: 1, fontSize: 11, wordBreak: "break-all" }}>
            {shortUrl ?? "Shortening…"}
          </div>
          <button className="btn-secondary btn-sm" onClick={copyLink} disabled={!shortUrl}>Copy</button>
        </div>
      </div>

      <div className="field">
        <label>Subject</label>
        <input
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
          placeholder="Your broadcast subject"
          maxLength={200}
        />
      </div>

      <div className="field">
        <label>Message preview</label>
        <textarea
          rows={4}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          placeholder="Enter your broadcast message…"
          style={{ width: "100%", boxSizing: "border-box" }}
        />
        <div className="muted" style={{ fontSize: 10 }}>
          Tip: the short link is already included. Edit freely before opening the composer.
        </div>
      </div>

      <button
        className="btn-primary"
        disabled={busy || !shortUrl}
        onClick={() => { setBusy(true); openWebCompose(); setBusy(false); }}
      >
        {busy && <span className="spinner" />}Open in Sayzio composer ↗
      </button>
      <div className="muted" style={{ fontSize: 11, marginTop: 4 }}>
        Opens your Sayzio dashboard with this message pre-filled so you can review, choose channels, and send.
      </div>
    </div>
  );
}
