/**
 * CSV viewer — parses CSV text and builds a self-contained HTML page that
 * renders the data as a sortable, paginated table. Used by the
 * downloads:open-in-tab flow so .csv downloads are readable in a tab
 * instead of raw comma-separated text.
 */

/** Maximum rows (excluding header) embedded in the viewer page. */
export const CSV_VIEWER_MAX_ROWS = 5000;

/** Rows rendered per page in the viewer. */
export const CSV_VIEWER_PAGE_SIZE = 500;

/** Files larger than this fall back to the plain file:// rendering. */
export const CSV_VIEWER_MAX_FILE_BYTES = 25 * 1024 * 1024; // 25 MB

export interface ParsedCsv {
  /** Header row (first row of the file). Empty when the file has no rows. */
  header: string[];
  /** Data rows, capped at `maxRows`. */
  rows: string[][];
  /** Total data rows in the file (may exceed rows.length when truncated). */
  totalRows: number;
  /** True when rows were capped at `maxRows`. */
  truncated: boolean;
}

/**
 * RFC 4180-ish CSV parser: handles quoted fields, escaped quotes (""),
 * newlines inside quotes, and CRLF/LF line endings. Rows beyond `maxRows`
 * are counted but not stored, so huge files stay cheap to embed.
 */
export function parseCsv(text: string, maxRows: number = CSV_VIEWER_MAX_ROWS): ParsedCsv {
  const rows: string[][] = [];
  let header: string[] = [];
  let totalRows = 0;
  let headerSeen = false;

  let field = '';
  let row: string[] = [];
  let inQuotes = false;
  let sawAny = false;

  const pushRow = () => {
    row.push(field);
    field = '';
    if (!headerSeen) {
      header = row;
      headerSeen = true;
    } else {
      totalRows++;
      if (rows.length < maxRows) rows.push(row);
    }
    row = [];
  };

  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    sawAny = true;
    if (inQuotes) {
      if (ch === '"') {
        if (text[i + 1] === '"') {
          field += '"';
          i++;
        } else {
          inQuotes = false;
        }
      } else {
        field += ch;
      }
    } else if (ch === '"') {
      inQuotes = true;
    } else if (ch === ',') {
      row.push(field);
      field = '';
    } else if (ch === '\n') {
      pushRow();
    } else if (ch === '\r') {
      if (text[i + 1] === '\n') i++;
      pushRow();
    } else {
      field += ch;
    }
  }
  // Trailing row without a final newline (skip a fully empty trailing line).
  if (sawAny && (field !== '' || row.length > 0)) {
    pushRow();
  }

  return {
    header,
    rows,
    totalRows,
    truncated: totalRows > rows.length,
  };
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Serialize data for embedding inside a <script> tag. `<` is escaped so a
 * cell containing "</script>" cannot break out of the script context.
 */
function jsonForScript(value: unknown): string {
  return JSON.stringify(value).replace(/</g, '\\u003c');
}

/**
 * Build a fully self-contained HTML page rendering the parsed CSV as a
 * sortable, paginated table. No external resources are referenced.
 */
