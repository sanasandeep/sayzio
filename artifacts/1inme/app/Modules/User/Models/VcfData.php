<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VcfData extends Model
{
    protected $table = 'vcf_data';

    protected $fillable = [
        'link_id',
        // Name
        'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'nickname',
        // Avatar
        'photo_path',
        // Org
        'organization', 'department', 'title', 'role',
        // Dates
        'birthday', 'anniversary',
        // Legacy single-value (kept for back-compat with older rows + the
        // sortable indexed columns)
        'email', 'phone', 'phone_work', 'website',
        'street', 'city', 'state', 'zip', 'country',
        // Multi-value
        'emails', 'phones', 'urls', 'addresses', 'social_profiles',
        // Note
        'note',
    ];

    protected $casts = [
        'emails'          => 'array',
        'phones'          => 'array',
        'urls'            => 'array',
        'addresses'       => 'array',
        'social_profiles' => 'array',
        'birthday'        => 'date:Y-m-d',
        'anniversary'     => 'date:Y-m-d',
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * Hydrated multi-value lists. If the JSON column has entries, use
     * them; otherwise fall back to the legacy single-value column so
     * older records keep rendering without a data migration.
     *
     * @return array<int, array{label:string,value:string}>
     */
    public function emailList(): array
    {
        $list = is_array($this->emails) ? array_values(array_filter($this->emails, fn($e) => !empty($e['value']))) : [];
        if (!empty($list)) return $list;
        return $this->email ? [['label' => 'Email', 'value' => $this->email]] : [];
    }

    public function phoneList(): array
    {
        $list = is_array($this->phones) ? array_values(array_filter($this->phones, fn($p) => !empty($p['value']))) : [];
        if (!empty($list)) return $list;
        $out = [];
        if ($this->phone)      $out[] = ['label' => 'Mobile', 'value' => $this->phone];
        if ($this->phone_work) $out[] = ['label' => 'Work',   'value' => $this->phone_work];
        return $out;
    }

    public function urlList(): array
    {
        $list = is_array($this->urls) ? array_values(array_filter($this->urls, fn($u) => !empty($u['value']))) : [];
        if (!empty($list)) return $list;
        return $this->website ? [['label' => 'Website', 'value' => $this->website]] : [];
    }

    public function addressList(): array
    {
        $list = is_array($this->addresses) ? array_values(array_filter($this->addresses, function ($a) {
            return !empty($a['street']) || !empty($a['city']) || !empty($a['state']) || !empty($a['zip']) || !empty($a['country']);
        })) : [];
        if (!empty($list)) return $list;
        if ($this->street || $this->city || $this->state || $this->zip || $this->country) {
            return [[
                'label'   => 'Work',
                'street'  => (string) $this->street,
                'city'    => (string) $this->city,
                'state'   => (string) $this->state,
                'zip'     => (string) $this->zip,
                'country' => (string) $this->country,
            ]];
        }
        return [];
    }

    public function socialList(): array
    {
        return is_array($this->social_profiles)
            ? array_values(array_filter($this->social_profiles, fn($s) => !empty($s['value'])))
            : [];
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->prefix, $this->first_name, $this->middle_name, $this->last_name, $this->suffix,
        ])));
    }

    /**
     * Resolve a public URL for the avatar image. Returns null when no
     * photo is set or the file is missing on disk.
     */
    public function photoUrl(): ?string
    {
        if (!$this->photo_path) return null;
        if (preg_match('~^https?://~i', $this->photo_path)) return $this->photo_path;
        return Storage::disk('public')->exists($this->photo_path)
            ? Storage::disk('public')->url($this->photo_path)
            : null;
    }

    /**
     * Generate a vCard 3.0-compliant document with every field populated
     * by the user. Multi-value fields emit one TEL/EMAIL/URL/ADR/IMPP
     * line per entry; the photo is base64-embedded so the .vcf file is
     * a single self-contained download even if the recipient is offline.
     */
    public function toVcf(): string
    {
        $out = [];
        $out[] = 'BEGIN:VCARD';
        $out[] = 'VERSION:3.0';
        $out[] = 'PRODID:-//Sayzio//Link Manager//EN';

        // N:Last;First;Middle;Prefix;Suffix
        $out[] = 'N:' . self::esc($this->last_name) . ';' . self::esc($this->first_name) . ';' . self::esc($this->middle_name) . ';' . self::esc($this->prefix) . ';' . self::esc($this->suffix);
        $out[] = 'FN:' . self::esc($this->fullName() ?: ($this->first_name ?: ''));
        if ($this->nickname)     $out[] = 'NICKNAME:' . self::esc($this->nickname);
        if ($this->organization || $this->department) {
            $out[] = 'ORG:' . self::esc($this->organization) . ($this->department ? ';' . self::esc($this->department) : '');
        }
        if ($this->title) $out[] = 'TITLE:' . self::esc($this->title);
        if ($this->role)  $out[] = 'ROLE:'  . self::esc($this->role);

        foreach ($this->emailList() as $e) {
            $out[] = 'EMAIL;TYPE=' . self::vcfType($e['label'] ?? 'INTERNET') . ':' . self::esc($e['value']);
        }
        foreach ($this->phoneList() as $p) {
            $out[] = 'TEL;TYPE=' . self::vcfType($p['label'] ?? 'VOICE') . ':' . self::esc($p['value']);
        }
        foreach ($this->urlList() as $u) {
            $out[] = 'URL;TYPE=' . self::vcfType($u['label'] ?? 'WORK') . ':' . self::esc($u['value']);
        }
        foreach ($this->addressList() as $a) {
            $out[] = 'ADR;TYPE=' . self::vcfType($a['label'] ?? 'WORK') . ':;;'
                . self::esc($a['street'] ?? '') . ';'
                . self::esc($a['city'] ?? '') . ';'
                . self::esc($a['state'] ?? '') . ';'
                . self::esc($a['zip'] ?? '') . ';'
                . self::esc($a['country'] ?? '');
        }
        foreach ($this->socialList() as $s) {
            $service = (string) ($s['service'] ?? 'social');
            $val = (string) ($s['value'] ?? '');
            // X-SOCIALPROFILE is widely supported; IMPP for IM-style services.
            $out[] = 'X-SOCIALPROFILE;TYPE=' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $service)) . ':' . self::esc($val);
        }

        if ($this->birthday)    $out[] = 'BDAY:'        . $this->birthday->format('Y-m-d');
        if ($this->anniversary) $out[] = 'ANNIVERSARY:' . $this->anniversary->format('Y-m-d');

        // Photo — embed local files as base64 so the vcf is self-contained.
        if ($this->photo_path) {
            if (preg_match('~^https?://~i', $this->photo_path)) {
                $out[] = 'PHOTO;VALUE=URI:' . $this->photo_path;
            } elseif (Storage::disk('public')->exists($this->photo_path)) {
                $bytes = Storage::disk('public')->get($this->photo_path);
                $ext = strtoupper(pathinfo($this->photo_path, PATHINFO_EXTENSION) ?: 'JPEG');
                if ($ext === 'JPG') $ext = 'JPEG';
                // Fold long PHOTO lines per RFC 2425 (75 chars max per line).
                $b64 = chunk_split(base64_encode($bytes), 73, "\r\n ");
                $out[] = 'PHOTO;ENCODING=b;TYPE=' . $ext . ':' . rtrim($b64, "\r\n ");
            }
        }

        if ($this->note) $out[] = 'NOTE:' . self::esc($this->note);
        $out[] = 'REV:' . now()->format('Y-m-d\TH:i:s\Z');
        $out[] = 'END:VCARD';

        return implode("\r\n", $out) . "\r\n";
    }

    /** RFC 6350 escaping for vCard text values. */
    private static function esc(?string $v): string
    {
        if ($v === null || $v === '') return '';
        return str_replace(
            ["\\", "\n", "\r", ",", ";"],
            ['\\\\', '\\n', '', '\\,', '\\;'],
            $v,
        );
    }

    /** Map a user-entered label to a vCard TYPE token. */
    private static function vcfType(string $label): string
    {
        $map = [
            'mobile' => 'CELL', 'cell' => 'CELL', 'cellular' => 'CELL',
            'work' => 'WORK', 'office' => 'WORK', 'business' => 'WORK',
            'home' => 'HOME', 'personal' => 'HOME',
            'fax' => 'FAX', 'work fax' => 'WORK,FAX', 'home fax' => 'HOME,FAX',
            'main' => 'MAIN', 'other' => 'OTHER',
            'email' => 'INTERNET', 'work email' => 'INTERNET,WORK', 'personal email' => 'INTERNET,HOME',
            'website' => 'WORK', 'blog' => 'HOME', 'portfolio' => 'WORK',
        ];
        $key = strtolower(trim($label));
        return $map[$key] ?? strtoupper(preg_replace('/[^A-Z0-9,]/i', '', $label) ?: 'OTHER');
    }
}
