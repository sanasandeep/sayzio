import React, { useEffect, useRef, useState } from "react";
import { browser } from "../lib/browser";
import { api, ApiError, MailboxDraftResult } from "../lib/api";
import type { MailboxThread, ExtractResult } from "../content/mailbox-extract";

interface Props {
  tabId: number | null;
  webBaseUrl: string;
  onCancel: () => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string; link?: { href: string; label: string } }) => void;
}

type KnowledgeBase = { id: number; name: string };

type UiState =
  | { phase: "detecting" }
  | { phase: "unsupported"; message: string }
  | { phase: "ready"; thread: MailboxThread }
  | { phase: "generating" }
  | { phase: "done"; thread: MailboxThread; result: MailboxDraftResult }
  | { phase: "inserting" };

export function MailboxReplyView({ tabId, webBaseUrl, onCancel, showToast }: Props) {
  const [uiState, setUiState] = useState<UiState>({ phase: "detecting" });
  const [kbList, setKbList] = useState<KnowledgeBase[]>([]);
  const [selectedKbIds, setSelectedKbIds] = useState<number[]>([]);
  const [includeLinks, setIncludeLinks] = useState<boolean>(true);
  const [instruction, setInstruction] = useState("");
  const mountedRef = useRef(true);

  // Persist KB + links preferences across sessions via extension storage.
  useEffect(() => {
    browser.storage.local.get(["mailboxKbIds", "mailboxIncludeLinks"]).then((res: any) => {
      if (!mountedRef.current) return;
      if (Array.isArray(res?.mailboxKbIds)) setSelectedKbIds(res.mailboxKbIds);
      if (typeof res?.mailboxIncludeLinks === "boolean") setIncludeLinks(res.mailboxIncludeLinks);
    });
    return () => { mountedRef.current = false; };
  }, []);

  // Extract thread from the active tab on mount.
  useEffect(() => {
    if (!tabId) {
      setUiState({ phase: "unsupported", message: "No active tab." });
      return;
    }
    (async () => {
      try {
        const results = await browser.scripting.executeScript({
          target: { tabId },
          files: ["content-mailbox-extract.js"],
        });
        if (!mountedRef.current) return;
        const res = results?.[0]?.result as ExtractResult | null;
        if (!res) {
          setUiState({ phase: "unsupported", message: "Could not run on this page." });
          return;
        }
        if (!res.ok) {
          setUiState({ phase: "unsupported", message: res.error });
          return;
        }
        setUiState({ phase: "ready", thread: res.thread });
      } catch (e: any) {
        if (!mountedRef.current) return;
        setUiState({ phase: "unsupported", message: e?.message || "Could not access this tab." });
      }
    })();
  }, [tabId]);

  // Fetch knowledge bases in the background (best-effort; don't block the UI).
  useEffect(() => {
    api.listKnowledgeBases().then((r) => {
      if (!mountedRef.current) return;
      setKbList(r.mine ?? []);
    }).catch(() => undefined);
  }, []);

  const savePrefs = (kbIds: number[], links: boolean) => {
    browser.storage.local.set({ mailboxKbIds: kbIds, mailboxIncludeLinks: links });
  };

  const handleGenerate = async (thread: MailboxThread, regenerateInstruction = "") => {
    setUiState({ phase: "generating" });
    try {
      const result = await api.draftMailboxReply({
        thread: {
          subject: thread.subject,
          participants: thread.participants,
          messages: thread.messages,
        },
        knowledge_base_ids: selectedKbIds.length > 0 ? selectedKbIds : undefined,
        include_links: includeLinks,
        instruction: regenerateInstruction || undefined,
      });
      if (mountedRef.current) {
        setUiState({ phase: "done", thread, result });
      }
    } catch (e: any) {
      if (!mountedRef.current) return;
      if (e instanceof ApiError && e.status === 402) {
        const details = (e.payload as any)?.error?.details ?? (e.payload as any)?.data ?? {};
        const topupUrl = details.topup_url || `${webBaseUrl}/user/upgrade`;
        showToast({
          kind: "error",
          text: `Not enough coins to generate this draft.`,
          link: { href: topupUrl, label: "Top up coins" },
        });
        // Go back to ready so the user can try again
        const prev = uiState;
        setUiState("thread" in prev ? { phase: "ready", thread: prev.thread as MailboxThread } : { phase: "detecting" });
      } else if (e instanceof ApiError && e.status === 403) {
        showToast({ kind: "error", text: "AI features are not enabled on this account." });
        const prev = uiState;
        setUiState("thread" in prev ? { phase: "ready", thread: prev.thread as MailboxThread } : { phase: "detecting" });
      } else {
        showToast({ kind: "error", text: e?.message || "Draft generation failed." });
        const prev = uiState;
        setUiState("thread" in prev ? { phase: "ready", thread: prev.thread as MailboxThread } : { phase: "detecting" });
      }
    }
  };

  const handleCopy = (draft: string) => {
    navigator.clipboard?.writeText(draft).then(() => {
      showToast({ kind: "success", text: "Draft copied to clipboard!" });
    }).catch(() => {
      showToast({ kind: "info", text: "Could not auto-copy. Select and copy the text manually." });
    });
  };

  const handleInsert = async (draft: string) => {
    if (!tabId) {
      showToast({ kind: "error", text: "No active tab." });
      return;
    }
    setUiState((s) => ({ ...s, phase: "inserting" } as UiState));
    try {
      const results = await browser.scripting.executeScript({
        target: { tabId },
        func: insertDraftIntoMailbox,
        args: [draft],
      });
      const res = results?.[0]?.result as { ok: boolean; error?: string } | null;
      if (res?.ok) {
        showToast({ kind: "success", text: "Draft inserted into the reply box!" });
        onCancel();
      } else {
        showToast({
          kind: "info",
          text: res?.error || "Could not insert automatically. Copy and paste the draft instead.",
        });
        if (mountedRef.current) {
          setUiState((s) => {
            const prev = s as any;
            return prev.result ? { phase: "done", thread: prev.thread, result: prev.result } : s;
          });
        }
      }
    } catch (e: any) {
      showToast({ kind: "info", text: "Could not insert automatically. Copy and paste the draft instead." });
      if (mountedRef.current) {
        setUiState((s) => {
          const prev = s as any;
          return prev.result ? { phase: "done", thread: prev.thread, result: prev.result } : s;
        });
      }
    }
  };

  // ── Render ────────────────────────────────────────────────────────────

  if (uiState.phase === "detecting") {
    return (
      <div className="body">
        <h3 className="section-h" style={{ marginBottom: 4 }}>Draft AI reply</h3>
        <div className="muted" style={{ marginTop: 16 }}>🔍 Reading the open email thread…</div>
        <div style={{ marginTop: 16 }}>
          <button className="btn-secondary" onClick={onCancel}>Cancel</button>
        </div>
      </div>
    );
  }

  if (uiState.phase === "unsupported") {
    return (
      <div className="body">
        <h3 className="section-h" style={{ marginBottom: 4 }}>Draft AI reply</h3>
        <div style={{
          padding: "10px 12px", borderRadius: 8, marginBottom: 12,
          background: "rgba(239,68,68,.08)", border: "1px solid rgba(239,68,68,.25)",
          fontSize: 13,
        }}>
          {uiState.message}
        </div>
        <p className="muted" style={{ fontSize: 12 }}>
          Open a Gmail or Outlook web thread, then try again.
        </p>
        <button className="btn-secondary" onClick={onCancel}>Close</button>
      </div>
    );
  }

  if (uiState.phase === "ready" || uiState.phase === "generating") {
    const thread = uiState.phase === "ready" ? uiState.thread : null;
    const generating = uiState.phase === "generating";
    return (
      <div className="body">
        <h3 className="section-h" style={{ marginBottom: 4 }}>Draft AI reply</h3>

        {thread && (
          <div style={{
            padding: "8px 10px", marginBottom: 10, borderRadius: 8,
            background: "rgba(92,131,255,.08)", border: "1px solid rgba(92,131,255,.2)",
            fontSize: 12,
          }}>
            <div style={{ fontWeight: 600, fontSize: 13 }}>
              {thread.provider === "gmail" ? "✉ Gmail" : "✉ Outlook"} · {thread.messages.length} message{thread.messages.length !== 1 ? "s" : ""}
            </div>
            <div className="muted" style={{ marginTop: 2 }} title={thread.subject}>
              {thread.subject.length > 55 ? thread.subject.slice(0, 52) + "…" : thread.subject}
            </div>
          </div>
        )}

        {kbList.length > 0 && (
          <div className="field">
            <label>Knowledge Bases (optional)</label>
            <div style={{ display: "flex", flexDirection: "column", gap: 4 }}>
              {kbList.map((kb) => (
                <label key={kb.id} className="toggle-row" style={{ fontSize: 12 }}>
                  <input
                    type="checkbox"
                    checked={selectedKbIds.includes(kb.id)}
                    onChange={(e) => {
                      const next = e.target.checked
                        ? [...selectedKbIds, kb.id]
                        : selectedKbIds.filter((id) => id !== kb.id);
                      setSelectedKbIds(next);
                      savePrefs(next, includeLinks);
                    }}
                  />
                  <span>{kb.name}</span>
                </label>
              ))}
            </div>
          </div>
        )}

        <label className="toggle-row" style={{ marginBottom: 8 }}>
          <input
            type="checkbox"
            checked={includeLinks}
            onChange={(e) => {
              setIncludeLinks(e.target.checked);
              savePrefs(selectedKbIds, e.target.checked);
            }}
          />
          <span style={{ fontSize: 12 }}>Include my Sayzio links</span>
        </label>

        <div className="row" style={{ gap: 8 }}>
          <button className="btn-secondary" onClick={onCancel} disabled={generating}>Cancel</button>
          <button
            className="btn-primary"
            disabled={generating || !thread}
            onClick={() => thread && handleGenerate(thread)}
          >
            {generating && <span className="spinner" />}
            {generating ? "Generating…" : "Generate draft"}
          </button>
        </div>
      </div>
    );
  }

  if (uiState.phase === "done" || uiState.phase === "inserting") {
    const { thread, result } = uiState.phase === "done"
      ? uiState
      : (uiState as any) as { thread: MailboxThread; result: MailboxDraftResult };
    const inserting = uiState.phase === "inserting";

    return (
      <div className="body">
        <h3 className="section-h" style={{ marginBottom: 4 }}>Draft AI reply</h3>

        <div style={{
          padding: "10px 12px", marginBottom: 10, borderRadius: 8,
          background: "rgba(15,23,42,.4)", border: "1px solid rgba(92,131,255,.2)",
          fontSize: 12, whiteSpace: "pre-wrap", maxHeight: 220, overflowY: "auto",
          lineHeight: 1.5,
        }}>
          {result.draft || <span className="muted">(empty draft)</span>}
        </div>

        {result.citations.length > 0 && (
          <div style={{ marginBottom: 8, fontSize: 11, color: "rgba(147,164,201,.8)" }}>
            Sources: {result.citations.map((c) => c.name).join(", ")}
          </div>
        )}

        {result.credits_spent > 0 && (
          <div style={{ marginBottom: 8, fontSize: 11, color: "rgba(147,164,201,.6)" }}>
            {result.credits_spent} coin{result.credits_spent !== 1 ? "s" : ""} used
          </div>
        )}

        <div className="field">
          <label>Adjust (optional)</label>
          <input
            value={instruction}
            onChange={(e) => setInstruction(e.target.value)}
            placeholder="e.g. shorter, more formal, add pricing…"
            disabled={inserting}
          />
        </div>

        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
          <button
            className="btn-secondary"
            style={{ flex: "1 1 auto", fontSize: 12 }}
            disabled={inserting}
            onClick={() => handleGenerate(thread, instruction)}
          >
            ↺ Regenerate
          </button>
          <button
            className="btn-secondary"
            style={{ flex: "1 1 auto", fontSize: 12 }}
            disabled={inserting}
            onClick={() => handleCopy(result.draft)}
          >
            📋 Copy
          </button>
          <button
            className="btn-primary"
            style={{ flex: "1 1 auto", fontSize: 12 }}
            disabled={inserting}
            onClick={() => handleInsert(result.draft)}
          >
            {inserting && <span className="spinner" />}
            {inserting ? "Inserting…" : "⬆ Insert"}
          </button>
        </div>

        <button className="btn-link" style={{ marginTop: 8, fontSize: 12 }} onClick={onCancel} disabled={inserting}>
          Done
        </button>
      </div>
    );
  }

  return null;
}

