<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Per-source ingestion pipeline for the AI Mind feature.
 *
 *   1. Resolve the source's raw text (delegated per type).
 *   2. Chunk into ~chunk_chars windows with chunk_overlap_chars overlap.
 *   3. Batch-embed via the shared OpenAiService (which meters credits).
 *   4. Replace the previous chunks for that source atomically — so a
 *      refresh never leaves the Mind in a half-rebuilt state.
 *
 * Status reporting is best-effort and human-readable; the controller
 * surfaces the message under each source row.
 */
class AiMindIngestor
{
    public function __construct(protected OpenAiService $openai) {}

    public function ingest(AiMindSource $source): void
    {
        $mind = $source->mind()->first();
        if (!$mind) return;

        // The owner's account is who pays for embeddings. The platform
        // mind has no owner; embeddings for the seeded default mind use
        // the first admin we can find, falling back to skipping spend.
        $payer = $mind->user_id
            ? User::find($mind->user_id)
            : User::where('role', 'super_admin')->orderBy('id')->first();

        $source->forceFill([
            'status' => AiMindSource::STATUS_PROCESSING,
            'status_message' => null,
        ])->save();

        try {
            // `feature` sources are answered live at query time — we
            // don't embed them. We still mark them ready so the UI
            // shows the source as installed and "auto-fresh".
            if ($source->type === AiMindSource::TYPE_FEATURE) {
                AiMindChunk::where('source_id', $source->id)->delete();
                $source->forceFill([
                    'status'           => AiMindSource::STATUS_READY,
                    'status_message'   => 'Live snapshot — answered at query time.',
                    'chunks_count'     => 0,
                    'last_ingested_at' => now(),
                ])->save();
                $mind->recountStats();
                return;
            }

            $caps = AiMindSettings::caps();
            $ocrUsed = false;
            try {
                $text = $this->extractText($source);
            } catch (PdfOcrRequiredException $e) {
                if (!$payer) {
                    // No account to charge means we can't run OCR — preserve
                    // the original "OCR required" surface for the operator.
                    throw new \RuntimeException('This PDF appears to be image-only — OCR is required. Please paste its text or upload a text-based PDF.');
                }
                $text = $this->extractPdfWithOcr($e->pdfBytes, $payer, $source, $caps);
                $ocrUsed = true;
            }
            if ($text === '') {
                throw new \RuntimeException('No extractable text found in this source.');
            }
            // Hard cap so a runaway crawl doesn't blow up the embedding
            // bill or the database.
            if (mb_strlen($text) > $caps['max_text_chars']) {
                $text = mb_substr($text, 0, $caps['max_text_chars']);
            }

            $chunks = $this->chunk($text, $caps['chunk_chars'], $caps['chunk_overlap_chars']);
            if (count($chunks) > $caps['max_chunks_per_source']) {
                $chunks = array_slice($chunks, 0, $caps['max_chunks_per_source']);
            }

            $model = AiMindSettings::embeddingModel();

            // Embed in small batches so a single failure doesn't lose
            // the whole source. We commit the new chunk set only after
            // every batch resolved.
            $vectors  = [];
            $tokens   = 0;
            $batches  = array_chunk($chunks, 50);
            foreach ($batches as $batch) {
                if ($payer) {
                    $resp = $this->openai->embed($payer, $model, $batch, [
                        'feature'    => 'mind',
                        'related_id' => $source->id,
                        'reason'     => "Mind ingest: {$source->title}",
                        'meta'       => [
                            'kind'      => 'ingest',
                            'mind_id'   => (int) $source->mind_id,
                            'source_id' => (int) $source->id,
                        ],
                    ]);
                    foreach ($resp['vectors'] as $v) $vectors[] = $v;
                    $tokens += (int) ($resp['tokens_in'] ?? 0);
                } else {
                    // No payer — store empty vectors so search still works
                    // for keyword fallback. Used only by the platform mind
                    // when no admin user exists yet (test boot).
                    foreach ($batch as $_) $vectors[] = [];
                }
            }

            DB::transaction(function () use ($source, $chunks, $vectors, $model, $ocrUsed, $text) {
                AiMindChunk::where('source_id', $source->id)->delete();
                foreach ($chunks as $i => $content) {
                    AiMindChunk::create([
                        'mind_id'   => $source->mind_id,
                        'source_id' => $source->id,
                        'ord'       => $i,
                        'content'   => $content,
                        'tokens'    => (int) ceil(mb_strlen($content) / 4),
                        'embedding' => $vectors[$i] ?? [],
                        'model'     => $model,
                    ]);
                }
                $attrs = [
                    'status'           => AiMindSource::STATUS_READY,
                    'status_message'   => $ocrUsed ? "OCR'd from scan" : null,
                    'chunks_count'     => count($chunks),
                    'last_ingested_at' => now(),
                    'next_refresh_at'  => $this->nextRefreshAt($source),
                ];
                // Persist the extracted text on body for document and link
                // sources so the source detail page can render it (and the
                // citation highlighter can pinpoint the chunk inline).
                // text/FAQ already store user-supplied body; feature has no
                // body. For documents and links, body would otherwise be
                // empty and force the highlighter into its fallback callout.
                if (in_array($source->type, [AiMindSource::TYPE_DOCUMENT, AiMindSource::TYPE_LINK], true)) {
                    $attrs['body'] = $text;
                }
                $source->forceFill($attrs)->save();
            });

            $mind->forceFill(['last_ingested_at' => now()])->save();
            $mind->recountStats();
        } catch (\Throwable $e) {
            Log::warning('Mind ingest failed', [
                'source_id' => $source->id, 'mind_id' => $source->mind_id,
                'error' => $e->getMessage(),
            ]);
            $source->forceFill([
                'status'         => AiMindSource::STATUS_FAILED,
                'status_message' => Str::limit($e->getMessage(), 480),
            ])->save();
        }
    }

