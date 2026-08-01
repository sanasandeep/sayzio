/**
 * Minimal, sanitized-by-construction Markdown → HTML renderer for the
 * downloaded-file viewer. The entire source is HTML-escaped FIRST, then
 * Markdown structure is layered on top, so file content can never inject
 * raw HTML or scripts. Link/image URLs are restricted to http/https/mailto.
 */

/** True when a downloaded file should get the rendered Markdown viewer. */
export function isMarkdownDownload(filename: string, mimeType?: string | null): boolean {
  if (mimeType) {
    const base = mimeType.split(';')[0]?.trim().toLowerCase();
    if (base === 'text/markdown' || base === 'text/x-markdown') return true;
  }
  return /\.(md|markdown)$/i.test(filename.trim());
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** Only allow safe absolute link targets; everything else renders as text. */
function safeHref(rawUrl: string): string | null {
  const url = rawUrl.trim();
  if (/^(https?:\/\/|mailto:)/i.test(url)) return url;
  return null;
}

/** Inline markdown: code spans, bold, italic, strikethrough, links, images. */
function renderInline(escaped: string): string {
  let out = '';
  // Split out code spans first so no other inline rules apply inside them.
  const parts = escaped.split(/(`+)([^`]*?)\1/g);
  for (let i = 0; i < parts.length; i++) {
    if (i % 3 === 1) continue; // the backtick delimiter group
    if (i % 3 === 2) {
      out += `<code>${parts[i]}</code>`;
      continue;
    }
    let text = parts[i] ?? '';
    // Images: render as a plain link (no remote fetches from the viewer).
    text = text.replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\)/g, (_m, alt: string, url: string) => {
      const href = safeHref(url);
      const label = alt || url;
      return href ? `<a href="${escapeHtml(href)}" rel="noopener noreferrer">🖼 ${label}</a>` : label;
    });
    // Links
    text = text.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\)/g, (_m, label: string, url: string) => {
      const href = safeHref(url);
      return href ? `<a href="${escapeHtml(href)}" rel="noopener noreferrer">${label}</a>` : label;
    });
    // Autolinks: <https://…> was escaped to &lt;url&gt;
    text = text.replace(/&lt;(https?:\/\/[^\s&]+)&gt;/g, '<a href="$1" rel="noopener noreferrer">$1</a>');
    // Bold, italic, strikethrough
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    text = text.replace(/(^|[^*])\*([^*\s][^*]*?)\*/g, '$1<em>$2</em>');
    text = text.replace(/(^|[^_\w])_([^_\s][^_]*?)_(?![\w])/g, '$1<em>$2</em>');
    text = text.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    out += text;
  }
  return out;
}

/**
 * Convert Markdown source into sanitized HTML body markup.
 * Supports: headings, fenced code blocks, blockquotes, hr, unordered and
 * ordered lists (one nesting level via indentation), tables are left as text,
 * paragraphs, and inline formatting.
 */
