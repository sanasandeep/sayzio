import { describe, it, expect } from 'vitest';
import { isMarkdownDownload, renderMarkdownToHtml, renderMarkdownDocument } from '../src/shared/markdown';

describe('isMarkdownDownload', () => {
  it('matches .md and .markdown extensions case-insensitively', () => {
    expect(isMarkdownDownload('README.md')).toBe(true);
    expect(isMarkdownDownload('notes.MARKDOWN')).toBe(true);
    expect(isMarkdownDownload('notes.txt')).toBe(false);
    expect(isMarkdownDownload('data.json')).toBe(false);
    expect(isMarkdownDownload('report.csv')).toBe(false);
  });

  it('matches text/markdown MIME regardless of extension', () => {
    expect(isMarkdownDownload('file.bin', 'text/markdown')).toBe(true);
    expect(isMarkdownDownload('file.bin', 'text/x-markdown; charset=utf-8')).toBe(true);
    expect(isMarkdownDownload('file.bin', 'text/plain')).toBe(false);
  });
});

describe('renderMarkdownToHtml', () => {
  it('renders headings, lists, and paragraphs', () => {
    const html = renderMarkdownToHtml('# Title\n\nSome text\n\n- one\n- two\n\n1. first\n2. second');
    expect(html).toContain('<h1>Title</h1>');
    expect(html).toContain('<p>Some text</p>');
    expect(html).toContain('<ul>');
    expect(html).toContain('<li>one</li>');
    expect(html).toContain('<ol>');
    expect(html).toContain('<li>first</li>');
  });

  it('renders inline formatting and code', () => {
    const html = renderMarkdownToHtml('**bold** *ital* `code` ~~gone~~');
    expect(html).toContain('<strong>bold</strong>');
    expect(html).toContain('<em>ital</em>');
    expect(html).toContain('<code>code</code>');
    expect(html).toContain('<del>gone</del>');
  });

  it('renders fenced code blocks verbatim and escaped', () => {
    const html = renderMarkdownToHtml('```js\nconst a = "<b>";\n# not a heading\n```');
    expect(html).toContain('<pre><code class="language-js">');
    expect(html).toContain('const a = &quot;&lt;b&gt;&quot;;');
    expect(html).toContain('# not a heading');
    expect(html).not.toContain('<h1>');
  });

  it('renders blockquotes and hr', () => {
    const html = renderMarkdownToHtml('> quoted\n\n---');
    expect(html).toContain('<blockquote>');
    expect(html).toContain('quoted');
    expect(html).toContain('<hr>');
  });

  it('escapes raw HTML — no script execution from content', () => {
    const html = renderMarkdownToHtml('<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>');
    expect(html).not.toContain('<script>');
    expect(html).not.toContain('<img');
    expect(html).toContain('&lt;script&gt;');
  });

  it('only allows http/https/mailto links; javascript: renders as text', () => {
    const html = renderMarkdownToHtml('[ok](https://example.com) [bad](javascript:alert(1)) [mail](mailto:a@b.c)');
    expect(html).toContain('<a href="https://example.com"');
    expect(html).toContain('<a href="mailto:a@b.c"');
    expect(html).not.toContain('javascript:');
  });

  it('does not inject attributes through link labels or urls', () => {
    const html = renderMarkdownToHtml('[a"onmouseover="x](https://e.com/"onclick="y)');
    // Quotes from labels/urls must be escaped so they can never terminate the
    // href attribute and start a new one (e.g. `" onclick="`). Every <a> tag
    // must carry exactly the href + rel attributes we emit — nothing injected.
    expect(html).not.toContain('onmouseover="');
    for (const tag of html.match(/<a [^>]*>/g) ?? []) {
      expect(tag).toMatch(/^<a href="[^"]*" rel="noopener noreferrer">$/);
    }
  });

  it('renders images as safe links, never <img> tags', () => {
    const html = renderMarkdownToHtml('![alt text](https://example.com/pic.png)');
    expect(html).not.toContain('<img');
    expect(html).toContain('<a href="https://example.com/pic.png"');
    expect(html).toContain('alt text');
  });

  it('renders task lists with disabled checkboxes', () => {
    const html = renderMarkdownToHtml('- [ ] todo\n- [x] done');
    expect(html).toContain('<input type="checkbox" disabled>');
    expect(html).toContain('<input type="checkbox" checked disabled>');
  });
});

describe('renderMarkdownDocument', () => {
  it('produces a full document with escaped title and raw-source link, no scripts', () => {
    const doc = renderMarkdownDocument('# Hi <script>', {
      title: '<file>.md',
      rawFileUrl: 'file:///tmp/%3Cfile%3E.md',
    });
    expect(doc).toContain('<!DOCTYPE html>');
    expect(doc).toContain('&lt;file&gt;.md');
    expect(doc).toContain('View raw source');
    expect(doc).toContain('href="file:///tmp/%3Cfile%3E.md"');
    expect(doc).not.toContain('<script');
  });
});
