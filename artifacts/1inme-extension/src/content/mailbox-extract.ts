/**
 * content-mailbox-extract.ts
 *
 * Injected on demand (executeScript) when the user triggers "Draft AI reply"
 * in the popup while a Gmail or Outlook-web tab is active.
 *
 * Responsibilities:
 *  1. Detect whether the current page is a supported mailbox thread view.
 *  2. Extract the visible email conversation (subject, participants, ordered
 *     messages) with quoted history trimmed.
 *  3. Optionally insert a supplied draft string into the active (or newly
 *     opened) reply compose box — called via a separate executeScript call
 *     with the INSERT_DRAFT function injected inline.
 *
 * All exported types are used by the popup; the file itself is bundled as a
 * standalone content script (content-mailbox-extract.js) by Vite.
 */

export type MailboxProvider = "gmail" | "outlook";

export interface MailboxMessage {
  role: "inbound" | "outbound";
  sender: string;
  body: string;
}

export interface MailboxThread {
  provider: MailboxProvider;
  subject: string;
  participants: string[];
  messages: MailboxMessage[];
}

export type ExtractResult =
  | { ok: true; thread: MailboxThread }
  | { ok: false; error: string; provider?: MailboxProvider };

// ── Provider detection ────────────────────────────────────────────────

function detectProvider(): MailboxProvider | null {
  const host = location.hostname.replace(/^www\./, "");
  if (host === "mail.google.com") return "gmail";
  if (host === "outlook.live.com" || host === "outlook.office.com" || host === "outlook.office365.com") return "outlook";
  return null;
}

// ── Text cleaning helpers ─────────────────────────────────────────────

/** Remove Gmail/Outlook "On <date>, <person> wrote:" quoted-reply lines. */
function trimQuotedHistory(text: string): string {
  // Gmail "On Mon, 1 Jan 2024 at 10:00, Alice <alice@example.com> wrote:"
  text = text.replace(/\n?On .{10,100} wrote:\n[\s\S]*/i, "").trim();
  // Outlook "From: ... Sent: ... To: ... Subject: ..."
  text = text.replace(/\n?-{3,}\s*Original Message\s*-{3,}[\s\S]*/i, "").trim();
  text = text.replace(/\n?_{3,}[\s\S]*/i, "").trim();
  return text;
}

function collapseWhitespace(text: string): string {
  return text.replace(/\r\n/g, "\n").replace(/[ \t]{2,}/g, " ").replace(/\n{3,}/g, "\n\n").trim();
}

// ── Gmail extraction ──────────────────────────────────────────────────

function extractGmail(): ExtractResult {
  // Subject — h2 inside the thread header
  const subjectEl = document.querySelector('h2[data-legacy-thread-id], .hP');
  const subject = (subjectEl?.textContent ?? document.title.replace(/ - Gmail$/, "")).trim();

  // Expanded message items — each .gs block is one message in a thread
  const messageEls = document.querySelectorAll<HTMLElement>('.gs');
  if (messageEls.length === 0) {
    return { ok: false, error: "No open email thread found. Open a Gmail thread first.", provider: "gmail" };
  }

  const participants = new Set<string>();
  const messages: MailboxMessage[] = [];
  const myAddresses = getGmailMyAddresses();

  for (const el of Array.from(messageEls)) {
    const senderEl = el.querySelector('.gD') as HTMLElement | null;
    const sender = senderEl?.getAttribute('email') ?? senderEl?.textContent?.trim() ?? "";
    if (sender) participants.add(sender);

    // Prefer the fully-expanded body; fall back to snippet
    const bodyEl = (
      el.querySelector('.a3s') as HTMLElement | null ??
      el.querySelector('[data-message-id]') as HTMLElement | null
    );
    if (!bodyEl) continue;

    // Clone to strip blockquotes (quoted history) before extracting text
    const clone = bodyEl.cloneNode(true) as HTMLElement;
    clone.querySelectorAll('.gmail_quote, blockquote[type="cite"]').forEach((q) => q.remove());

    const rawText = clone.innerText ?? clone.textContent ?? "";
    const body = collapseWhitespace(trimQuotedHistory(rawText));
    if (!body) continue;

    const isOutbound = myAddresses.size > 0 && myAddresses.has(sender.toLowerCase());
    messages.push({ role: isOutbound ? "outbound" : "inbound", sender, body });
  }

  if (messages.length === 0) {
    return { ok: false, error: "Could not read the email thread. Try expanding the messages.", provider: "gmail" };
  }

  return {
    ok: true,
    thread: {
      provider: "gmail",
      subject: subject || "(no subject)",
      participants: Array.from(participants).slice(0, 10),
      messages: messages.slice(-20),
    },
  };
}

/** Gmail embeds the signed-in user's addresses in account-menu data attrs. */
function getGmailMyAddresses(): Set<string> {
  const addrs = new Set<string>();
  document.querySelectorAll('[data-email]').forEach((el) => {
    const v = el.getAttribute('data-email');
    if (v) addrs.add(v.toLowerCase());
  });
  // Also try the "from" of sent messages (role=outbound detection fallback)
  document.querySelectorAll('.iw .go').forEach((el) => {
    const text = (el.textContent ?? "").trim().toLowerCase();
    if (text.includes("@")) addrs.add(text);
  });
  return addrs;
}

// ── Outlook extraction ────────────────────────────────────────────────

