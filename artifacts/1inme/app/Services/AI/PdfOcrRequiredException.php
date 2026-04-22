<?php

namespace App\Services\AI;

/**
 * Signal that a PDF carries no extractable text glyphs and needs OCR.
 * The raw bytes are passed along so the ingest pipeline can re-render
 * the pages without re-reading from storage.
 */
class PdfOcrRequiredException extends \RuntimeException
{
    public function __construct(public readonly string $pdfBytes, string $message = 'PDF requires OCR to extract text.')
    {
        parent::__construct($message);
    }
}
