<?php

namespace App\Modules\User\Support;

/**
 * Registry of every QR-code content type supported by the QR Studio.
 * Each entry knows how to convert its payload into the raw string that
 * gets encoded into the QR. Validation rules are also returned so the
 * controller can validate consistently.
 */
class QrCodeTypeRegistry
{
    public static function types(): array
    {
        return [
            'text'     => ['label' => 'Text',        'icon' => 'fa-paragraph',          'group' => 'basic'],
            'url'      => ['label' => 'URL',         'icon' => 'fa-link',               'group' => 'basic'],
            'phone'    => ['label' => 'Phone',       'icon' => 'fa-phone',              'group' => 'contact'],
            'sms'      => ['label' => 'SMS',         'icon' => 'fa-comment-sms',        'group' => 'contact'],
            'email'    => ['label' => 'Email',       'icon' => 'fa-envelope',           'group' => 'contact'],
            'whatsapp' => ['label' => 'WhatsApp',    'icon' => 'fa-brands fa-whatsapp', 'group' => 'contact'],
            'facetime' => ['label' => 'Facetime',    'icon' => 'fa-video',              'group' => 'contact'],
            'location' => ['label' => 'Location',    'icon' => 'fa-location-dot',       'group' => 'utility'],
            'wifi'     => ['label' => 'WiFi',        'icon' => 'fa-wifi',               'group' => 'utility'],
            'event'    => ['label' => 'Event',       'icon' => 'fa-calendar-day',       'group' => 'utility'],
            'vcard'    => ['label' => 'vCard',       'icon' => 'fa-id-card',            'group' => 'utility'],
            'crypto'   => ['label' => 'Crypto',      'icon' => 'fa-brands fa-bitcoin',  'group' => 'payments'],
            'paypal'   => ['label' => 'PayPal',      'icon' => 'fa-brands fa-paypal',   'group' => 'payments'],
            'upi'      => ['label' => 'UPI Payment', 'icon' => 'fa-indian-rupee-sign',  'group' => 'payments'],
            'epc'      => ['label' => 'EPC Payment', 'icon' => 'fa-euro-sign',          'group' => 'payments'],
            'pix'      => ['label' => 'PIX Payment', 'icon' => 'fa-money-bill-transfer','group' => 'payments'],
        ];
    }

