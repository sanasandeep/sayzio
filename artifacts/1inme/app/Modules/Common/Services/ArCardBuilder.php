<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;

/**
 * Builds the 3D AR business-card assets from a link's AR settings.
 *
 * v1 design: a flat textured "card" plane. The card art is rendered
 * server-side as a PNG (GD) using the creator's accent colour, name,
 * headline and avatar; the PNG is then embedded into a minimal valid
 * GLB (for Android scene-viewer / WebXR) and into a minimal USDZ
 * (for iOS Quick Look). The renderer page also overlays HTML hotspots
 * for tappable blocks — those go through the existing
 * `/{alias}/b/{blockId}?source=ar` redirect so analytics flows are
 * shared with the standard web experience.
 */
class ArCardBuilder
{
    public function settings(Link $link): array
    {
        $s = is_array($link->ar_settings) ? $link->ar_settings : [];
        return [
            'enabled'       => (bool) ($link->ar_enabled ?? false),
            'block_ids'     => array_values(array_filter(array_map('intval', $s['block_ids'] ?? []))),
            'headline'      => trim((string) ($s['headline'] ?? '')),
            'accent_color'  => $this->normalizeColor($s['accent_color'] ?? '#7c3aed'),
            'avatar_url'    => trim((string) ($s['avatar_url'] ?? '')) ?: null,
            'display_name'  => trim((string) ($s['display_name'] ?? ($link->title ?: $link->alias))),
            'subtitle'      => trim((string) ($s['subtitle'] ?? '')),
        ];
    }

    /** Render the card-face PNG (1024x640) from current settings. */
    public function texturePng(Link $link): string
    {
        $cfg = $this->settings($link);
        $W = 1024; $H = 640;

        if (!function_exists('imagecreatetruecolor')) {
            // Tiny 1x1 PNG fallback if GD isn't available.
            return base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAUAAarVyFEAAAAASUVORK5CYII='
            );
        }

        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        [$r, $g, $b] = $this->hexToRgb($cfg['accent_color']);
        // Background gradient (accent → dark)
        for ($y = 0; $y < $H; $y++) {
            $t = $y / max(1, $H - 1);
            $nr = (int) ($r * (1 - $t) * 0.85 + 14 * $t);
            $ng = (int) ($g * (1 - $t) * 0.85 + 16 * $t);
            $nb = (int) ($b * (1 - $t) * 0.85 + 30 * $t);
            $col = imagecolorallocate($img, $nr, $ng, $nb);
            imageline($img, 0, $y, $W, $y, $col);
        }

