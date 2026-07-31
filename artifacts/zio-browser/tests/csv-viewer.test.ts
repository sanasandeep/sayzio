import { describe, it, expect } from 'vitest';
import {
  parseCsv,
  buildCsvViewerHtml,
  isCsvDownload,
  CSV_VIEWER_MAX_ROWS,
  CSV_VIEWER_PAGE_SIZE,
} from '../src/shared/csv-viewer';

describe('parseCsv', () => {
  it('parses a simple CSV with header and rows', () => {
    const p = parseCsv('name,age\nAlice,30\nBob,25\n');
    expect(p.header).toEqual(['name', 'age']);
    expect(p.rows).toEqual([
      ['Alice', '30'],
      ['Bob', '25'],
    ]);
    expect(p.totalRows).toBe(2);
    expect(p.truncated).toBe(false);
  });

  it('handles quoted fields with commas, escaped quotes, and embedded newlines', () => {
    const p = parseCsv('a,b\n"x, y","say ""hi"""\n"line1\nline2",z\n');
    expect(p.rows).toEqual([
      ['x, y', 'say "hi"'],
      ['line1\nline2', 'z'],
    ]);
  });

  it('handles CRLF line endings', () => {
    const p = parseCsv('a,b\r\n1,2\r\n3,4\r\n');
    expect(p.header).toEqual(['a', 'b']);
    expect(p.rows).toEqual([
      ['1', '2'],
      ['3', '4'],
    ]);
  });

  it('handles a trailing row without a final newline', () => {
    const p = parseCsv('a,b\n1,2');
    expect(p.rows).toEqual([['1', '2']]);
    expect(p.totalRows).toBe(1);
  });

  it('handles empty input', () => {
    const p = parseCsv('');
    expect(p.header).toEqual([]);
    expect(p.rows).toEqual([]);
    expect(p.totalRows).toBe(0);
    expect(p.truncated).toBe(false);
  });

  it('caps stored rows but keeps counting the total', () => {
    const lines = ['col'];
    for (let i = 0; i < 20; i++) lines.push(String(i));
    const p = parseCsv(lines.join('\n'), 5);
    expect(p.rows.length).toBe(5);
    expect(p.totalRows).toBe(20);
    expect(p.truncated).toBe(true);
  });

  it('default cap matches CSV_VIEWER_MAX_ROWS', () => {
    const lines = ['col'];
    for (let i = 0; i < CSV_VIEWER_MAX_ROWS + 10; i++) lines.push(String(i));
    const p = parseCsv(lines.join('\n'));
    expect(p.rows.length).toBe(CSV_VIEWER_MAX_ROWS);
    expect(p.truncated).toBe(true);
  });
});

describe('buildCsvViewerHtml', () => {
  it('embeds the parsed data and page size as JSON', () => {
    const html = buildCsvViewerHtml('export.csv', 'name,age\nAlice,30\n');
    expect(html).toContain('id="csv-data"');
    expect(html).toContain('"header":["name","age"]');
    expect(html).toContain('"rows":[["Alice","30"]]');
    expect(html).toContain(`"pageSize":${CSV_VIEWER_PAGE_SIZE}`);
  });

  it('escapes the filename in the title', () => {
    const html = buildCsvViewerHtml('<script>.csv', 'a\n1\n');
    expect(html).toContain('<title>&lt;script&gt;.csv</title>');
    expect(html).not.toContain('<title><script>');
  });

  it('escapes </script> sequences inside cell data', () => {
    const html = buildCsvViewerHtml('x.csv', 'a\n"</script><img src=x>"\n');
    // The raw closing tag must never appear inside the embedded JSON.
    expect(html).not.toContain('"\\u003c/script>\u003cimg'.replace(/\\u003c/g, '</'));
    expect(html).toContain('\\u003c/script>');
  });

  it('is fully self-contained (no external resource references)', () => {
    const html = buildCsvViewerHtml('x.csv', 'a,b\n1,2\n');
    expect(html).not.toMatch(/src="https?:/);
    expect(html).not.toMatch(/href="https?:/);
  });
});

describe('isCsvDownload', () => {
  it('matches .csv extensions case-insensitively', () => {
    expect(isCsvDownload('report.csv')).toBe(true);
    expect(isCsvDownload('REPORT.CSV')).toBe(true);
    expect(isCsvDownload(' export.Csv ')).toBe(true);
  });

  it('matches text/csv MIME type regardless of extension', () => {
    expect(isCsvDownload('data.txt', 'text/csv')).toBe(true);
    expect(isCsvDownload('data.txt', 'text/csv; charset=utf-8')).toBe(true);
  });

  it('rejects non-CSV files', () => {
    expect(isCsvDownload('notes.txt')).toBe(false);
    expect(isCsvDownload('data.json', 'application/json')).toBe(false);
    expect(isCsvDownload('doc.md', 'text/markdown')).toBe(false);
  });
});
