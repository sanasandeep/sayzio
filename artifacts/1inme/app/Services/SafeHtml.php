<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class SafeHtml
{
    /**
     * Allowed tags and the attributes they may keep.
     */
    private const ALLOWED = [
        'a' => ['href', 'title'],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'p' => [],
        'br' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h3' => [],
        'h4' => [],
        'blockquote' => [],
        'code' => [],
    ];

    /**
     * Render a section body that may contain a safe subset of HTML and/or
     * a tiny markdown-ish syntax. Anything outside the allowlist is escaped.
     */
    public static function render(?string $input): string
    {
        $input = (string) $input;
        if ($input === '') {
            return '';
        }

        // Look like HTML? If not, apply markdown-lite first.
        if (!preg_match('/<[a-z][^>]*>/i', $input)) {
            $input = self::markdownLite($input);
        } else {
            // Still allow inline **bold**, *italic*, [text](url) inside HTML.
            $input = self::inlineMarkdown($input);
        }

        // Wrap in a container so DOMDocument has a single root and preserves
        // UTF-8 (the hack with mb_convert_encoding handles encoding).
        $wrapped = '<?xml encoding="utf-8" ?><div>' . $input . '</div>';

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) {
            return nl2br(e($input));
        }

        self::sanitizeNode($root);

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return $html;
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        // Walk a snapshot of children so we can mutate.
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!($child instanceof DOMElement)) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (!array_key_exists($tag, self::ALLOWED)) {
                // Replace disallowed element with its (sanitized) children.
                self::sanitizeNode($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Strip non-allowed attributes.
            $allowedAttrs = self::ALLOWED[$tag];
            $attrsSnapshot = [];
            foreach ($child->attributes as $attr) {
                $attrsSnapshot[] = $attr->nodeName;
            }
            foreach ($attrsSnapshot as $attrName) {
                if (!in_array(strtolower($attrName), $allowedAttrs, true)) {
                    $child->removeAttribute($attrName);
                }
            }

            if ($tag === 'a') {
                $href = $child->getAttribute('href');
                if (!self::isSafeUrl($href)) {
                    $child->removeAttribute('href');
                } else {
                    // External links: open in new tab safely.
                    if (preg_match('#^https?://#i', $href)) {
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener noreferrer nofollow');
                    }
                }
            }

            self::sanitizeNode($child);
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $url = trim($url);
        if (preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $url)) {
            return !preg_match('/^javascript:/i', $url);
        }
        return false;
    }

    /**
     * Convert a tiny markdown-ish syntax (no HTML present) to HTML:
     *   - bullet lists (lines starting with "- " or "* ")
     *   - ordered lists (lines starting with "1. ")
     *   - blank-line paragraph breaks
     *   - inline **bold**, *italic*, [text](url)
     */
    private static function markdownLite(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        $out = [];
        $listType = null; // 'ul' | 'ol' | null
        $paragraph = [];

        $flushParagraph = function () use (&$paragraph, &$out) {
            if ($paragraph) {
                $body = implode('<br>', array_map(fn($l) => self::inlineMarkdown(e($l)), $paragraph));
                $out[] = '<p>' . $body . '</p>';
                $paragraph = [];
            }
        };
        $closeList = function () use (&$listType, &$out) {
            if ($listType) {
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $closeList();
                    $out[] = '<ul>';
                    $listType = 'ul';
                }
                $out[] = '<li>' . self::inlineMarkdown(e($m[1])) . '</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.*)$/', $trim, $m)) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $closeList();
                    $out[] = '<ol>';
                    $listType = 'ol';
                }
                $out[] = '<li>' . self::inlineMarkdown(e($m[1])) . '</li>';
                continue;
            }
            $closeList();
            $paragraph[] = $line;
        }
        $flushParagraph();
        $closeList();

        return implode('', $out);
    }

    /**
     * Apply inline markdown (**bold**, *italic*, [text](url)) to a string
     * that is otherwise treated as already-safe HTML/text.
     */
    private static function inlineMarkdown(string $s): string
    {
        // Links: [text](url)
        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
            $url = $m[2];
            if (!self::isSafeUrl($url)) {
                return e($m[1]);
            }
            $extra = preg_match('#^https?://#i', $url)
                ? ' target="_blank" rel="noopener noreferrer nofollow"'
                : '';
            return '<a href="' . e($url) . '"' . $extra . '>' . $m[1] . '</a>';
        }, $s);

        // Bold **text**
        $s = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $s);
        // Italic *text* (avoid matching ** which is already consumed)
        $s = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $s);

        return $s;
    }
}
