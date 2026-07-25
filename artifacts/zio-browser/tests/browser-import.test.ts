import { describe, it, expect } from 'vitest';
import { parseBookmarksHtml } from '../src/main/browser-import';

describe('parseBookmarksHtml', () => {
  it('parses a Netscape bookmarks export with folders', () => {
    const html = `
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL><p>
  <DT><H3 ADD_DATE="1700000000">Bookmarks bar</H3>
  <DL><p>
    <DT><A HREF="https://example.com/" ADD_DATE="1700000001">Example</A>
    <DT><H3>Work</H3>
    <DL><p>
      <DT><A HREF="https://work.example.com/dash" ICON="data:image/png;base64,x">Work Dash</A>
    </DL><p>
    <DT><A HREF="https://after.example.com/">After Folder</A>
  </DL><p>
</DL><p>`;
    const items = parseBookmarksHtml(html);
    expect(items).toHaveLength(3);
    expect(items[0]).toEqual({ url: 'https://example.com/', title: 'Example', folder: 'Bookmarks bar' });
    expect(items[1]).toEqual({ url: 'https://work.example.com/dash', title: 'Work Dash', folder: 'Work' });
    // After the inner </DL>, folder should pop back to the outer one.
    expect(items[2].folder).toBe('Bookmarks bar');
  });

  it('skips non-http(s) links and decodes entities', () => {
    const html = `
<DL><p>
  <DT><A HREF="javascript:alert(1)">bad</A>
  <DT><A HREF="chrome://settings">internal</A>
  <DT><A HREF="https://example.com/?a=1&amp;b=2">A &amp; B</A>
</DL>`;
    const items = parseBookmarksHtml(html);
    expect(items).toHaveLength(1);
    expect(items[0].url).toBe('https://example.com/?a=1&b=2');
    expect(items[0].title).toBe('A & B');
  });

  it('returns empty array for empty or junk input', () => {
    expect(parseBookmarksHtml('')).toEqual([]);
    expect(parseBookmarksHtml('<html><body>nothing here</body></html>')).toEqual([]);
  });
});
