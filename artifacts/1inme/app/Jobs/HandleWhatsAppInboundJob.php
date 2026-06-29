<?php

namespace App\Jobs;

use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\UserFile;
use App\Services\AI\WhisperService;
use App\Services\WhatsApp\WhatsAppAgentService;
use App\Services\WhatsApp\WhatsAppCloudApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Processes a single inbound WhatsApp message off the request cycle
 * (Task #2759): downloads any media, transcribes voice notes via
 * Whisper, vaults images/files, then runs the WhatsApp agent loop.
 *
 * The webhook controller answers Meta with 200 immediately and queues
 * one of these per message so a slow model turn never trips Meta's
 * delivery timeout (which would otherwise cause retries / duplicates).
 */
class HandleWhatsAppInboundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    /**
     * @param string               $from    sender WhatsApp phone (digits)
     * @param array<string,mixed>  $message a single WhatsApp `messages[]` entry
     */
    public function __construct(private string $from, private array $message)
    {
    }

    public function handle(WhatsAppCloudApi $cloud, WhatsAppAgentService $agent, WhisperService $whisper): void
    {
        $type = (string) ($this->message['type'] ?? '');
        $text = '';
        $pending = [];

        // Resolve the sender up front so media we vault is owned by the
        // right account. Unknown numbers still flow through so the agent
        // can send the "I don't recognise this number" reply.
        $user = LinkedIdentifier::resolveUser('phone', $this->from);

        try {
            switch ($type) {
                case 'text':
                    $text = (string) ($this->message['text']['body'] ?? '');
                    break;

                case 'image':
                    $text = (string) ($this->message['image']['caption'] ?? '');
                    if ($user) {
                        $file = $this->vaultMedia($cloud, $user, (string) ($this->message['image']['id'] ?? ''), 'image');
                        if ($file) {
                            $pending[] = ['kind' => 'image', 'user_file_id' => $file->id, 'url' => $file->url, 'name' => $file->original_name];
                        }
                    }
                    break;

                case 'document':
                    $text = (string) ($this->message['document']['caption'] ?? '');
                    if ($user) {
                        $file = $this->vaultMedia(
                            $cloud,
                            $user,
                            (string) ($this->message['document']['id'] ?? ''),
                            'file',
                            (string) ($this->message['document']['filename'] ?? ''),
                        );
                        if ($file) {
                            $pending[] = ['kind' => 'file', 'user_file_id' => $file->id, 'url' => $file->url, 'name' => $file->original_name];
                        }
                    }
                    break;

                case 'audio':
                case 'voice':
                    // Voice note: transcribe to text so it becomes the instruction.
                    if ($user) {
                        $mediaId = (string) ($this->message[$type]['id'] ?? '');
                        $upload = $this->downloadAsUpload($cloud, $mediaId, 'audio');
                        if ($upload !== null) {
                            try {
                                $res = $whisper->transcribe($user, $upload);
                                $text = (string) ($res['text'] ?? '');
                            } catch (\Throwable $e) {
                                Log::warning('WhatsApp voice transcription failed: ' . $e->getMessage());
                                $cloud->sendText($this->from, "I couldn't transcribe that voice note. Try typing your request instead.");
                                return;
                            } finally {
                                @unlink($upload->getRealPath());
                            }
                        }
                    }
                    break;

                default:
                    // Unsupported (location, contacts, stickers, reactions…).
                    $cloud->sendText($this->from, "I can work with text, links, images, files and voice notes. Send me one of those and tell me what to create.");
                    return;
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp inbound media handling failed: ' . $e->getMessage());
        }

        $agent->handle($this->from, $text, $pending);
    }

    /** Download + vault an inbound media item, returning the UserFile or null. */
    private function vaultMedia(WhatsAppCloudApi $cloud, $user, string $mediaId, string $kind, string $filename = ''): ?UserFile
    {
        if ($mediaId === '') return null;

        $meta = $cloud->getMediaUrl($mediaId);
        if (!$meta) return null;

        $bytes = $cloud->downloadMedia($meta['url']);
        if ($bytes === null) return null;

        $mime = (string) ($meta['mime'] ?? 'application/octet-stream');
        $ext  = $this->extensionFor($mime, $filename);
        $name = $filename !== '' ? $filename : ('whatsapp-' . $kind . '-' . now()->format('Ymd-His') . '.' . $ext);

        $tmp = tempnam(sys_get_temp_dir(), 'wa_');
        if ($tmp === false) return null;
        file_put_contents($tmp, $bytes);

        try {
            $upload = new UploadedFile($tmp, $name, $mime, null, true);
            return UserFile::createFromUpload($upload, $user, [
                'enforce_allowlist' => false,
                'upload_key'        => 'link.file_share',
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media vault failed: ' . $e->getMessage());
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Download an inbound media item to a temp file wrapped as an
     * UploadedFile with a correct extension (so downstream consumers like
     * Whisper can infer the audio/file format from the name). Caller is
     * responsible for unlinking the temp path.
     */
    private function downloadAsUpload(WhatsAppCloudApi $cloud, string $mediaId, string $kind): ?UploadedFile
    {
        if ($mediaId === '') return null;

        $meta = $cloud->getMediaUrl($mediaId);
        if (!$meta) return null;

        $bytes = $cloud->downloadMedia($meta['url']);
        if ($bytes === null) return null;

        $mime = (string) ($meta['mime'] ?? 'application/octet-stream');
        $name = 'whatsapp-' . $kind . '-' . now()->format('Ymd-His') . '.' . $this->extensionFor($mime, '');

        $tmp = tempnam(sys_get_temp_dir(), 'wa_');
        if ($tmp === false) return null;
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name, $mime, null, true);
    }

    private function extensionFor(string $mime, string $filename): string
    {
        if ($filename !== '' && str_contains($filename, '.')) {
            return strtolower(Str::afterLast($filename, '.'));
        }
        return match (true) {
            str_contains($mime, 'jpeg') => 'jpg',
            str_contains($mime, 'png')  => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif')  => 'gif',
            str_contains($mime, 'pdf')  => 'pdf',
            str_contains($mime, 'ogg')  => 'ogg',
            str_contains($mime, 'mpeg') => 'mp3',
            default => 'bin',
        };
    }
}