        // Accent stripe down the left
        $stripeColor = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, 18, $H, $stripeColor);

        // Subtle inner border
        $border = imagecolorallocatealpha($img, 255, 255, 255, 110);
        imagerectangle($img, 24, 24, $W - 24, $H - 24, $border);

        $white = imagecolorallocate($img, 255, 255, 255);
        $faint = imagecolorallocatealpha($img, 255, 255, 255, 60);

        // Composite the creator's avatar (if provided) as a circular badge
        // in the upper-left of the card, then offset the text columns to the
        // right so the layout stays readable. Failures (network, decode,
        // missing GD JPEG support) silently fall back to a text-only card.
        $avatarSize = 180;
        $avatarX = 60;
        $avatarY = 80;
        $textX = 70;
        $hasAvatar = $this->drawAvatar($img, $cfg['avatar_url'] ?? null, $avatarX, $avatarY, $avatarSize);
        if ($hasAvatar) {
            $textX = $avatarX + $avatarSize + 36;
        }

        // Use built-in font (no TTF dependency for portability)
        $name = $this->sanitize($cfg['display_name']) ?: 'Your Name';
        $headline = $this->sanitize($cfg['headline']) ?: '1INME · Link in Bio in AR';
        $subtitle = $this->sanitize($cfg['subtitle']);

        imagestring($img, 5, $textX, 100, substr($name, 0, 38), $white);
        imagestring($img, 4, $textX, 150, substr($headline, 0, 60), $faint);
        if ($subtitle !== '') {
            imagestring($img, 3, $textX, 190, substr($subtitle, 0, 80), $faint);
        }

        // 1INME wordmark bottom-right
        imagestring($img, 3, $W - 130, $H - 50, '1INME · AR', $faint);

        // URL hint bottom-left
        $aliasHint = $this->sanitize('/' . $link->alias);
        imagestring($img, 3, 70, $H - 50, $aliasHint, $white);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        return $png;
    }

    /**
     * Build a minimal valid GLB: a single textured quad in front of the
     * camera, with the card PNG as its baseColorTexture.
     */
    public function glb(Link $link): string
    {
        $png = $this->texturePng($link);
        $pngLen = strlen($png);

        // Geometry: a 1.6 x 1.0 plane centered at origin, facing +Z.
        // 4 vertices: (-0.8,-0.5,0)(0.8,-0.5,0)(0.8,0.5,0)(-0.8,0.5,0)
        // UVs:        (0,1)(1,1)(1,0)(0,0)
        // Indices:    0,1,2, 0,2,3
        $positions = pack('f*',
            -0.8, -0.5, 0.0,
             0.8, -0.5, 0.0,
             0.8,  0.5, 0.0,
            -0.8,  0.5, 0.0,
        );
        $uvs = pack('f*',
            0.0, 1.0,
            1.0, 1.0,
            1.0, 0.0,
            0.0, 0.0,
        );
        $indices = pack('v*', 0, 1, 2, 0, 2, 3);

        // Pad indices to 4-byte boundary
        $idxPad = (4 - (strlen($indices) % 4)) % 4;
        $indices .= str_repeat("\x00", $idxPad);
        $idxLenAligned = strlen($indices);

        // Pad PNG to 4-byte boundary
        $imgPad = (4 - ($pngLen % 4)) % 4;
        $pngPadded = $png . str_repeat("\x00", $imgPad);

        // Bin layout: positions | uvs | indices(padded) | png(padded)
        $bin = $positions . $uvs . $indices . $pngPadded;
        $binLen = strlen($bin);

        $posOffset = 0;
        $uvOffset  = strlen($positions);
        $idxOffset = $uvOffset + strlen($uvs);
        $imgOffset = $idxOffset + $idxLenAligned;

        $gltf = [
            'asset' => ['version' => '2.0', 'generator' => '1INME ArCardBuilder'],
            'scene' => 0,
            'scenes' => [['nodes' => [0]]],
            'nodes' => [['mesh' => 0]],
            'meshes' => [[
                'primitives' => [[
                    'attributes' => ['POSITION' => 0, 'TEXCOORD_0' => 1],
                    'indices' => 2,
                    'material' => 0,
                    'mode' => 4,
                ]],
            ]],
            'materials' => [[
                'name' => 'CardFace',
                'pbrMetallicRoughness' => [
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor' => 0.0,
                    'roughnessFactor' => 0.85,
                ],
                'doubleSided' => true,
            ]],
            'textures' => [['source' => 0, 'sampler' => 0]],
            'samplers' => [['magFilter' => 9729, 'minFilter' => 9987, 'wrapS' => 33071, 'wrapT' => 33071]],
            'images' => [['bufferView' => 3, 'mimeType' => 'image/png']],
            'accessors' => [
                ['bufferView' => 0, 'componentType' => 5126, 'count' => 4, 'type' => 'VEC3',
                    'min' => [-0.8, -0.5, 0.0], 'max' => [0.8, 0.5, 0.0]],
                ['bufferView' => 1, 'componentType' => 5126, 'count' => 4, 'type' => 'VEC2'],
                ['bufferView' => 2, 'componentType' => 5123, 'count' => 6, 'type' => 'SCALAR'],
            ],
            'bufferViews' => [
                ['buffer' => 0, 'byteOffset' => $posOffset, 'byteLength' => strlen($positions), 'target' => 34962],
                ['buffer' => 0, 'byteOffset' => $uvOffset,  'byteLength' => strlen($uvs),       'target' => 34962],
                ['buffer' => 0, 'byteOffset' => $idxOffset, 'byteLength' => 12,                  'target' => 34963],
                ['buffer' => 0, 'byteOffset' => $imgOffset, 'byteLength' => $pngLen],
            ],
            'buffers' => [['byteLength' => $binLen]],
        ];

        $json = json_encode($gltf, JSON_UNESCAPED_SLASHES);
        // Pad JSON with spaces to 4-byte boundary (GLB spec)
        $jsonPad = (4 - (strlen($json) % 4)) % 4;
        $json .= str_repeat(' ', $jsonPad);
        $jsonLen = strlen($json);

        $totalLength = 12 + 8 + $jsonLen + 8 + $binLen;

        $glb  = pack('V3', 0x46546C67, 2, $totalLength);          // 'glTF', version, length
        $glb .= pack('V', $jsonLen) . pack('V', 0x4E4F534A) . $json; // JSON chunk
        $glb .= pack('V', $binLen)  . pack('V', 0x004E4942) . $bin;  // BIN chunk
        return $glb;
    }

    /**
     * Build a minimal USDZ (uncompressed ZIP with 64-byte aligned data
     * offsets) containing model.usda + texture.png. iOS Quick Look will
     * render the textured plane; tappable hotspots are not part of the
     * USDZ in v1 (Quick Look users land on the standard biolink via the
     * "open in browser" affordance).
     */
    public function usdz(Link $link): string
    {
        $png = $this->texturePng($link);
        $usda = $this->buildUsda();

        return $this->buildUncompressedZip([
            ['name' => 'model.usda',  'data' => $usda],
            ['name' => 'texture.png', 'data' => $png],
        ]);
    }

    private function buildUsda(): string
    {
        return <<<USDA
#usda 1.0
(
    defaultPrim = "Card"
    metersPerUnit = 1
    upAxis = "Y"
)

def Xform "Card" (kind = "component")
{
    def Mesh "Plane"
    {
        int[] faceVertexCounts = [4]
        int[] faceVertexIndices = [0, 1, 2, 3]
        point3f[] points = [(-0.8, -0.5, 0), (0.8, -0.5, 0), (0.8, 0.5, 0), (-0.8, 0.5, 0)]
        texCoord2f[] primvars:st = [(0, 0), (1, 0), (1, 1), (0, 1)] (interpolation = "vertex")
        rel material:binding = </Card/CardMaterial>
    }

    def Material "CardMaterial"
    {
        token outputs:surface.connect = </Card/CardMaterial/Surface.outputs:surface>

        def Shader "Surface"
        {
            uniform token info:id = "UsdPreviewSurface"
            color3f inputs:diffuseColor.connect = </Card/CardMaterial/Tex.outputs:rgb>
            float inputs:roughness = 0.85
            float inputs:metallic = 0
            token outputs:surface
        }

        def Shader "Tex"
        {
            uniform token info:id = "UsdUVTexture"
            asset inputs:file = @texture.png@
            float2 inputs:st.connect = </Card/CardMaterial/Reader.outputs:result>
            color3f outputs:rgb
        }

        def Shader "Reader"
        {
            uniform token info:id = "UsdPrimvarReader_float2"
            token inputs:varname = "st"
            float2 outputs:result
        }
    }
}

USDA;
    }

    /**
     * Build an uncompressed ZIP where every entry's payload starts on a
     * 64-byte boundary — required by the USDZ spec.
     */
    private function buildUncompressedZip(array $files): string
    {
        $blob = '';
        $central = '';
        $count = 0;

        foreach ($files as $f) {
            $name = $f['name'];
            $data = $f['data'];
            $crc = crc32($data);
            $size = strlen($data);
            $nameLen = strlen($name);

            // Local file header is 30 + nameLen bytes; data must align to 64.
            $headerLen = 30 + $nameLen;
            $localOffset = strlen($blob);
            $afterHeader = $localOffset + $headerLen;
            $alignTo = 64;
            $padNeeded = ($alignTo - ($afterHeader % $alignTo)) % $alignTo;

            // We add the padding via the "extra field" of the local header.
            $extra = str_repeat("\x00", $padNeeded);
            $extraLen = strlen($extra);

            $localHeader = pack('VvvvvvVVVvv',
                0x04034b50,  // signature
                20,          // version needed
                0,           // flags
                0,           // method (stored)
                0, 0,        // mtime, mdate
                $crc,
                $size,
                $size,
                $nameLen,
                $extraLen
            ) . $name . $extra;

            $blob .= $localHeader . $data;

            $central .= pack('VvvvvvvVVVvvvvvVV',
                0x02014b50,  // signature
                20, 20, 0, 0,
                0, 0,
                $crc,
                $size,
                $size,
                $nameLen,
                0,    // extra (central) length
                0,    // comment length
                0,    // disk
                0,    // internal attrs
                0,    // external attrs
                $localOffset
            ) . $name;
            $count++;
        }

        $centralOffset = strlen($blob);
        $centralSize = strlen($central);

        $eocd = pack('VvvvvVVv',
            0x06054b50,
            0, 0,
            $count, $count,
            $centralSize,
            $centralOffset,
            0
        );

        return $blob . $central . $eocd;
    }

    private function normalizeColor(?string $hex): string
    {
        $hex = trim((string) $hex);
        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $hex)) {
            return '#7c3aed';
        }
        return '#' . ltrim($hex, '#');
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function sanitize(string $s): string
    {
        // GD's built-in font is ASCII-only; strip non-printables.
        return preg_replace('/[^\x20-\x7E]+/', '', $s) ?? '';
    }

    /**
     * Composite the creator's avatar onto $img as a circular badge.
     * Returns true on success, false (and leaves $img untouched) on any
     * fetch/decode/format problem so the caller can fall back to a
     * text-only layout. Only http(s) URLs are honored to avoid SSRF
     * via file:// or other schemes.
     */
    private function drawAvatar($img, ?string $url, int $x, int $y, int $size): bool
    {
        if (!$url) return false;
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        // SSRF guard: block any host that resolves to a private/loopback/
        // link-local/reserved IP so a creator can't aim avatar_url at our
        // own internal services or cloud metadata endpoints.
        $host = $parts['host'] ?? '';
        if ($host === '' || !$this->isPublicHost($host)) {
            return false;
        }

        // Cache the fetched bytes for an hour so we don't hit the remote
        // host on every texture request (the texture endpoint is public).
        $cacheKey = 'ar:avatar:' . sha1($url);
        $bytes = null;
        try { $bytes = \Illuminate\Support\Facades\Cache::get($cacheKey); }
        catch (\Throwable $e) { /* cache backend unavailable — fetch live */ }

        if ($bytes === null) {
            try {
                $ctx = stream_context_create([
                    'http' => ['timeout' => 4, 'follow_location' => 0, 'max_redirects' => 0,
                        'header' => "User-Agent: 1INME-AR/1.0\r\n"],
                    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
                ]);
                $bytes = @file_get_contents($url, false, $ctx, 0, 4 * 1024 * 1024);
                if ($bytes === false || strlen($bytes) < 32) return false;
                try { \Illuminate\Support\Facades\Cache::put($cacheKey, $bytes, 3600); }
                catch (\Throwable $e) { /* ignore cache write failures */ }
            } catch (\Throwable $e) {
                return false;
            }
        }
        $src = @imagecreatefromstring($bytes);
        if (!$src) return false;

        $sw = imagesx($src); $sh = imagesy($src);
        // Cover-crop the source to a square before scaling so portraits keep
        // their face area instead of getting stretched.
        $sq = min($sw, $sh);
        $sx = (int) (($sw - $sq) / 2);
        $sy = (int) (($sh - $sq) / 2);

        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $sq, $sq);
        imagedestroy($src);

        // Mask to a circle by punching transparency outside the disc.
        $mask = imagecreatetruecolor($size, $size);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        $bg = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefilledrectangle($mask, 0, 0, $size, $size, $bg);
        $disc = imagecolorallocate($mask, 0, 0, 0);
        imagefilledellipse($mask, (int) ($size / 2), (int) ($size / 2), $size, $size, $disc);

        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefilledrectangle($out, 0, 0, $size, $size, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagealphablending($out, true);
        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                $mc = imagecolorat($mask, $px, $py);
                $alpha = ($mc >> 24) & 0x7F;
                if ($alpha < 64) {
                    imagesetpixel($out, $px, $py, imagecolorat($dst, $px, $py));
                }
            }
        }
        imagedestroy($mask);
        imagedestroy($dst);

        imagecopy($img, $out, $x, $y, 0, 0, $size, $size);
        // White ring around the avatar for legibility on any background.
        $ring = imagecolorallocatealpha($img, 255, 255, 255, 50);
        imageellipse($img, $x + (int) ($size / 2), $y + (int) ($size / 2), $size + 4, $size + 4, $ring);
        imagedestroy($out);
        return true;
    }

    /**
     * SSRF guard. Returns true only when *every* address the host resolves
     * to is a routable public IP. Blocks loopback, private, link-local,
     * unique-local, multicast, and reserved ranges on both IPv4 and IPv6,
     * which catches AWS/GCP metadata (169.254.169.254, fd00:ec2::254),
     * Docker bridges, and same-host services.
     */
    private function isPublicHost(string $host): bool
    {
        $host = strtolower(trim($host, "[]"));
        // Reject obviously dangerous literals up-front.
        if (in_array($host, ['localhost', 'localhost.localdomain', 'metadata', 'metadata.google.internal'], true)) {
            return false;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (!is_array($records) || count($records) === 0) return false;
            foreach ($records as $r) {
                if (!empty($r['ip'])) $ips[] = $r['ip'];
                if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            }
        }
        if (count($ips) === 0) return false;

        $publicFlags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, $publicFlags)) return false;
        }
        return true;
    }
}