export function buildCsvViewerHtml(filename: string, csvText: string): string {
  const parsed = parseCsv(csvText);
  const title = escapeHtml(filename);
  const payload = jsonForScript({
    header: parsed.header,
    rows: parsed.rows,
    totalRows: parsed.totalRows,
    truncated: parsed.truncated,
    pageSize: CSV_VIEWER_PAGE_SIZE,
  });

  return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title}</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 13px/1.5 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f7f7f8; color: #1c1c1e; }
  @media (prefers-color-scheme: dark) { body { background: #1c1c1e; color: #e5e5ea; } }
  header { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: inherit; border-bottom: 1px solid rgba(128,128,128,.3); }
  header h1 { font-size: 14px; font-weight: 600; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  header .meta { color: rgba(128,128,128,.9); font-size: 12px; white-space: nowrap; }
  .notice { margin: 8px 16px 0; padding: 8px 12px; border-radius: 8px; background: rgba(255,180,0,.15); border: 1px solid rgba(255,180,0,.4); font-size: 12px; }
  .wrap { padding: 12px 16px 60px; overflow-x: auto; }
  table { border-collapse: collapse; width: 100%; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  @media (prefers-color-scheme: dark) { table { background: #2c2c2e; } }
  th, td { padding: 6px 10px; border-bottom: 1px solid rgba(128,128,128,.2); text-align: left; max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  th { position: sticky; top: 0; background: rgba(128,128,128,.12); font-weight: 600; cursor: pointer; user-select: none; }
  th .arrow { opacity: .6; font-size: 10px; margin-left: 4px; }
  tr:hover td { background: rgba(128,128,128,.08); }
  td.num-cell { text-align: right; font-variant-numeric: tabular-nums; }
  .pager { position: fixed; bottom: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: center; gap: 12px; padding: 8px; background: inherit; border-top: 1px solid rgba(128,128,128,.3); }
  .pager button { font: inherit; padding: 4px 14px; border-radius: 6px; border: 1px solid rgba(128,128,128,.4); background: transparent; color: inherit; cursor: pointer; }
  .pager button:disabled { opacity: .4; cursor: default; }
  .empty { padding: 40px; text-align: center; color: rgba(128,128,128,.9); }
</style>
</head>
<body>
<header><h1>${title}</h1><span class="meta" id="meta"></span></header>
<div id="notice"></div>
<div class="wrap"><div id="table"></div></div>
<div class="pager" id="pager" hidden>
  <button id="prev">&larr; Prev</button>
  <span id="pageinfo"></span>
  <button id="next">Next &rarr;</button>
</div>
<script type="application/json" id="csv-data">${payload}</script>
<script>
(function () {
  var data = JSON.parse(document.getElementById('csv-data').textContent);
  var header = data.header, rows = data.rows;
  var pageSize = data.pageSize, page = 0;
  var sortCol = -1, sortDir = 1;
  var view = rows.slice();

  document.getElementById('meta').textContent =
    data.totalRows.toLocaleString() + ' row' + (data.totalRows === 1 ? '' : 's') +
    ' \\u00b7 ' + header.length + ' column' + (header.length === 1 ? '' : 's');

  if (data.truncated) {
    var n = document.getElementById('notice');
    var d = document.createElement('div');
    d.className = 'notice';
    d.textContent = 'Large file: showing the first ' + rows.length.toLocaleString() +
      ' of ' + data.totalRows.toLocaleString() + ' rows. Open the file in a spreadsheet app to see everything.';
    n.appendChild(d);
  }

  function isNumeric(s) { return s !== '' && !isNaN(Number(s)); }
  var numericCols = header.map(function (_, c) {
    var seen = false;
    for (var i = 0; i < Math.min(rows.length, 200); i++) {
      var v = rows[i][c];
      if (v === undefined || v === '') continue;
      if (!isNumeric(v)) return false;
      seen = true;
    }
    return seen;
  });

  function sortView() {
    if (sortCol < 0) { view = rows.slice(); return; }
    var c = sortCol, dir = sortDir, num = numericCols[c];
    view = rows.slice().sort(function (a, b) {
      var x = a[c] === undefined ? '' : a[c];
      var y = b[c] === undefined ? '' : b[c];
      if (num) {
        var nx = x === '' ? -Infinity : Number(x);
        var ny = y === '' ? -Infinity : Number(y);
        return (nx - ny) * dir;
      }
      return x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' }) * dir;
    });
  }

  function render() {
    var host = document.getElementById('table');
    host.textContent = '';
    if (header.length === 0 || (header.length === 1 && header[0] === '' && rows.length === 0)) {
      var e = document.createElement('div');
      e.className = 'empty';
      e.textContent = 'This CSV file is empty.';
      host.appendChild(e);
      return;
    }
    var table = document.createElement('table');
    var thead = document.createElement('thead');
    var hr = document.createElement('tr');
    header.forEach(function (h, c) {
      var th = document.createElement('th');
      th.textContent = h;
      if (c === sortCol) {
        var a = document.createElement('span');
        a.className = 'arrow';
        a.textContent = sortDir === 1 ? '\\u25b2' : '\\u25bc';
        th.appendChild(a);
      }
      th.addEventListener('click', function () {
        if (sortCol === c) { sortDir = -sortDir; } else { sortCol = c; sortDir = 1; }
        sortView(); page = 0; render();
      });
      hr.appendChild(th);
    });
    thead.appendChild(hr);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    var start = page * pageSize;
    var slice = view.slice(start, start + pageSize);
    slice.forEach(function (r) {
      var tr = document.createElement('tr');
      for (var c = 0; c < header.length; c++) {
        var td = document.createElement('td');
        var v = r[c] === undefined ? '' : r[c];
        td.textContent = v;
        td.title = v;
        if (numericCols[c]) td.className = 'num-cell';
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    host.appendChild(table);

    var pages = Math.max(1, Math.ceil(view.length / pageSize));
    var pager = document.getElementById('pager');
    if (pages > 1) {
      pager.hidden = false;
      document.getElementById('pageinfo').textContent = 'Page ' + (page + 1) + ' of ' + pages;
      document.getElementById('prev').disabled = page === 0;
      document.getElementById('next').disabled = page >= pages - 1;
    } else {
      pager.hidden = true;
    }
  }

  document.getElementById('prev').addEventListener('click', function () {
    if (page > 0) { page--; render(); window.scrollTo(0, 0); }
  });
  document.getElementById('next').addEventListener('click', function () {
    page++; render(); window.scrollTo(0, 0);
  });

  render();
})();
</script>
</body>
</html>`;
}

/** True when a downloaded file should get the CSV table viewer. */
export function isCsvDownload(filename: string, mimeType?: string | null): boolean {
  if (/\.csv$/i.test(filename.trim())) return true;
  if (mimeType) {
    const base = mimeType.split(';')[0]?.trim().toLowerCase();
    if (base === 'text/csv') return true;
  }
  return false;
}
