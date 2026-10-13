<?php

namespace App\Services\AI;

use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class NoteSourceReader
{
    public function webpage(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        abort_unless(in_array($scheme, ['http', 'https']) && $host && empty($parts['user']) && empty($parts['pass']), 422, 'Use a public HTTP or HTTPS URL.');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        abort_unless(in_array($port, [80, 443]), 422, 'Only standard web ports are supported.');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_filter(array_merge(array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'), array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'))));
        abort_unless($ips, 422, 'Could not resolve the source host.');
        foreach ($ips as $ip) {
            abort_unless(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE), 422, 'Private or reserved network URLs are not supported.');
        }
        $bytes = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_PROXY => '', CURLOPT_RESOLVE => [$host.':'.$port.':'.implode(',', array_map(fn ($ip) => str_contains($ip, ':') ? '['.$ip.']' : $ip, $ips))], CURLOPT_USERAGENT => 'Sayzio-Notes/1.0', CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$bytes) {
            if (strlen($bytes) + strlen($chunk) > 2097152) {
                return 0;
            }$bytes .= $chunk;

            return strlen($chunk);
        }]);
        try {
            $ok = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $type = strtolower(explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '')[0]);
        } finally {
            curl_close($ch);
        }
        abort_unless($ok && $code === 200, 422, 'Source could not be read. Use a direct public URL without redirects or upload the document.');
        abort_unless(in_array($type, ['text/html', 'text/plain']), 422, 'For PDF or image URLs, use the source picker URL tab.');
        $bytes = preg_replace('/<(script|style)\\b[^>]*>[\\s\\S]*?<\\/\\1>/i', '', $bytes);

        return $this->text($bytes);
    }

    private function text(string $html): string
    {
        $html = preg_replace('/<\/(?:p|div|li|h[1-6]|section|article|tr)>|<br\s*\/?>/i', "\n", $html);

        return mb_substr(trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')), 0, 40000);
    }

    public function content($file): array
    {
        $mime = $file->getMimeType();
        if (in_array($mime, ['text/plain', 'text/html'])) {
            $text = file_get_contents($file->getRealPath());
            $text = preg_replace('/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/i', '', $text);

            return [['type' => 'text', 'text' => $this->text($text)]];
        }

        return array_map(fn ($bytes) => ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.base64_encode($bytes), 'detail' => 'high']], $this->images($file));
    }

    public function images($file): array
    {
        $paths = [$file->getRealPath()];
        $directory = null;
        try {
            if ($file->getMimeType() === 'application/pdf') {
                $directory = sys_get_temp_dir().'/form-ai-'.Str::uuid();
                mkdir($directory, 0700);
                $info = new Process(['pdfinfo', $file->getRealPath()]);
                $info->setTimeout(10);
                $info->run();
                abort_unless($info->isSuccessful() && preg_match('/Pages:\s+(\d+)/', $info->getOutput(), $m), 422, 'PDF reading is unavailable on this server; upload page images instead.');
                abort_if((int) $m[1] > 6, 422, 'Upload a PDF of at most six pages.');
                $p = new Process(['pdftoppm', '-jpeg', '-scale-to', '1024', '-f', '1', '-l', '6', $file->getRealPath(), $directory.'/page']);
                $p->setTimeout(45);
                $p->mustRun();
                $paths = glob($directory.'/page-*.jpg');
                sort($paths, SORT_NATURAL);
            }
            abort_unless(function_exists('imagecreatefromstring'), 422, 'Image processing is unavailable on this server.');
            $images = [];
            foreach ($paths as $path) {
                $bytes = file_get_contents($path);
                $size = getimagesizefromstring($bytes);
                abort_unless($size && $size[0] * $size[1] <= 30000000, 422, 'Use an image smaller than 30 megapixels.');
                $src = imagecreatefromstring($bytes);
                abort_unless($src, 422, 'Image could not be read.');
                $ratio = min(1, 1024 / max(imagesx($src), imagesy($src)));
                $dst = imagecreatetruecolor(max(1, (int) (imagesx($src) * $ratio)), max(1, (int) (imagesy($src) * $ratio)));
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefill($dst, 0, 0, $white);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), imagesx($src), imagesy($src));
                ob_start();
                imagejpeg($dst, null, 85);
                $images[] = ob_get_clean();
                imagedestroy($dst);
                imagedestroy($src);
            }

            return $images;
        } catch (ExceptionInterface $e) {
            abort(422, 'PDF reading is unavailable; upload page images instead.');
        } finally {
            if ($directory) {
                foreach (glob($directory.'/*') as $p) {
                    @unlink($p);
                }@rmdir($directory);
            }
        }
    }
}
