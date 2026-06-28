<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\ElevenLabsService;
use App\Services\AI\OpenAiService;
use App\Services\AI\QrArtService;
use App\Services\AI\WhisperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin settings for the AI Engine:
 *   - Master toggle (ai.enabled).
 *   - OpenAI API key (Crypt-encrypted at rest, masked in UI).
 *   - Enabled models with per-model credit rates per 1k tokens.
 *   - Wallet-coin → AI-credit conversion rate.
 *   - Buyable AI credit packs (label / credits / wallet cost).
 *
 * The rate model is "credits per 1k tokens" so admins can mirror
 * OpenAI's per-1k pricing directly. Per-call cost is computed by the
 * shared OpenAiService from the response's token usage.
 */
class AiEngineController extends Controller
{
    public function edit()
    {
        $featureStatus = [];
        foreach (AiEngineSettings::FEATURES as $f) {
            $featureStatus[$f] = AiEngineSettings::featureModelStatus($f);
        }

        return view('admin.ai-engine.edit', [
            'enabled'         => AiEngineSettings::isEnabled(),
            'maskedKey'       => AiEngineSettings::maskedOpenAiKey(),
            'hasKey'          => AiEngineSettings::openAiKey() !== null,
            'models'          => AiEngineSettings::models(),
            'features'        => AiEngineSettings::FEATURES,
            'featureModels'   => AiEngineSettings::featureModels(),
            'featureStatus'   => $featureStatus,
            'defaultFeatureModel' => AiEngineSettings::DEFAULT_FEATURE_MODEL,
            'featureModelHistory' => AiEngineSettings::recentFeatureModelChanges(20),

            // Voice Assistant
            'voiceEnabled'         => AiEngineSettings::voiceEnabled(),
            'voicePlans'           => AiEngineSettings::voiceEnabledPlans(),
            'maskedWhisperKey'     => AiEngineSettings::maskedWhisperKey(),
            'hasWhisperKey'        => AiEngineSettings::whisperKey() !== null,
            'whisperModel'         => AiEngineSettings::whisperModel(),
            'voiceGptModel'        => AiEngineSettings::voiceGptModel(),
            'maskedElevenKey'      => AiEngineSettings::maskedElevenLabsKey(),
            'hasElevenKey'         => AiEngineSettings::elevenLabsKey() !== null,
            'elevenVoiceId'        => AiEngineSettings::elevenLabsVoiceId(),
            'elevenModel'          => AiEngineSettings::elevenLabsModel(),
            'voicePriceStt'        => AiEngineSettings::voiceSttCoinsPerMinute(),
            'voicePriceTts'        => AiEngineSettings::voiceTtsCoinsPer1kChars(),
            'voiceRateLimit'       => AiEngineSettings::voiceTurnsPerMinute(),

            // AI Artistic QR (Replicate)
            'maskedReplicateKey'    => AiEngineSettings::maskedReplicateKey(),
            'hasReplicateKey'       => AiEngineSettings::replicateKey() !== null,
            'hasStoredReplicateKey' => AiEngineSettings::hasStoredReplicateKey(),
            'qrArtCoins'            => AiEngineSettings::qrArtCoinsPerGeneration(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'                       => 'nullable|boolean',
            'openai_api_key'                => 'nullable|string|max:255',
            'clear_openai_api_key'          => 'nullable|boolean',
            'models'                        => 'array',
            'models.*.name'                 => 'required_with:models|string|max:64',
            'models.*.kind'                 => 'required_with:models|in:chat,embedding',
            'models.*.enabled'              => 'nullable|boolean',
            'models.*.in_coins_per_1k'      => 'nullable|numeric|min:0|max:100000',
            'models.*.out_coins_per_1k'     => 'nullable|numeric|min:0|max:100000',
            'feature_models'                => 'array',
            'feature_models.*'              => 'nullable|string|max:64',

            // Voice Assistant
            'voice_enabled'                 => 'nullable|boolean',
            'voice_plans'                   => 'nullable|array',
            'voice_plans.*'                 => 'nullable|string|max:64',
            'whisper_api_key'               => 'nullable|string|max:255',
            'clear_whisper_api_key'         => 'nullable|boolean',
            'whisper_model'                 => 'nullable|string|max:64',
            'voice_gpt_model'               => 'nullable|string|max:64',
            'elevenlabs_api_key'            => 'nullable|string|max:255',
            'clear_elevenlabs_api_key'      => 'nullable|boolean',
            'elevenlabs_voice_id'           => 'nullable|string|max:64',
            'elevenlabs_model'              => 'nullable|string|max:64',
            'voice_price_stt'               => 'nullable|numeric|min:0|max:100000',
            'voice_price_tts'               => 'nullable|numeric|min:0|max:100000',
            'voice_rate_per_minute'         => 'nullable|integer|min:1|max:600',

            // AI Artistic QR (Replicate)
            'replicate_api_key'             => 'nullable|string|max:255',
            'clear_replicate_api_key'       => 'nullable|boolean',
            'qr_art_coins'                  => 'nullable|integer|min:1|max:100000',
        ]);

        // Validate that no AI feature ends up pointing at a missing,
        // disabled, or non-chat model after the new payload is applied.
        // We mirror what setModels()/setFeatureModels() will persist so
        // the check matches the post-save reality, then merge with the
        // currently-stored values for anything the form omitted (the
        // form supports partial saves of just the toggle, etc.).
        $effectiveModels = array_key_exists('models', $data)
            ? array_values(array_filter(array_map(function ($m) {
                if (!is_array($m) || empty($m['name'])) return null;
                return [
                    'name'    => trim((string) $m['name']),
                    'kind'    => in_array(($m['kind'] ?? 'chat'), ['chat', 'embedding'], true) ? $m['kind'] : 'chat',
                    'enabled' => (bool) ($m['enabled'] ?? false),
                ];
            }, $data['models'])))
            : AiEngineSettings::models();

        // When feature_models is posted we mirror setFeatureModels(): only
        // non-empty string entries are persisted, anything missing/empty
        // falls back to DEFAULT_FEATURE_MODEL via featureModels(). This
        // way validation sees exactly what featureModels() will return
        // after the save, including partial payloads.
        if (array_key_exists('feature_models', $data)) {
            $effectiveFeatureModels = [];
            foreach (AiEngineSettings::FEATURES as $f) {
                $val = $data['feature_models'][$f] ?? null;
                $effectiveFeatureModels[$f] = (is_string($val) && trim($val) !== '')
                    ? trim($val)
                    : AiEngineSettings::DEFAULT_FEATURE_MODEL;
            }
        } else {
            $effectiveFeatureModels = AiEngineSettings::featureModels();
        }

        $featureErrors = [];
        foreach (AiEngineSettings::FEATURES as $f) {
            $status = AiEngineSettings::featureModelStatusFor($f, $effectiveFeatureModels, $effectiveModels);
            if (!$status['ok']) {
                $featureErrors["feature_models.{$f}"] = [
                    ucfirst($f) . ': ' . $status['message'],
                ];
            }
        }
        if ($featureErrors) {
            throw ValidationException::withMessages($featureErrors);
        }

        AiEngineSettings::setEnabled($request->boolean('enabled'));

        if ($request->boolean('clear_openai_api_key')) {
            AiEngineSettings::setOpenAiKey(null);
        } elseif (!empty($data['openai_api_key'])) {
            AiEngineSettings::setOpenAiKey($data['openai_api_key']);
        }

        // Models: update only when the form actually submitted them
        // (admin may save just the toggle without re-posting the table).
        if (array_key_exists('models', $data)) {
            AiEngineSettings::setModels(array_map(function ($m) {
                $m['enabled'] = (bool) ($m['enabled'] ?? false);
                return $m;
            }, $data['models']));
        }
        if (array_key_exists('feature_models', $data)) {
            $admin = Auth::guard('admin')->user() ?: $request->user();
            AiEngineSettings::setFeatureModels(
                $data['feature_models'],
                $admin?->id,
                $admin?->name ?? $admin?->email
            );
        }

        // Voice Assistant settings
        if ($request->has('voice_enabled')) {
            AiEngineSettings::setVoiceEnabled($request->boolean('voice_enabled'));
        }
        if (array_key_exists('voice_plans', $data)) {
            AiEngineSettings::setVoiceEnabledPlans(is_array($data['voice_plans']) ? $data['voice_plans'] : []);
        }
        if ($request->boolean('clear_whisper_api_key')) {
            AiEngineSettings::setWhisperKey(null);
        } elseif (!empty($data['whisper_api_key'])) {
            AiEngineSettings::setWhisperKey($data['whisper_api_key']);
        }
        if (array_key_exists('whisper_model', $data)) {
            AiEngineSettings::setWhisperModel($data['whisper_model']);
        }
        if (array_key_exists('voice_gpt_model', $data)) {
            AiEngineSettings::setVoiceGptModel($data['voice_gpt_model']);
        }
        if ($request->boolean('clear_elevenlabs_api_key')) {
            AiEngineSettings::setElevenLabsKey(null);
        } elseif (!empty($data['elevenlabs_api_key'])) {
            AiEngineSettings::setElevenLabsKey($data['elevenlabs_api_key']);
        }
        if (array_key_exists('elevenlabs_voice_id', $data)) {
            AiEngineSettings::setElevenLabsVoiceId($data['elevenlabs_voice_id']);
        }
        if (array_key_exists('elevenlabs_model', $data)) {
            AiEngineSettings::setElevenLabsModel($data['elevenlabs_model']);
        }
        if (isset($data['voice_price_stt'])) {
            AiEngineSettings::setVoiceSttCoinsPerMinute((float) $data['voice_price_stt']);
        }
        if (isset($data['voice_price_tts'])) {
            AiEngineSettings::setVoiceTtsCoinsPer1kChars((float) $data['voice_price_tts']);
        }
        if (isset($data['voice_rate_per_minute'])) {
            AiEngineSettings::setVoiceTurnsPerMinute((int) $data['voice_rate_per_minute']);
        }

        // AI Artistic QR (Replicate)
        if ($request->boolean('clear_replicate_api_key')) {
            AiEngineSettings::setReplicateKey(null);
        } elseif (!empty($data['replicate_api_key'])) {
            AiEngineSettings::setReplicateKey($data['replicate_api_key']);
        }
        if (isset($data['qr_art_coins'])) {
            AiEngineSettings::setQrArtCoinsPerGeneration((int) $data['qr_art_coins']);
        }

        return redirect()->route('admin.ai-engine.edit')
            ->with('success', 'AI Engine settings saved.');
    }

    /**
     * Unmetered "Test connection" action for the AI Engine page. Pings
     * OpenAI with a tiny 1-token chat call using either the key just typed
     * into the form (preferred) or the stored key, and reports the result
     * as JSON for inline rendering. No coins are charged and the AI Engine
     * does not need to be enabled.
     */
    public function testConnection(Request $request, OpenAiService $openai)
    {
        $typed = trim((string) $request->input('openai_api_key', ''));

        // Ping an enabled chat model when one exists so the test mirrors
        // real usage; otherwise fall back to the default feature model.
        $enabledChat = collect(AiEngineSettings::models())
            ->first(fn ($m) => ($m['kind'] ?? 'chat') === 'chat' && !empty($m['enabled']));
        $model = $enabledChat['name'] ?? AiEngineSettings::DEFAULT_FEATURE_MODEL;

        return response()->json(
            $openai->testKey($typed !== '' ? $typed : null, $model)
        );
    }

    /**
     * Unmetered "Test connection" action for the Whisper (STT) key. Lists
     * OpenAI models using the key just typed into the form (preferred) or
     * the stored Whisper key (which itself falls back to the main OpenAI
     * key, like the transcribe runtime), and reports the result as JSON
     * for inline rendering. No coins are charged and the AI Engine does
     * not need to be enabled.
     */
    public function testWhisperConnection(Request $request, WhisperService $whisper)
    {
        $typed = trim((string) $request->input('whisper_api_key', ''));

        return response()->json(
            $whisper->testKey($typed !== '' ? $typed : null)
        );
    }

    /**
     * Unmetered "Test connection" action for the ElevenLabs (TTS) key.
     * Hits the cheap GET /v1/voices endpoint using the key just typed into
     * the form (preferred) or the stored key, and reports the result as
     * JSON for inline rendering. No coins are charged and the AI Engine
     * does not need to be enabled.
     */
    public function testElevenLabsConnection(Request $request, ElevenLabsService $eleven)
    {
        $typed = trim((string) $request->input('elevenlabs_api_key', ''));

        return response()->json(
            $eleven->testKey($typed !== '' ? $typed : null)
        );
    }

    /**
     * Unmetered "Test connection" action for the Replicate (AI Artistic QR)
     * token. Hits the cheap authenticated GET /v1/account endpoint using the
     * token just typed into the form (preferred) or the stored/env token, and
     * reports the result as JSON for inline rendering. No coins are charged and
     * the AI Engine does not need to be enabled.
     */
    public function testReplicateConnection(Request $request, QrArtService $qrArt)
    {
        $typed = trim((string) $request->input('replicate_api_key', ''));

        return response()->json(
            $qrArt->testToken($typed !== '' ? $typed : null)
        );
    }
}
