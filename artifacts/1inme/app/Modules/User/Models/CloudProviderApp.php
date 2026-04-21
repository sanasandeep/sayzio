<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CloudProviderApp extends Model
{
    use BelongsToWorkspace;

    public const PROVIDERS = ['google_drive', 'dropbox', 'onedrive'];

    public const PROVIDER_LABELS = [
        'google_drive' => 'Google Drive',
        'dropbox'      => 'Dropbox',
        'onedrive'     => 'OneDrive',
    ];

    public const PROVIDER_ICONS = [
        'google_drive' => 'fab fa-google-drive',
        'dropbox'      => 'fab fa-dropbox',
        'onedrive'     => 'fab fa-microsoft',
    ];

    protected $fillable = [
        'workspace_id', 'provider', 'client_id',
        'client_secret_encrypted', 'redirect_uri', 'enabled',
    ];

    protected $casts = [
        'enabled'                 => 'boolean',
        'client_secret_encrypted' => 'encrypted',
    ];

    public static function isKnownProvider(string $p): bool
    {
        return in_array($p, self::PROVIDERS, true);
    }

    public function label(): string
    {
        return self::PROVIDER_LABELS[$this->provider] ?? $this->provider;
    }

    public function maskedSecret(): string
    {
        $s = (string) $this->client_secret_encrypted;
        if ($s === '') return '';
        return strlen($s) > 6 ? substr($s, 0, 3) . str_repeat('•', 8) . substr($s, -3) : str_repeat('•', 8);
    }

    public function isConfigured(): bool
    {
        return $this->enabled && filled($this->client_id) && filled($this->client_secret_encrypted);
    }
}
