<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\User;
use App\Services\AI\AiMindIngestor;
use App\Services\AI\OpenAiService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class AiMindIngestorDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function ingestor(): AiMindIngestor
    {
        return new AiMindIngestor(app(OpenAiService::class));
    }

    protected function makeSource(string $disk, string $path, string $ext): AiMindSource
    {
        $user = User::create([
            'name' => 'Tester', 'email' => 'tester+'.uniqid().'@example.com',
            'password' => 'x', 'status' => 'active', 'role' => 'user',
        ]);
        $mind = AiMind::create([
            'user_id' => $user->id,
            'name'    => 'Test Mind',
            'slug'    => 'test-mind-' . uniqid(),
        ]);
        return AiMindSource::create([
            'mind_id'       => $mind->id,
            'type'          => AiMindSource::TYPE_DOCUMENT,
            'title'         => "Test.{$ext}",
            'storage_disk'  => $disk,
            'storage_path'  => $path,
            'mime'          => $ext === 'pdf' ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function test_docx_extraction_preserves_headings_lists_and_tables(): void
    {
        Storage::fake('local');

        $word    = new PhpWord();
        $section = $word->addSection();
        $word->addTitleStyle(1, ['bold' => true, 'size' => 18]);
        $section->addTitle('Quarterly Report', 1);
        $section->addText('Intro paragraph about the quarter.');
        $section->addListItem('Apples');
        $section->addListItem('Bananas');
        $section->addListItem('Cherries');
        $table = $section->addTable();
        $row   = $table->addRow();
        $row->addCell()->addText('Region');
        $row->addCell()->addText('Sales');
        $row2 = $table->addRow();
        $row2->addCell()->addText('North');
        $row2->addCell()->addText('1234');

        $tmp = tempnam(sys_get_temp_dir(), 'mind-test-') . '.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($tmp);
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        Storage::disk('local')->put('docs/test.docx', $bytes);
        $source = $this->makeSource('local', 'docs/test.docx', 'docx');

        $text = $this->ingestor()->extractText($source);

        $this->assertStringContainsString('Quarterly Report', $text);
        $this->assertStringContainsString('#', $text, 'heading marker present');
        $this->assertStringContainsString('Intro paragraph', $text);
        $this->assertStringContainsString('- Apples', $text);
        $this->assertStringContainsString('- Bananas', $text);
        $this->assertStringContainsString('- Cherries', $text);
        $this->assertStringContainsString('| Region | Sales |', $text);
        $this->assertStringContainsString('| North | 1234 |', $text);
    }

    public function test_pdf_extraction_pulls_real_text(): void
    {
        Storage::fake('local');

        $dom = new Dompdf();
        $dom->loadHtml('<html><body><h1>Welcome</h1><p>The quick brown fox jumps over the lazy dog.</p><p>Second paragraph for testing extraction.</p></body></html>');
        $dom->setPaper('A4');
        $dom->render();
        $pdfBytes = $dom->output();

        Storage::disk('local')->put('docs/test.pdf', $pdfBytes);
        $source = $this->makeSource('local', 'docs/test.pdf', 'pdf');

        $text = $this->ingestor()->extractText($source);

        $this->assertStringContainsString('Welcome', $text);
        $this->assertStringContainsString('quick brown fox', $text);
        $this->assertStringContainsString('Second paragraph', $text);
    }

    public function test_image_only_pdf_surfaces_ocr_required_message(): void
    {
        Storage::fake('local');

        // A minimal valid PDF with one blank page, no fonts and no
        // text streams — simulates a scanned/image-only page after
        // the parser has confirmed the structure.
        $bytes = $this->buildFontlessPdf();
        Storage::disk('local')->put('docs/scan.pdf', $bytes);
        $source = $this->makeSource('local', 'docs/scan.pdf', 'pdf');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/OCR/i');
        $this->ingestor()->extractText($source);
    }

    public function test_docx_extraction_handles_numbered_list_runs(): void
    {
        Storage::fake('local');

        $word    = new PhpWord();
        $section = $word->addSection();
        $section->addListItemRun()->addText('First step');
        $section->addListItemRun()->addText('Second step');
        $section->addListItemRun()->addText('Third step');

        $tmp = tempnam(sys_get_temp_dir(), 'mind-test-') . '.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($tmp);
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        Storage::disk('local')->put('docs/numbered.docx', $bytes);
        $source = $this->makeSource('local', 'docs/numbered.docx', 'docx');

        $text = $this->ingestor()->extractText($source);

        $this->assertStringContainsString('- First step', $text);
        $this->assertStringContainsString('- Second step', $text);
        $this->assertStringContainsString('- Third step', $text);
    }

    /** Builds a syntactically-valid PDF with one empty page and no fonts. */
    protected function buildFontlessPdf(): string
    {
        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >>";

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objs as $i => $body) {
            $offsets[$i] = strlen($out);
            $out .= "{$i} 0 obj\n{$body}\nendobj\n";
        }
        $xrefOffset = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        foreach ($objs as $i => $_) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer << /Size " . (count($objs) + 1) . " /Root 1 0 R >>\n";
        $out .= "startxref\n{$xrefOffset}\n%%EOF\n";
        return $out;
    }
}
