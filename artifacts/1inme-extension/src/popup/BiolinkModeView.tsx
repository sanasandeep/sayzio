import React, { useState } from "react";

interface Props {
  tabUrl: string;
  tabTitle: string;
  onQuick: () => void;
  onAi: () => void;
  onCancel: () => void;
}

export function BiolinkModeView({ tabUrl, tabTitle, onQuick, onAi, onCancel }: Props) {
  const [mode, setMode] = useState<"quick" | "ai">("quick");

  return (
    <div className="body">
      <h3 className="section-h" style={{ marginBottom: 6 }}>Turn page into bio-link</h3>
      <p className="muted" style={{ fontSize: 12, marginBottom: 14 }}>
        {tabTitle && <strong style={{ display: "block", marginBottom: 4 }}>{tabTitle}</strong>}
        Choose how to create your bio-link page from this URL.
      </p>

      <div style={{ display: "flex", flexDirection: "column", gap: 8, marginBottom: 14 }}>
        <label style={{
          display: "flex", gap: 12, alignItems: "flex-start", cursor: "pointer",
          padding: "10px 12px", borderRadius: 10,
          border: `2px solid ${mode === "quick" ? "#3b82f6" : "rgba(255,255,255,.1)"}`,
          background: mode === "quick" ? "rgba(59,130,246,.07)" : "transparent",
          transition: "border-color .15s",
        }}>
          <input type="radio" name="mode" value="quick" checked={mode === "quick"}
            onChange={() => setMode("quick")} style={{ marginTop: 3 }} />
          <div>
            <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 3 }}>⚡ Quick (recommended)</div>
            <div className="muted" style={{ fontSize: 12 }}>
              Instantly extracts links, OG image, and page title and builds a bio-link draft you can
              refine in the editor. No AI credits used.
            </div>
          </div>
        </label>

        <label style={{
          display: "flex", gap: 12, alignItems: "flex-start", cursor: "pointer",
          padding: "10px 12px", borderRadius: 10,
          border: `2px solid ${mode === "ai" ? "#a855f7" : "rgba(255,255,255,.1)"}`,
          background: mode === "ai" ? "rgba(168,85,247,.07)" : "transparent",
          transition: "border-color .15s",
        }}>
          <input type="radio" name="mode" value="ai" checked={mode === "ai"}
            onChange={() => setMode("ai")} style={{ marginTop: 3 }} />
          <div>
            <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 3 }}>✨ AI-powered</div>
            <div className="muted" style={{ fontSize: 12 }}>
              Uses the AI Biolink Builder to generate a fully designed page with matched blocks,
              theme, and copy. Uses AI credits from your wallet.
            </div>
          </div>
        </label>
      </div>

      <div className="row" style={{ gap: 8 }}>
        <button className="btn-secondary" onClick={onCancel}>Cancel</button>
        <button
          className="btn-primary"
          onClick={mode === "quick" ? onQuick : onAi}
          style={mode === "ai" ? { background: "linear-gradient(135deg,#6d28d9,#a855f7)" } : {}}
        >
          {mode === "quick" ? "⚡ Quick build" : "✨ AI build"}
        </button>
      </div>
    </div>
  );
}