    /** Re-extract & re-embed every source in the Mind. */
    public function ingestAllForMind(AiMind $mind): void
    {
        foreach ($mind->sources as $s) {
            $this->ingest($s);
        }
    }

    /**
     * Compute the next link-refresh timestamp from the source's
     * configured cadence (clamped by the platform minimum).
     */
    public function nextRefreshAt(AiMindSource $source): ?\Carbon\Carbon
    {
        if ($source->type !== AiMindSource::TYPE_LINK) return null;
        $minMin = max(15, AiMindSettings::cap('link_refresh_min_minutes'));
        $mins   = max($minMin, (int) ($source->refresh_minutes ?? (60 * 24)));
        return now()->addMinutes($mins);
    }

    /** Extract raw text from a source according to its type. */
    public function extractText(AiMindSource $source): string
    {
        return match ($source->type) {
            AiMindSource::TYPE_TEXT     => (string) $source->body,
            AiMindSource::TYPE_FAQ      => $this->flattenFaq($source->body),
            AiMindSource::TYPE_DOCUMENT => $this->extractDocument($source),
            AiMindSource::TYPE_LINK     => $this->fetchLink($source),
            default                     => '',
        };
    }

    protected function flattenFaq(?string $body): string
    {
        if (!$body) return '';
        $faqs = json_decode($body, true);
        if (!is_array($faqs)) return $body;
        $out = [];
        foreach ($faqs as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q === '' && $a === '') continue;
            $out[] = "Q: {$q}\nA: {$a}";
        }
        return implode("\n\n", $out);
    }

    protected function extractDocument(AiMindSource $source): string
    {
        if (!$source->storage_path) return '';
        $disk = Storage::disk($source->storage_disk ?: 'local');
        if (!$disk->exists($source->storage_path)) {
            throw new \RuntimeException('Document file is missing from storage.');
        }
        $mime = strtolower((string) $source->mime);
        $ext  = strtolower(pathinfo($source->storage_path, PATHINFO_EXTENSION));

        // TXT / MD: read directly. Strip any BOM. We restrict the
        // text/* MIME shortcut to plain/markdown so structured text
        // formats like text/rtf still hit their dedicated parser
        // below instead of being ingested as raw source.
        $plainTextMimes = ['text/plain', 'text/markdown', 'text/x-markdown'];
        if (in_array($ext, ['txt', 'md'], true) || in_array($mime, $plainTextMimes, true)) {
            $raw = $disk->get($source->storage_path);
            return preg_replace("/^\xEF\xBB\xBF/", '', (string) $raw);
        }

        // DOCX: parse with PHPWord so we keep the structure (headings,
        // lists, tables) instead of just dumping the body XML. The
        // structured text helps the embedder produce better chunks.
        if ($ext === 'docx' || str_contains($mime, 'wordprocessingml')) {
            $tmp = tempnam(sys_get_temp_dir(), 'mind-doc-');
            file_put_contents($tmp, $disk->get($source->storage_path));
            try {
                return $this->extractDocxStructured($tmp);
            } finally {
                @unlink($tmp);
            }
        }

        // Legacy binary .doc — PHPWord's MsDoc reader walks the OLE
        // streams and gives us structured paragraphs the same way the
        // DOCX path does, so we render through the same element tree.
        if ($ext === 'doc' || $mime === 'application/msword') {
            $tmp = tempnam(sys_get_temp_dir(), 'mind-doc-');
            file_put_contents($tmp, $disk->get($source->storage_path));
            try {
                return $this->extractWordStructured($tmp, 'MsDoc', 'DOC');
            } finally {
                @unlink($tmp);
            }
        }

        // RTF — PHPWord's RTF reader keeps paragraph/heading structure
        // for well-formed files. If it can't parse the document (some
        // exporters write quirky RTF), fall back to a control-word
        // stripper that at least preserves the visible text.
        if ($ext === 'rtf' || $mime === 'application/rtf' || $mime === 'text/rtf') {
            $bytes = (string) $disk->get($source->storage_path);
            $tmp   = tempnam(sys_get_temp_dir(), 'mind-rtf-');
            file_put_contents($tmp, $bytes);
            try {
                try {
                    return $this->extractWordStructured($tmp, 'RTF', 'RTF');
                } catch (\Throwable $e) {
                    $text = $this->extractRtfPlain($bytes);
                    if ($text === '') {
                        throw new \RuntimeException('Could not extract text from this RTF: ' . $e->getMessage());
                    }
                    return $text;
                }
            } finally {
                @unlink($tmp);
            }
        }

        // PPTX — OOXML zip with one XML per slide. We open it with
        // ZipArchive and pull the visible text runs from each slide,
        // keeping the slide title (first text frame) as a heading so
        // the chunker can break on slide boundaries.
        if ($ext === 'pptx' || str_contains($mime, 'presentationml')) {
            $tmp = tempnam(sys_get_temp_dir(), 'mind-pptx-');
            file_put_contents($tmp, $disk->get($source->storage_path));
            try {
                return $this->extractPptxStructured($tmp);
            } finally {
                @unlink($tmp);
            }
        }

        // PDF: parse with smalot/pdfparser so multi-column layouts and
        // embedded font encodings come through correctly. We let the
        // parser run first and only fall back to the OCR-required
        // message when it produces no text — checking raw bytes for
        // `/Font` up front misclassifies PDFs whose objects are stored
        // in compressed object streams.
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            $bytes = (string) $disk->get($source->storage_path);
            return $this->extractPdfStructured($bytes);
        }

        throw new \RuntimeException("Unsupported document type: {$ext}");
    }

    /**
     * PDF text extraction via smalot/pdfparser. Pages are joined with
     * blank lines so the chunker can break on page boundaries when it
     * lands near one.
     */
    protected function extractPdfStructured(string $bytes): string
    {
        $hasFontResource = false;
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseContent($bytes);
            $pages  = $pdf->getPages();
            $out    = [];
            foreach ($pages as $page) {
                // A page that exposes any font resource means the PDF
                // genuinely carries text glyphs (vs. being a pure image
                // scan). We use this to choose the right error message.
                if (!$hasFontResource) {
                    try {
                        if (method_exists($page, 'getFonts') && !empty($page->getFonts())) {
                            $hasFontResource = true;
                        }
                    } catch (\Throwable $e) { /* ignore — informational */ }
                }
                $t = trim((string) $page->getText());
                if ($t !== '') $out[] = $t;
            }
            $text = trim(implode("\n\n", $out));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not parse this PDF: ' . $e->getMessage());
        }
        if ($text === '') {
            if (!$hasFontResource) {
                // Bubble the bytes up so ingest() can attempt OCR rather
                // than failing here — extractText has no payer context.
                throw new PdfOcrRequiredException($bytes);
            }
            throw new \RuntimeException('Could not extract text from this PDF — try a text-based export.');
        }
        return $text;
    }

    /**
     * Render each PDF page to PNG via `pdftoppm` (poppler-utils) and ask
     * a vision-capable chat model to transcribe it. Pages are processed
     * sequentially so a mid-document failure leaves a clear error rather
     * than a partially-charged source.
     */
    protected function extractPdfWithOcr(string $bytes, User $payer, AiMindSource $source, array $caps): string
    {
        if (!$this->pdftoppmAvailable()) {
            throw new \RuntimeException('OCR is not available on this server (pdftoppm not installed).');
        }

        $maxPages = max(1, (int) ($caps['max_ocr_pages_per_source'] ?? 30));
        $tmpDir   = sys_get_temp_dir() . '/mind-ocr-' . uniqid('', true);
        if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException('Could not create OCR working directory.');
        }
        $pdfPath = $tmpDir . '/in.pdf';
        file_put_contents($pdfPath, $bytes);

        try {
            $cmd = sprintf(
                'pdftoppm -png -r 200 -l %d %s %s 2>&1',
                $maxPages,
                escapeshellarg($pdfPath),
                escapeshellarg($tmpDir . '/page'),
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0) {
                throw new \RuntimeException('Could not render PDF for OCR: ' . trim(implode("\n", $output)));
            }

            $pngs = glob($tmpDir . '/page-*.png') ?: [];
            sort($pngs);
            if (!$pngs) {
                throw new \RuntimeException('OCR rendering produced no pages.');
            }

            $model     = AiMindSettings::ocrModel();
            $pageTexts = [];
            foreach ($pngs as $i => $pngPath) {
                $b64 = base64_encode((string) file_get_contents($pngPath));
                $messages = [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Transcribe every readable word from this scanned page exactly as it appears. Preserve paragraph breaks. Return only the extracted text, with no commentary.'],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $b64]],
                    ],
                ]];
                $resp = $this->openai->chat($payer, $model, $messages, [
                    'feature'     => 'mind',
                    'related_id'  => $source->id,
                    'temperature' => 0,
                    'reason'      => 'Mind OCR page ' . ($i + 1) . ": {$source->title}",
                ]);
                $t = trim((string) ($resp['content'] ?? ''));
                if ($t !== '') $pageTexts[] = $t;
            }

            $text = trim(implode("\n\n", $pageTexts));
            if ($text === '') {
                throw new \RuntimeException('OCR ran but no text was recognised in this PDF.');
            }
            return $text;
        } finally {
            foreach (glob($tmpDir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($tmpDir);
        }
    }

    /** Cached check for the poppler `pdftoppm` binary on PATH. */
    protected function pdftoppmAvailable(): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        $out = []; $code = 1;
        @exec('command -v pdftoppm 2>/dev/null', $out, $code);
        return $available = ($code === 0 && !empty($out));
    }

    /**
     * DOCX structured extraction via PHPWord. We walk the element tree
     * so headings keep their prefix (#, ##), bullet/numbered lists keep
     * their markers, and tables render as pipe-separated rows.
     */
    protected function extractDocxStructured(string $path): string
    {
        return $this->extractWordStructured($path, 'Word2007', 'DOCX');
    }

    /**
     * Shared PHPWord extraction for any Word-family format the library
     * can read (Word2007/DOCX, MsDoc/.doc, RTF). The element renderer
     * is format-agnostic — headings, lists, and tables all map onto
     * the same Title/ListItem/Table classes.
     */
    protected function extractWordStructured(string $path, string $reader, string $label): string
    {
        try {
            $doc = \PhpOffice\PhpWord\IOFactory::load($path, $reader);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Could not open {$label} file: " . $e->getMessage());
        }
        $lines = [];
        foreach ($doc->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $this->renderDocxElement($el, $lines);
            }
        }
        $text = trim(implode("\n", $lines));
        if ($text === '') {
            throw new \RuntimeException("No extractable text found in this {$label}.");
        }
        return $text;
    }

    /**
     * Last-ditch RTF reader: strip control words, groups, and hex
     * escapes, leaving only the visible text. Used when PHPWord's RTF
     * reader rejects the file (some apps emit non-spec RTF).
     */
    protected function extractRtfPlain(string $rtf): string
    {
        // Drop binary blobs and embedded objects up front.
        $rtf = preg_replace('/\{\\\\(?:\*\\\\)?(?:pict|object|bin|fonttbl|colortbl|stylesheet|info|header|footer|themedata|datastore|latentstyles|listtable|rsidtbl)\b[^{}]*\}/is', ' ', $rtf);
        // Decode \'hh hex escapes (Windows-1252 best-effort).
        $rtf = preg_replace_callback('/\\\\\'([0-9a-fA-F]{2})/', function ($m) {
            $byte = chr(hexdec($m[1]));
            $u    = @iconv('Windows-1252', 'UTF-8//IGNORE', $byte);
            return $u !== false ? $u : '';
        }, $rtf);
        // Decode \uNNNN unicode escapes (signed 16-bit, optionally negative).
        $rtf = preg_replace_callback('/\\\\u(-?\d+)\??/', function ($m) {
            $cp = (int) $m[1];
            if ($cp < 0) $cp += 65536;
            return mb_chr($cp, 'UTF-8') ?: '';
        }, $rtf);
        // Paragraph / line breaks become real newlines.
        $rtf = preg_replace('/\\\\(par|line|page|sect)\b\s?/', "\n", $rtf);
        $rtf = preg_replace('/\\\\tab\b\s?/', "\t", $rtf);
        // Strip remaining control words and group markers.
        $rtf = preg_replace('/\\\\[a-zA-Z]+-?\d* ?/', '', $rtf);
        $rtf = str_replace(['{', '}', '\\\\', '\\'], ['', '', "\n", ''], $rtf);
        // Collapse runs of whitespace inside lines but keep paragraph breaks.
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]+/u', ' ', $l)), explode("\n", $rtf));
        $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));
        return trim(implode("\n", $lines));
    }

    /**
     * PPTX structured extraction. We open the OOXML package, sort the
     * slide parts by their natural slide number, and pull the text
     * runs from each. The first text frame on a slide is treated as
     * its title and emitted as a `## ` heading so the chunker can
     * break cleanly on slide boundaries.
     */
    protected function extractPptxStructured(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open PPTX file (not a valid OOXML package).');
        }
        try {
            $slideEntries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name && preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                    $slideEntries[(int) $m[1]] = $name;
                }
            }
            if (!$slideEntries) {
                throw new \RuntimeException('No slides found in this PPTX.');
            }
            ksort($slideEntries);
            $lines = [];
            $idx   = 0;
            foreach ($slideEntries as $num => $entry) {
                $idx++;
                $xml = (string) $zip->getFromName($entry);
                $frames = $this->pptxTextFrames($xml);
                if (!$frames) continue;
                $title = trim(array_shift($frames));
                $lines[] = '## Slide ' . $num . ($title !== '' ? ': ' . $title : '');
                foreach ($frames as $frame) {
                    $frame = trim($frame);
                    if ($frame !== '') $lines[] = $frame;
                }
                $lines[] = '';
            }
            $text = trim(implode("\n", $lines));
            if ($text === '') {
                throw new \RuntimeException('No extractable text found in this PPTX.');
            }
            return $text;
        } finally {
            $zip->close();
        }
    }

    /**
     * Pull text frames from one slide's XML. Each <p:sp> shape becomes
     * one frame; runs (`<a:t>`) inside it are concatenated and
     * paragraph (`<a:p>`) breaks become newlines so multi-line bullet
     * blocks stay readable.
     *
     * @return list<string>
     */
    protected function pptxTextFrames(string $xml): array
    {
        if ($xml === '') return [];
        // Use SimpleXML with namespace stripping so we don't have to
        // register the drawingml/presentationml namespaces explicitly.
        $clean = preg_replace('/<(\/?)(?:[a-zA-Z0-9]+:)/', '<$1', $xml);
        $prev  = libxml_use_internal_errors(true);
        $doc   = simplexml_load_string($clean);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$doc) return [];
        $frames = [];
        foreach ($doc->xpath('//sp/txBody') ?: [] as $body) {
            $paragraphs = [];
            foreach ($body->xpath('./p') ?: [] as $p) {
                $runs = [];
                foreach ($p->xpath('./r/t') ?: [] as $t) {
                    $runs[] = (string) $t;
                }
                // Standalone <a:t> (no run wrapper) — rare but possible.
                foreach ($p->xpath('./fld/t') ?: [] as $t) {
                    $runs[] = (string) $t;
                }
                $line = trim(implode('', $runs));
                if ($line !== '') $paragraphs[] = $line;
            }
            $frame = trim(implode("\n", $paragraphs));
            if ($frame !== '') $frames[] = $frame;
        }
        return $frames;
    }

    /** @param array<int,string> $lines */
    protected function renderDocxElement(object $el, array &$lines): void
    {
        $cls = class_basename($el);
        switch ($cls) {
            case 'Title':
                $depth = method_exists($el, 'getDepth') ? (int) $el->getDepth() : 1;
                $lines[] = str_repeat('#', max(1, min(6, $depth + 1))) . ' ' . $this->docxInline($el);
                $lines[] = '';
                break;
            case 'TextRun':
            case 'Text':
                $t = $this->docxInline($el);
                if ($t !== '') $lines[] = $t;
                break;
            case 'ListItem':
            case 'ListItemRun':
                $lines[] = '- ' . $this->docxInline($el);
                break;
            case 'Table':
                foreach ($el->getRows() as $row) {
                    $cells = [];
                    foreach ($row->getCells() as $cell) {
                        $cellLines = [];
                        foreach ($cell->getElements() as $ce) {
                            $this->renderDocxElement($ce, $cellLines);
                        }
                        $cells[] = trim(implode(' ', $cellLines));
                    }
                    $lines[] = '| ' . implode(' | ', $cells) . ' |';
                }
                $lines[] = '';
                break;
            default:
                if (method_exists($el, 'getElements')) {
                    foreach ($el->getElements() as $child) {
                        $this->renderDocxElement($child, $lines);
                    }
                } else {
                    $t = $this->docxInline($el);
                    if ($t !== '') $lines[] = $t;
                }
        }
    }

    protected function docxInline(object $el): string
    {
        if (method_exists($el, 'getText')) {
            $t = $el->getText();
            if (is_string($t)) return trim($t);
        }
        $parts = [];
        if (method_exists($el, 'getElements')) {
            foreach ($el->getElements() as $child) {
                $parts[] = $this->docxInline($child);
            }
        }
        return trim(implode(' ', array_filter($parts, fn ($p) => $p !== '')));
    }

    protected function fetchLink(AiMindSource $source): string
    {
        $url = trim((string) $source->url);
        if ($url === '') return '';
        // Refuse to crawl localhost/private IPs to avoid SSRF abuse.
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if ($this->isPrivateHost($host)) {
            throw new \RuntimeException('Refusing to crawl a private or local address.');
        }
        $robots = $this->fetchRobots($url);
        if ($robots && $this->isDisallowed($robots, $url)) {
            throw new \RuntimeException('robots.txt disallows fetching this URL.');
        }
        $resp = Http::withHeaders([
            'User-Agent' => '1INMEMindBot/1.0 (+https://1inme.com/bots)',
        ])->timeout(20)->get($url);
        if ($resp->failed()) {
            throw new \RuntimeException("Link fetch failed (HTTP {$resp->status()}).");
        }
        $html = (string) $resp->body();
        return $this->htmlToText($html);
    }

    protected function fetchRobots(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return null;
        $robotsUrl = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/robots.txt';
        try {
            $r = Http::timeout(5)->get($robotsUrl);
            return $r->ok() ? (string) $r->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isDisallowed(string $robots, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $applies = false;
        foreach (preg_split('/\R/', $robots) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
                $agent   = strtolower(trim($m[1]));
                $applies = $agent === '*' || str_contains($agent, '1inme');
                continue;
            }
            if ($applies && preg_match('/^Disallow:\s*(.*)$/i', $line, $m)) {
                $rule = trim($m[1]);
                if ($rule === '') continue;
                if (str_starts_with($path, $rule)) return true;
            }
        }
        return false;
    }

    protected function isPrivateHost(string $host): bool
    {
        if ($host === '') return true;
        $host = strtolower($host);
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) return true;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ?: gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    protected function htmlToText(string $html): string
    {
        // Strip script/style blocks before stripping tags so their
        // contents don't leak into the text body.
        $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', ' ', $html);
        $html = preg_replace('#<style\b[^>]*>(.*?)</style>#is', ' ', $html);
        $html = preg_replace('#<nav\b[^>]*>(.*?)</nav>#is', ' ', $html);
        $html = preg_replace('#<footer\b[^>]*>(.*?)</footer>#is', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\s+/u", ' ', $text);
        return trim((string) $text);
    }

    /**
     * Greedy character-window chunker with overlap, breaking on
     * whitespace where possible so we never split mid-word.
     *
     * @return list<string>
     */
    public function chunk(string $text, int $size, int $overlap): array
    {
        $text = trim(preg_replace("/\s+/u", ' ', $text));
        if ($text === '') return [];
        if ($size < 200) $size = 200;
        if ($overlap < 0) $overlap = 0;
        if ($overlap >= $size) $overlap = (int) ($size / 4);

        $chunks = [];
        $len = mb_strlen($text);
        $start = 0;
        while ($start < $len) {
            $end = min($len, $start + $size);
            if ($end < $len) {
                // Walk back to the nearest whitespace within the last
                // 100 chars so chunks land on word boundaries.
                $window = mb_substr($text, $start, $end - $start);
                $lastSpace = mb_strrpos($window, ' ');
                if ($lastSpace !== false && $lastSpace > $size - 100) {
                    $end = $start + $lastSpace;
                }
            }
            $piece = trim(mb_substr($text, $start, $end - $start));
            if ($piece !== '') $chunks[] = $piece;
            if ($end >= $len) break;
            $start = max($end - $overlap, $start + 1);
        }
        return $chunks;
    }
}
