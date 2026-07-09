import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";
import type { ReviewCandidate } from "../content/review-detect";

interface Props {
  onCancel: () => void;
  onCaptured: () => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

export function ReviewCaptureView({ onCancel, onCaptured, showToast }: Props) {
  const [candidate, setCandidate] = useState<ReviewCandidate | null>(null);
  const [detecting, setDetecting] = useState(true);
  const [provider, setProvider] = useState<"google" | "trustpilot">("google");
  const [externalRef, setExternalRef] = useState("");
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [previewCaptured, setPreviewCaptured] = useState(false);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    chrome.tabs.query({ active: true, currentWindow: true }, async (tabs) => {
      const tabId = tabs[0]?.id;
      if (!tabId) { if (mountedRef.current) setDetecting(false); return; }
      try {
        const results = await chrome.scripting.executeScript({
          target: { tabId },
          files: ["content-review-detect.js"],
        });
        const c = results?.[0]?.result as ReviewCandidate | null;
        if (!mountedRef.current) return;
        if (c) {
          setCandidate(c);
          setProvider(c.provider);
          setExternalRef(c.externalRef);
          setName(c.name ?? "");
        }
      } catch { /* best-effort */ }
      finally { if (mountedRef.current) setDetecting(false); }
    });
    return () => { mountedRef.current = false; };
  }, []);

  const capture = async () => {
    if (!externalRef.trim()) { showToast({ kind: "error", text: "Business ID is required" }); return; }
    setBusy(true);
    try {
      const resp = await api.captureReviewSource(provider, externalRef.trim(), name.trim() || undefined);
      if (resp.preview) {
        // Keep the view open with an explicit preview-mode notice instead of
        // silently closing — a toast alone is too easy to miss.
        setPreviewCaptured(true);
      } else {
        showToast({ kind: "success", text: "Reviews are syncing in the background!" });
        onCaptured();
      }
    } catch (e: any) {
      showToast({ kind: "error", text: e instanceof ApiError ? e.message : "Could not capture reviews" });
    } finally {
      setBusy(false);
    }
  };

  if (previewCaptured) {
    return (
      <div className="body">
        <h3 className="section-h" style={{ marginBottom: 4 }}>Capture business reviews</h3>
        <div
          data-testid="review-preview-notice"
          style={{
            padding: "10px 12px", marginBottom: 12, borderRadius: 8,
            background: "rgba(245,158,11,.10)", border: "1px solid rgba(245,158,11,.35)",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 4 }}>
            <span style={{
              fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em",
              padding: "2px 6px", borderRadius: 999,
              background: "rgba(245,158,11,.2)", color: "#f59e0b",
            }}>Preview mode</span>
            <span style={{ fontWeight: 600, fontSize: 13 }}>Sample reviews only</span>
          </div>
          <p className="muted" style={{ fontSize: 12, margin: 0 }}>
            This source was connected, but the platform API keys
            ({provider === "google" ? "Google Places" : "Trustpilot"}) aren't configured yet,
            so sample reviews are shown instead of live ones. Real reviews will sync
            automatically once an admin adds the keys in the platform settings.
          </p>
        </div>
        <button className="btn-primary" onClick={onCaptured}>Got it</button>
      </div>
    );
  }

  return (
    <div className="body">
      <h3 className="section-h" style={{ marginBottom: 4 }}>Capture business reviews</h3>
      <p className="muted" style={{ fontSize: 12, marginBottom: 12 }}>
        Pull reviews from Google Maps or Trustpilot into your Sayzio Reviews wall.
      </p>

      {detecting && (
        <div className="muted" style={{ marginBottom: 8 }}>🔍 Detecting business on this page…</div>
      )}

      {!detecting && candidate && (
        <div style={{
          display: "flex", gap: 8, alignItems: "center",
          padding: "8px 10px", marginBottom: 10,
          borderRadius: 8, background: "rgba(34,197,94,.08)", border: "1px solid rgba(34,197,94,.25)",
        }}>
          {candidate.logoUrl && (
            <img src={candidate.logoUrl} alt="" width={32} height={32}
              style={{ borderRadius: 6, objectFit: "cover", flexShrink: 0 }} />
          )}
          <div>
            <div style={{ fontWeight: 600, fontSize: 13 }}>{candidate.name ?? "Business detected"}</div>
            <div style={{ fontSize: 11, opacity: .65 }}>
              {candidate.provider === "google" ? "Google Maps" : "Trustpilot"} · {candidate.externalRef}
            </div>
          </div>
        </div>
      )}

      {!detecting && !candidate && (
        <div className="muted" style={{ marginBottom: 10, fontSize: 12 }}>
          No business was auto-detected on this page. Fill in the details below manually.
        </div>
      )}

      <div className="field">
        <label>Provider</label>
        <select value={provider} onChange={(e) => setProvider(e.target.value as "google" | "trustpilot")}>
          <option value="google">Google (Maps / Business Profile)</option>
          <option value="trustpilot">Trustpilot</option>
        </select>
      </div>

      <div className="field">
        <label>
          {provider === "google" ? "Google Place ID or CID" : "Trustpilot business domain"}
        </label>
        <input
          value={externalRef}
          onChange={(e) => setExternalRef(e.target.value)}
          placeholder={provider === "google" ? "ChIJ..." : "example.com"}
        />
        <span className="muted" style={{ fontSize: 11 }}>
          {provider === "google"
            ? "Find it in the Google Maps URL (?place_id=…)"
            : "The part after trustpilot.com/review/"}
        </span>
      </div>

      <div className="field">
        <label>Business name (optional)</label>
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="My Business" />
      </div>

      <div className="row" style={{ gap: 8 }}>
        <button className="btn-secondary" onClick={onCancel} disabled={busy}>Cancel</button>
        <button className="btn-primary" onClick={capture} disabled={busy || !externalRef.trim()}>
          {busy && <span className="spinner" />}Capture reviews
        </button>
      </div>
    </div>
  );
}
