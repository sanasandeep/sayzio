<?php

namespace App\Services\ZioDigest;

use App\Modules\Common\Models\ZioDigest;
use Illuminate\Support\Facades\URL;

/**
 * Renders a Zio Digest's blocks into email-safe HTML and the short
 * WhatsApp summary. The public web page has its own Blade view; this
 * class only serves the outbound channels.
 */
class ZioDigestRenderer
{
    /**
     * Email HTML: simple, inline-styled, email-client-safe markup with a
     * "View in browser" link on top and an unsubscribe footer per recipient.
     */
    public function emailHtml(ZioDigest $digest, ?string $unsubscribeUrl = null): string
    {
        $publicUrl = $digest->publicUrl();
        $appName   = e((string) config('app.name'));

        $parts = [];
        $parts[] = '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f5f7;margin:0;padding:24px 12px;">';
        $parts[] = '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">';
        $parts[] = '<div style="padding:10px 24px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;text-align:center;">'
            . 'Having trouble viewing this? <a href="' . e($publicUrl) . '" style="color:#2563eb;">View it in your browser</a>.</div>';

        // Zio Digest logo (admin-updatable, absolute URL so email clients load it).
        $parts[] = '<div style="padding:20px 24px 4px;text-align:center;background:#ffffff;">'
            . '<img src="' . e(ZioDigestBranding::logoAbsoluteUrl()) . '" alt="Zio Digest" width="220" style="display:inline-block;max-width:220px;width:100%;height:auto;"></div>';

        if ($digest->lead_image) {
            $parts[] = '<img src="' . e($digest->lead_image) . '" alt="" style="display:block;width:100%;height:auto;">';
        }

        $parts[] = '<div style="padding:28px 24px;">';
        $parts[] = '<h1 style="margin:0 0 12px;font-size:24px;color:#111827;">' . e($digest->title) . '</h1>';
        if ($digest->summary) {
            $parts[] = '<p style="margin:0 0 20px;font-size:15px;color:#4b5563;">' . nl2br(e($digest->summary)) . '</p>';
        }

        foreach ((array) $digest->blocks as $block) {
            $parts[] = $this->emailBlock((array) $block);
        }

        $parts[] = '<div style="margin-top:28px;text-align:center;">'
            . '<a href="' . e($publicUrl) . '" style="display:inline-block;padding:10px 22px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;">Read the full digest</a></div>';
        $parts[] = '</div>';

        $footer = e($appName) . ' &middot; You received this because you have a ' . $appName . ' account.';
        if ($unsubscribeUrl) {
            $footer .= ' <a href="' . e($unsubscribeUrl) . '" style="color:#6b7280;text-decoration:underline;">Unsubscribe from digests</a>.';
        }
        $parts[] = '<div style="padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">' . $footer . '</div>';
        $parts[] = '</div></div>';

        return implode('', $parts);
    }

    private function emailBlock(array $block): string
    {
        $type = (string) ($block['type'] ?? '');
        $text = (string) ($block['text'] ?? '');
        $url  = (string) ($block['url'] ?? '');

        switch ($type) {
            case 'heading':
                return '<h2 style="margin:24px 0 8px;font-size:19px;color:#111827;">' . e($text) . '</h2>';
            case 'text':
                return '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#374151;">' . nl2br(e($text)) . '</p>';
            case 'image':
                if ($url === '') return '';
                $html = '<img src="' . e($url) . '" alt="' . e((string) ($block['alt'] ?? '')) . '" style="display:block;max-width:100%;height:auto;border-radius:8px;margin:14px 0 4px;">';
                if (!empty($block['caption'])) {
                    $html .= '<p style="margin:0 0 14px;font-size:12px;color:#9ca3af;">' . e((string) $block['caption']) . '</p>';
                }
                return $html;
            case 'video':
            case 'embed':
                if ($url === '') return '';
                $label = trim((string) ($block['title'] ?? '')) !== '' ? (string) $block['title']
                    : ($type === 'video' ? 'Watch the video' : 'View the post');
                return '<p style="margin:0 0 14px;font-size:15px;">'
                    . ($type === 'video' ? '&#9654;&#65039; ' : '&#128279; ')
                    . '<a href="' . e($url) . '" style="color:#2563eb;">' . e($label) . '</a></p>';
            case 'link':
                if ($url === '') return '';
                $title = trim((string) ($block['title'] ?? '')) !== '' ? (string) $block['title'] : $url;
                $html = '<div style="margin:0 0 14px;padding:12px 16px;border:1px solid #e5e7eb;border-radius:8px;">'
                    . '<a href="' . e($url) . '" style="color:#2563eb;font-size:15px;font-weight:bold;text-decoration:none;">' . e($title) . '</a>';
                if (!empty($block['description'])) {
                    $html .= '<div style="font-size:13px;color:#6b7280;margin-top:4px;">' . e((string) $block['description']) . '</div>';
                }
                return $html . '</div>';
        }

        return '';
    }

    /** Short WhatsApp summary message with the public link. */
    public function whatsappMessage(ZioDigest $digest): string
    {
        $appName = (string) config('app.name');
        $lines = ['*' . $digest->title . '*'];
        if (is_string($digest->summary) && trim($digest->summary) !== '') {
            $lines[] = mb_substr(trim($digest->summary), 0, 600);
        }
        $lines[] = 'Read the full digest: ' . $digest->publicUrl();
        $lines[] = '— ' . $appName;

        return implode("\n\n", $lines);
    }

    /** Signed per-user unsubscribe URL embedded in digest emails. */
    public static function unsubscribeUrl(int $userId, int $digestId): string
    {
        return URL::signedRoute('site.digest.unsubscribe', ['user' => $userId, 'digest' => $digestId]);
    }
}
