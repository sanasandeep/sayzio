import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";

interface FormItem {
  id: number;
  title: string;
  alias?: string | null;
  short_url?: string | null;
  submissions_count?: number;
}

interface Props {
  webBaseUrl: string;
  onCancel: () => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

export function FormShareView({ webBaseUrl, onCancel, showToast }: Props) {
  const [forms, setForms] = useState<FormItem[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    api.getForms(30)
      .then((resp) => {
        if (!mountedRef.current) return;
        setForms(resp.items ?? []);
      })
      .catch((e: any) => {
        if (!mountedRef.current) return;
        setErr(e instanceof ApiError ? e.message : (e?.message || "Could not load forms"));
      });
    return () => { mountedRef.current = false; };
  }, []);

  const copy = async (form: FormItem) => {
    const url = form.short_url || (form.alias ? `${webBaseUrl}/${form.alias}` : null);
    if (!url) {
      showToast({ kind: "info", text: "This form has no short URL yet." });
      return;
    }
    try {
      await navigator.clipboard.writeText(url);
      setCopiedId(form.id);
      setTimeout(() => setCopiedId(null), 2000);
      showToast({ kind: "success", text: `Copied: ${url}` });
    } catch {
      showToast({ kind: "info", text: url });
    }
  };

  return (
    <div className="body">
      <div className="preview-header">
        <strong>📋 Form quick-share</strong>
        <button className="btn-link" onClick={onCancel}>← Back</button>
      </div>
      <div className="muted" style={{ marginBottom: 8, fontSize: 12 }}>
        Pick a form to copy its share link.
      </div>
      {err && <div className="error-text">{err}</div>}
      {!err && forms === null && <div className="muted">Loading forms…</div>}
      {!err && forms?.length === 0 && (
        <div className="muted">
          No forms yet.{" "}
          <a href={`${webBaseUrl}/dashboard/forms`} target="_blank" rel="noreferrer">Create one in Sayzio</a>.
        </div>
      )}
      {forms && forms.length > 0 && (
        <div className="recent-list">
          {forms.map((f) => {
            const url = f.short_url || (f.alias ? `${webBaseUrl}/${f.alias}` : null);
            return (
              <div key={f.id} className="recent-item" style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="alias" style={{ fontWeight: 500, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                    {f.title || `Form #${f.id}`}
                  </div>
                  {url && (
                    <div className="muted" style={{ fontSize: 10, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                      {url}
                    </div>
                  )}
                  {f.submissions_count !== undefined && (
                    <div className="muted" style={{ fontSize: 10 }}>
                      {f.submissions_count} submission{f.submissions_count === 1 ? "" : "s"}
                    </div>
                  )}
                </div>
                <button
                  className="btn-secondary btn-sm"
                  disabled={!url}
                  onClick={() => copy(f)}
                  title={url ?? "No URL"}
                >
                  {copiedId === f.id ? "Copied ✓" : "Copy link"}
                </button>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
