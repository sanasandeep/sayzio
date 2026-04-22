<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiEngineSettings;
use Illuminate\Http\Request;

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
        return view('admin.ai-engine.edit', [
            'enabled'        => AiEngineSettings::isEnabled(),
            'maskedKey'      => AiEngineSettings::maskedOpenAiKey(),
            'hasKey'         => AiEngineSettings::openAiKey() !== null,
            'models'         => AiEngineSettings::models(),
            'walletRate'     => AiEngineSettings::walletToCreditsRate(),
            'packs'          => AiEngineSettings::packs(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'                       => 'nullable|boolean',
            'openai_api_key'                => 'nullable|string|max:255',
            'clear_openai_api_key'          => 'nullable|boolean',
            'wallet_to_credits_rate'        => 'nullable|integer|min:1|max:1000000',
            'models'                        => 'array',
            'models.*.name'                 => 'required_with:models|string|max:64',
            'models.*.kind'                 => 'required_with:models|in:chat,embedding',
            'models.*.enabled'              => 'nullable|boolean',
            'models.*.in_credits_per_1k'    => 'nullable|integer|min:0',
            'models.*.out_credits_per_1k'   => 'nullable|integer|min:0',
            'packs'                         => 'array',
            'packs.*.id'                    => 'required_with:packs|string|max:32',
            'packs.*.label'                 => 'required_with:packs|string|max:64',
            'packs.*.credits'               => 'required_with:packs|integer|min:1',
            'packs.*.wallet_cost'           => 'required_with:packs|integer|min:1',
        ]);

        AiEngineSettings::setEnabled($request->boolean('enabled'));

        if ($request->boolean('clear_openai_api_key')) {
            AiEngineSettings::setOpenAiKey(null);
        } elseif (!empty($data['openai_api_key'])) {
            AiEngineSettings::setOpenAiKey($data['openai_api_key']);
        }

        if (isset($data['wallet_to_credits_rate'])) {
            AiEngineSettings::setWalletToCreditsRate((int) $data['wallet_to_credits_rate']);
        }

        // Models / packs: update only when the form actually submitted
        // them (admin may save just the toggle without re-posting tables).
        if (array_key_exists('models', $data)) {
            AiEngineSettings::setModels(array_map(function ($m) {
                $m['enabled'] = (bool) ($m['enabled'] ?? false);
                return $m;
            }, $data['models']));
        }
        if (array_key_exists('packs', $data)) {
            AiEngineSettings::setPacks($data['packs']);
        }

        return redirect()->route('admin.ai-engine.edit')
            ->with('success', 'AI Engine settings saved.');
    }
}