    public static function rulesFor(string $type): array
    {
        return match ($type) {
            'text'     => ['text' => 'required|string|max:1500'],
            'url'      => ['url' => 'required|url|max:2048'],
            'phone'    => ['number' => 'required|string|max:32'],
            'sms'      => ['number' => 'required|string|max:32', 'message' => 'nullable|string|max:500'],
            'email'    => ['email' => 'required|email|max:200', 'subject' => 'nullable|string|max:200', 'body' => 'nullable|string|max:1000'],
            'whatsapp' => ['number' => 'required|string|max:32', 'message' => 'nullable|string|max:500'],
            'facetime' => ['contact' => 'required|string|max:200'],
            'location' => ['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180', 'label' => 'nullable|string|max:120'],
            'wifi'     => ['ssid' => 'required|string|max:32', 'password' => 'nullable|string|max:64', 'encryption' => 'required|in:WPA,WEP,nopass', 'hidden' => 'sometimes|boolean'],
            'event'    => ['title' => 'required|string|max:200', 'start' => 'required|date', 'end' => 'nullable|date', 'location' => 'nullable|string|max:200', 'description' => 'nullable|string|max:500'],
            'vcard'    => ['first_name' => 'required|string|max:60', 'last_name' => 'nullable|string|max:60', 'organization' => 'nullable|string|max:120', 'title' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:32', 'email' => 'nullable|email|max:200', 'website' => 'nullable|url|max:200', 'address' => 'nullable|string|max:200'],
            'crypto'   => ['currency' => 'required|in:bitcoin,ethereum,litecoin,dogecoin', 'address' => 'required|string|max:128', 'amount' => 'nullable|numeric|min:0', 'label' => 'nullable|string|max:60'],
            'paypal'   => ['username' => 'required|string|max:60', 'amount' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3'],
            'upi'      => ['vpa' => ['required', 'string', 'max:120', 'regex:/^[\w.\-]+@[\w.\-]+$/'], 'name' => 'required|string|max:60', 'amount' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:60'],
            'epc'      => ['name' => 'required|string|max:70', 'iban' => 'required|string|max:34', 'bic' => 'nullable|string|max:11', 'amount' => 'nullable|numeric|min:0', 'remittance' => 'nullable|string|max:140'],
            'pix'      => ['key' => 'required|string|max:77', 'merchant_name' => 'required|string|max:25', 'merchant_city' => 'required|string|max:15', 'amount' => 'nullable|numeric|min:0', 'txid' => 'nullable|string|max:25', 'description' => 'nullable|string|max:50'],
            default    => [],
        };
    }

    /**
     * Convert a validated payload into the raw string that becomes the QR contents.
     */
    public static function buildPayloadString(string $type, array $p): string
    {
        return match ($type) {
            'text'     => (string) ($p['text'] ?? ''),
            'url'      => (string) ($p['url'] ?? ''),
            'phone'    => 'tel:' . self::sanitizePhone($p['number']),
            'sms'      => 'SMSTO:' . self::sanitizePhone($p['number']) . ':' . ($p['message'] ?? ''),
            'email'    => 'mailto:' . $p['email']
                          . self::queryString(['subject' => $p['subject'] ?? null, 'body' => $p['body'] ?? null]),
            'whatsapp' => 'https://wa.me/' . preg_replace('/\D+/', '', $p['number'])
                          . (!empty($p['message']) ? '?text=' . rawurlencode($p['message']) : ''),
            'facetime' => 'facetime:' . (filter_var($p['contact'], FILTER_VALIDATE_EMAIL) ? $p['contact'] : self::sanitizePhone($p['contact'])),
            'location' => sprintf('geo:%s,%s?q=%s,%s%s',
                            $p['lat'], $p['lng'], $p['lat'], $p['lng'],
                            !empty($p['label']) ? '(' . rawurlencode($p['label']) . ')' : ''),
            'wifi'     => self::buildWifi($p),
            'event'    => self::buildEvent($p),
            'vcard'    => self::buildVcard($p),
            'crypto'   => self::buildCrypto($p),
            'paypal'   => 'https://paypal.me/' . ltrim($p['username'], '@')
                          . (!empty($p['amount']) ? '/' . rtrim(rtrim(number_format((float) $p['amount'], 2, '.', ''), '0'), '.') . ($p['currency'] ?? '') : ''),
            'upi'      => 'upi://pay' . self::queryString([
                            'pa' => $p['vpa'], 'pn' => $p['name'],
                            'am' => $p['amount'] ?? null, 'cu' => 'INR',
                            'tn' => $p['note'] ?? null,
                          ]),
            'epc'      => self::buildEpc($p),
            'pix'      => self::buildPix($p),
            default    => '',
        };
    }

    // ---------- helpers ----------

    private static function sanitizePhone(string $n): string
    {
        $n = preg_replace('/[^\d+]/', '', $n);
        return $n;
    }

    private static function queryString(array $pairs): string
    {
        $pairs = array_filter($pairs, fn($v) => $v !== null && $v !== '');
        if (!$pairs) return '';
        return '?' . http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
    }

    private static function buildWifi(array $p): string
    {
        $esc = fn($s) => addcslashes((string) $s, '\\;,:"');
        $hidden = !empty($p['hidden']) ? 'true' : 'false';
        $pwdPart = $p['encryption'] === 'nopass' ? '' : 'P:' . $esc($p['password'] ?? '') . ';';
        return 'WIFI:S:' . $esc($p['ssid']) . ';T:' . $p['encryption'] . ';' . $pwdPart . 'H:' . $hidden . ';;';
    }

    private static function buildEvent(array $p): string
    {
        $fmt = fn($d) => $d ? gmdate('Ymd\THis\Z', strtotime($d)) : '';
        $lines = ["BEGIN:VEVENT", "SUMMARY:" . self::vEsc($p['title'])];
        $lines[] = "DTSTART:" . $fmt($p['start']);
        if (!empty($p['end'])) $lines[] = "DTEND:" . $fmt($p['end']);
        if (!empty($p['location'])) $lines[] = "LOCATION:" . self::vEsc($p['location']);
        if (!empty($p['description'])) $lines[] = "DESCRIPTION:" . self::vEsc($p['description']);
        $lines[] = "END:VEVENT";
        return implode("\n", $lines);
    }

    private static function buildVcard(array $p): string
    {
        $lines = ["BEGIN:VCARD", "VERSION:3.0"];
        $first = $p['first_name'] ?? ''; $last = $p['last_name'] ?? '';
        $lines[] = "N:" . self::vEsc($last) . ';' . self::vEsc($first);
        $lines[] = "FN:" . self::vEsc(trim("$first $last"));
        if (!empty($p['organization'])) $lines[] = "ORG:" . self::vEsc($p['organization']);
        if (!empty($p['title']))        $lines[] = "TITLE:" . self::vEsc($p['title']);
        if (!empty($p['phone']))        $lines[] = "TEL;TYPE=CELL:" . self::sanitizePhone($p['phone']);
        if (!empty($p['email']))        $lines[] = "EMAIL:" . $p['email'];
        if (!empty($p['website']))      $lines[] = "URL:" . $p['website'];
        if (!empty($p['address']))      $lines[] = "ADR:;;" . self::vEsc($p['address']);
        $lines[] = "END:VCARD";
        return implode("\n", $lines);
    }

    private static function vEsc(string $s): string
    {
        return str_replace(["\r\n", "\n", ",", ";"], ["\\n", "\\n", "\\,", "\\;"], $s);
    }

    private static function buildCrypto(array $p): string
    {
        $scheme = $p['currency'];
        $params = self::queryString([
            'amount' => $p['amount'] ?? null,
            'label'  => $p['label'] ?? null,
        ]);
        return "$scheme:" . $p['address'] . $params;
    }

    /**
     * EPC069-12 v2 SEPA Credit Transfer. Exactly 11 fields separated by \n,
     * UTF-8, total payload max 331 bytes. Reference: European Payments Council
     * guideline. Truncates the unstructured remittance progressively to fit.
     */
    private static function buildEpc(array $p): string
    {
        $name       = mb_substr($p['name'], 0, 70);
        $iban       = preg_replace('/\s+/', '', strtoupper($p['iban']));
        $bic        = mb_substr($p['bic'] ?? '', 0, 11);
        $amount     = !empty($p['amount']) ? 'EUR' . number_format((float) $p['amount'], 2, '.', '') : '';
        $remittance = mb_substr($p['remittance'] ?? '', 0, 140);

        $build = function (string $rem) use ($name, $iban, $bic, $amount): string {
            return implode("\n", [
                'BCD', '002', '1', 'SCT',
                $bic, $name, $iban, $amount, '', '', $rem,
            ]);
        };

        $payload = $build($remittance);
        // Enforce 331-byte spec cap by trimming the only field with give: remittance.
        while (strlen($payload) > 331 && mb_strlen($remittance) > 0) {
            $remittance = mb_substr($remittance, 0, mb_strlen($remittance) - 1);
            $payload = $build($remittance);
        }
        return $payload;
    }

    /**
     * Brazilian PIX "Copia e Cola" payload (EMVCo BR Code v01).
     * EMVCo TLV length is BYTE length, not character count, so we use strlen()
     * throughout. Free-text fields (merchant name/city/description) are
     * transliterated to ASCII to match scanner expectations and keep length
     * semantics unambiguous. CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF).
     */
    private static function buildPix(array $p): string
    {
        $tlv = fn(string $id, string $value) => $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
        $ascii = function (string $s, int $maxBytes): string {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t === false) $t = preg_replace('/[^\x20-\x7E]/', '', $s);
            return substr($t, 0, $maxBytes);
        };

        // Merchant Account Information (id 26): the PIX key itself is restricted
        // (CPF/email/phone/random UUID), all of which are ASCII by definition.
        $mai = $tlv('00', 'BR.GOV.BCB.PIX') . $tlv('01', $p['key']);
        if (!empty($p['description'])) {
            $mai .= $tlv('02', $ascii((string) $p['description'], 50));
        }

        $payload  = $tlv('00', '01');                       // Payload Format Indicator
        $payload .= $tlv('26', $mai);                       // Merchant Account Information
        $payload .= $tlv('52', '0000');                     // Merchant Category Code
        $payload .= $tlv('53', '986');                      // Transaction Currency (BRL)
        if (!empty($p['amount'])) {
            $payload .= $tlv('54', number_format((float) $p['amount'], 2, '.', ''));
        }
        $payload .= $tlv('58', 'BR');                       // Country Code
        $payload .= $tlv('59', $ascii((string) $p['merchant_name'], 25));
        $payload .= $tlv('60', $ascii((string) $p['merchant_city'], 15));

        $txid = !empty($p['txid']) ? $ascii((string) $p['txid'], 25) : '***';
        $payload .= $tlv('62', $tlv('05', $txid));          // Additional Data Field

        $payload .= '6304';                                 // CRC tag + length placeholder
        $payload .= strtoupper(self::crc16Ccitt($payload));
        return $payload;
    }

    private static function crc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return str_pad(dechex($crc), 4, '0', STR_PAD_LEFT);
    }
}