export function renderMarkdownToHtml(source: string): string {
  const lines = source.replace(/\r\n?/g, '\n').split('\n');
  const html: string[] = [];
  let i = 0;
  let paragraph: string[] = [];
  const listStack: Array<'ul' | 'ol'> = [];

  const flushParagraph = () => {
    if (paragraph.length > 0) {
      html.push(`<p>${renderInline(escapeHtml(paragraph.join(' ')))}</p>`);
      paragraph = [];
    }
  };
  const closeLists = (depth = 0) => {
    while (listStack.length > depth) {
      html.push(`</${listStack.pop()}>`);
    }
  };

  while (i < lines.length) {
    const line = lines[i] ?? '';

    // Fenced code block
    const fence = line.match(/^\s*(```|~~~)\s*(\S*)\s*$/);
    if (fence) {
      flushParagraph();
      closeLists();
      const marker = fence[1] as string;
      const lang = (fence[2] ?? '').replace(/[^\w+-]/g, '');
      const code: string[] = [];
      i++;
      while (i < lines.length && !(lines[i] ?? '').match(new RegExp(`^\\s*${marker}\\s*$`))) {
        code.push(lines[i] ?? '');
        i++;
      }
      i++; // skip closing fence
      const cls = lang ? ` class="language-${lang}"` : '';
      html.push(`<pre><code${cls}>${escapeHtml(code.join('\n'))}</code></pre>`);
      continue;
    }

    // Heading
    const heading = line.match(/^(#{1,6})\s+(.*)$/);
    if (heading) {
      flushParagraph();
      closeLists();
      const level = (heading[1] as string).length;
      html.push(`<h${level}>${renderInline(escapeHtml((heading[2] ?? '').replace(/\s+#+\s*$/, '')))}</h${level}>`);
      i++;
      continue;
    }

    // Horizontal rule
    if (/^\s*([-*_])\s*(?:\1\s*){2,}$/.test(line)) {
      flushParagraph();
      closeLists();
      html.push('<hr>');
      i++;
      continue;
    }

    // Blockquote (collect consecutive lines)
    if (/^\s*>\s?/.test(line)) {
      flushParagraph();
      closeLists();
      const quote: string[] = [];
      while (i < lines.length && /^\s*>\s?/.test(lines[i] ?? '')) {
        quote.push((lines[i] ?? '').replace(/^\s*>\s?/, ''));
        i++;
      }
      html.push(`<blockquote>${renderMarkdownToHtml(quote.join('\n'))}</blockquote>`);
      continue;
    }

    // List item (unordered or ordered, one nesting level via indentation)
    const listItem = line.match(/^(\s*)([-*+]|\d+[.)])\s+(.*)$/);
    if (listItem) {
      flushParagraph();
      const indent = (listItem[1] as string).length;
      const depth = indent >= 2 ? 2 : 1;
      const kind: 'ul' | 'ol' = /^\d/.test(listItem[2] as string) ? 'ol' : 'ul';
      while (listStack.length > depth) html.push(`</${listStack.pop()}>`);
      while (listStack.length < depth) {
        listStack.push(kind);
        html.push(`<${kind}>`);
      }
      if (listStack[depth - 1] !== kind) {
        html.push(`</${listStack.pop()}>`);
        listStack.push(kind);
        html.push(`<${kind}>`);
      }
      // Task list checkbox
      let item = listItem[3] ?? '';
      let prefix = '';
      const task = item.match(/^\[([ xX])\]\s+(.*)$/);
      if (task) {
        prefix = task[1] === ' ' ? '<input type="checkbox" disabled> ' : '<input type="checkbox" checked disabled> ';
        item = task[2] ?? '';
      }
      html.push(`<li>${prefix}${renderInline(escapeHtml(item))}</li>`);
      i++;
      continue;
    }

    // Blank line
    if (/^\s*$/.test(line)) {
      flushParagraph();
      closeLists();
      i++;
      continue;
    }

    // Setext headings (=== / --- under a paragraph line)
    const next = lines[i + 1] ?? '';
    if (paragraph.length === 0 && /^\s*(=+|-+)\s*$/.test(next) && line.trim() !== '') {
      closeLists();
      const level = next.trim().startsWith('=') ? 1 : 2;
      html.push(`<h${level}>${renderInline(escapeHtml(line.trim()))}</h${level}>`);
      i += 2;
      continue;
    }

    // Paragraph text (also terminates any open list)
    closeLists();
    paragraph.push(line.trim());
    i++;
  }
  flushParagraph();
  closeLists();
  return html.join('\n');
}

/**
 * Wrap rendered Markdown in a complete, self-contained viewer document.
 * No scripts; a "View raw source" link points at the original file URL.
 */
export function renderMarkdownDocument(source: string, opts: { title: string; rawFileUrl: string }): string {
  const body = renderMarkdownToHtml(source);
  const title = escapeHtml(opts.title);
  const rawUrl = escapeHtml(opts.rawFileUrl);
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="light dark">
<title>${title}</title>
<style>
:root { --fg: #1f2430; --muted: #667085; --border: #e4e7ec; --code-bg: #f4f5f7; --accent: #3d6bff; --bg: #ffffff; }
@media (prefers-color-scheme: dark) {
  :root { --fg: #e5e9f0; --muted: #9aa4b2; --border: #2c3340; --code-bg: #1b202b; --accent: #7aa5ff; --bg: #12151c; }
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.65 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
.zio-md-header { position: sticky; top: 0; display: flex; align-items: center; gap: 12px; padding: 10px 20px; border-bottom: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--muted); }
.zio-md-header .fname { font-weight: 600; color: var(--fg); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.zio-md-header a { margin-left: auto; color: var(--accent); text-decoration: none; white-space: nowrap; }
.zio-md-header a:hover { text-decoration: underline; }
main { max-width: 820px; margin: 0 auto; padding: 28px 24px 80px; }
h1, h2, h3, h4, h5, h6 { line-height: 1.3; margin: 1.4em 0 0.5em; }
h1 { font-size: 2em; border-bottom: 1px solid var(--border); padding-bottom: 0.3em; }
h2 { font-size: 1.5em; border-bottom: 1px solid var(--border); padding-bottom: 0.3em; }
a { color: var(--accent); }
code { background: var(--code-bg); border-radius: 4px; padding: 0.15em 0.4em; font-size: 0.9em; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
pre { background: var(--code-bg); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; overflow-x: auto; }
pre code { background: none; padding: 0; font-size: 0.88em; }
blockquote { margin: 1em 0; padding: 0.2em 1em; border-left: 4px solid var(--border); color: var(--muted); }
hr { border: none; border-top: 1px solid var(--border); margin: 2em 0; }
ul, ol { padding-left: 1.6em; }
li { margin: 0.25em 0; }
input[type="checkbox"] { margin-right: 6px; }
</style>
</head>
<body>
<div class="zio-md-header"><span>Markdown preview</span><span class="fname">${title}</span><a href="${rawUrl}">View raw source</a></div>
<main>
${body}
</main>
</body>
</html>
`;
}