function extractOutlook(): ExtractResult {
  // Subject
  const subjectEl = document.querySelector<HTMLElement>(
    '[data-testid="subject"], .SubjectHeader, .allowTextSelection[role="heading"]'
  );
  const subject = (subjectEl?.textContent ?? document.title.replace(/ - Outlook$/, "")).trim();

  // Messages — Outlook renders each message as a reading pane item
  const messageEls = document.querySelectorAll<HTMLElement>(
    '[data-testid="message-content"], .ReadingPaneContent, .allowTextSelection[role="main"] .BodyContainer, .ConversationReadingPane .ItemContainer'
  );

  if (messageEls.length === 0) {
    return { ok: false, error: "No open email thread found. Open an Outlook message or conversation first.", provider: "outlook" };
  }

  const participants = new Set<string>();
  const messages: MailboxMessage[] = [];

  for (const el of Array.from(messageEls)) {
    // Sender
    const fromEl = el.querySelector<HTMLElement>(
      '[data-testid="from-field"] .fromAddress, .SenderName, [data-testid="senderName"]'
    );
    const sender = (fromEl?.textContent ?? "").trim();
    if (sender) participants.add(sender);

    // Body — prefer a visible div, strip quoted blocks
    const bodyEl = el.querySelector<HTMLElement>(
      '[data-testid="message-body"], .ReadingPaneContent, .itemBody, .allowTextSelection'
    ) ?? el;

    const clone = bodyEl.cloneNode(true) as HTMLElement;
    clone.querySelectorAll(
      '.quoted-text, blockquote, [data-testid="message-body-quoted"]'
    ).forEach((q) => q.remove());

    const rawText = clone.innerText ?? clone.textContent ?? "";
    const body = collapseWhitespace(trimQuotedHistory(rawText));
    if (!body) continue;

    messages.push({ role: "inbound", sender, body });
  }

  if (messages.length === 0) {
    return { ok: false, error: "Could not read the email. Try opening the message fully.", provider: "outlook" };
  }

  // Outlook doesn't tell us which messages are "outbound" without inspecting
  // sender addresses vs the current user. Mark all as inbound for safety;
  // the AI will pick up context from the thread direction.
  return {
    ok: true,
    thread: {
      provider: "outlook",
      subject: subject || "(no subject)",
      participants: Array.from(participants).slice(0, 10),
      messages: messages.slice(-20),
    },
  };
}

// ── Gmail reply insertion ─────────────────────────────────────────────

async function insertGmailReply(draft: string): Promise<{ ok: boolean; error?: string }> {
  // Open the reply composer if it isn't already open
  let composeBox = document.querySelector<HTMLElement>('[role="textbox"][aria-label*="Reply"][contenteditable="true"], [role="textbox"][aria-label*="reply"][contenteditable="true"]');

  if (!composeBox) {
    // Click the "Reply" button to open the composer
    const replyBtn = document.querySelector<HTMLElement>(
      '[data-tooltip="Reply"], [aria-label="Reply"], .ams[role="button"][data-tooltip*="Reply"]'
    );
    if (replyBtn) {
      replyBtn.click();
      await sleep(600);
      composeBox = document.querySelector<HTMLElement>('[role="textbox"][contenteditable="true"]');
    }
  }

  if (!composeBox) {
    return { ok: false, error: "Could not open the reply composer. Please click Reply first." };
  }

  composeBox.focus();
  // Insert at cursor / replace selection
  document.execCommand("selectAll");
  document.execCommand("insertText", false, draft);
  // If execCommand is not supported (Chromium strict mode), fall back to
  // setting innerHTML with a plain-text node.
  if ((composeBox.textContent ?? "").trim() === "") {
    composeBox.textContent = draft;
    composeBox.dispatchEvent(new Event("input", { bubbles: true }));
  }
  return { ok: true };
}

// ── Outlook reply insertion ───────────────────────────────────────────

async function insertOutlookReply(draft: string): Promise<{ ok: boolean; error?: string }> {
  let composeBox = document.querySelector<HTMLElement>(
    '[data-testid="compose-editor"], [contenteditable="true"][role="textbox"], .ms-TextField-field[contenteditable]'
  );

  if (!composeBox) {
    const replyBtn = document.querySelector<HTMLElement>(
      '[data-testid="reply-button"], [aria-label="Reply"], [title="Reply"]'
    );
    if (replyBtn) {
      replyBtn.click();
      await sleep(800);
      composeBox = document.querySelector<HTMLElement>(
        '[data-testid="compose-editor"], [contenteditable="true"][role="textbox"]'
      );
    }
  }

  if (!composeBox) {
    return { ok: false, error: "Could not open the reply composer. Please click Reply first." };
  }

  composeBox.focus();
  document.execCommand("selectAll");
  document.execCommand("insertText", false, draft);
  if ((composeBox.textContent ?? "").trim() === "") {
    composeBox.textContent = draft;
    composeBox.dispatchEvent(new Event("input", { bubbles: true }));
  }
  return { ok: true };
}

function sleep(ms: number): Promise<void> {
  return new Promise((r) => setTimeout(r, ms));
}

// ── Entry point ───────────────────────────────────────────────────────
// When injected via executeScript the final expression is the script result.
// Export functions separately so unit tests can import them; the IIFE result
// is what the popup reads via results[0].result.

const _provider = detectProvider();

(function (): ExtractResult {
  if (_provider === "gmail") return extractGmail();
  if (_provider === "outlook") return extractOutlook();
  return { ok: false, error: "This page is not a supported mailbox. Open a Gmail or Outlook web tab." };
})();