/**
 * Injected into the mailbox tab via executeScript to insert the draft text
 * into the active reply compose box. Must be a standalone function (no
 * closure over popup module scope) because executeScript serialises it.
 */
function insertDraftIntoMailbox(draft: string): { ok: boolean; error?: string } {
  function sleep(ms: number) { return new Promise<void>((r) => setTimeout(r, ms)); }

  // Try to find an already-open reply compose box first.
  const selectors = [
    // Gmail
    '[role="textbox"][aria-label*="Reply"][contenteditable="true"]',
    '[role="textbox"][aria-label*="reply"][contenteditable="true"]',
    // Outlook
    '[data-testid="compose-editor"][contenteditable="true"]',
    '[contenteditable="true"][role="textbox"]',
  ];

  let box: HTMLElement | null = null;
  for (const sel of selectors) {
    box = document.querySelector<HTMLElement>(sel);
    if (box) break;
  }

  if (!box) {
    // Try clicking Reply button
    const replyBtns = [
      '[data-tooltip="Reply"]',
      '[aria-label="Reply"]',
      '[data-testid="reply-button"]',
      '[title="Reply"]',
    ];
    for (const sel of replyBtns) {
      const btn = document.querySelector<HTMLElement>(sel);
      if (btn) { btn.click(); break; }
    }
    // Give the DOM a moment to render the composer
    return new Promise<{ ok: boolean; error?: string }>((resolve) => {
      setTimeout(() => {
        for (const sel of selectors) {
          box = document.querySelector<HTMLElement>(sel);
          if (box) break;
        }
        if (!box) {
          resolve({ ok: false, error: "Reply composer not found. Click Reply first, then try Insert again." });
          return;
        }
        box.focus();
        document.execCommand("selectAll", false);
        const inserted = document.execCommand("insertText", false, draft);
        if (!inserted || (box.textContent ?? "").trim() === "") {
          box.textContent = draft;
          box.dispatchEvent(new Event("input", { bubbles: true }));
        }
        resolve({ ok: true });
      }, 700);
    }) as any;
  }

  box.focus();
  document.execCommand("selectAll", false);
  const inserted = document.execCommand("insertText", false, draft);
  if (!inserted || (box.textContent ?? "").trim() === "") {
    box.textContent = draft;
    box.dispatchEvent(new Event("input", { bubbles: true }));
  }
  return { ok: true };
}
