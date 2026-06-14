<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\OtpService;
use App\Services\Integrations\IntegrationKeySettings;
use App\Services\Integrations\InternalAlertDispatcher;
use App\Services\AI\AiEngineSettings;
use App\Services\Billing\GatewayManager;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Support\IntegrationConfigRegistry;
use Illuminate\Http\Request;

/**
 * Unified admin "API Keys & Plugins" hub. Closes two gaps the rest of
 * the admin already covered piecemeal:
 *
 *   1. WhatsApp Cloud API credentials — previously env-only
 *      (config/whatsapp.php), now editable and stored encrypted in
 *      app_settings (with env fallback preserved).
 *   2. Internal alert webhooks (Slack / Discord) — previously only a
 *      logging env var, now editable with real fan-out delivery.
 *
 * It also surfaces a read-only status summary for the existing
 * key-bearing systems (AI Engine, Payment Gateways, Integrations Hub)
 * and links to their dedicated editors rather than duplicating them.
 *
 * Secret-handling UX mirrors the AI Engine page: stored secrets are
 * always masked, a blank field on save leaves the stored value
 * untouched, and an explicit "remove" checkbox clears it.
 */
class ApiKeysController extends Controller
{
    public function index()
    {
        return view('admin.api-keys.index', [
            // WhatsApp
            'waStatus'        => IntegrationKeySettings::whatsappStatus(),
            'waPhoneNumberId' => IntegrationKeySettings::whatsappPhoneNumberId(),
            'waHasToken'      => IntegrationKeySettings::whatsappAccessToken() !== null,
            'waMaskedToken'   => IntegrationKeySettings::maskedWhatsappAccessToken(),
            'waTemplate'      => IntegrationKeySettings::whatsappTemplateName(),
            'waLanguage'      => IntegrationKeySettings::whatsappTemplateLanguage(),
            'waGraphVersion'  => IntegrationKeySettings::whatsappGraphVersion(),

            // Internal alerts
            'alertsStatus'      => IntegrationKeySettings::alertsStatus(),
            'alertsEnabled'     => IntegrationKeySettings::alertsEnabled(),
            'slackHasUrl'       => IntegrationKeySettings::slackWebhookUrl() !== null,
            'slackMasked'       => IntegrationKeySettings::maskedSlackWebhookUrl(),
            'discordHasUrl'     => IntegrationKeySettings::discordWebhookUrl() !== null,
            'discordMasked'     => IntegrationKeySettings::maskedDiscordWebhookUrl(),

            // Per-category alert toggles
            'alertCategories'   => collect(IntegrationKeySettings::alertCategories())
                ->map(fn ($c) => $c + ['enabled' => IntegrationKeySettings::alertCategoryEnabled($c['key'])])
                ->all(),

            // Read-only overview of the other key-bearing systems
            'overview' => $this->overview(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // WhatsApp
            'wa_phone_number_id'   => ['nullable', 'string', 'max:64'],
            'wa_access_token'      => ['nullable', 'string', 'max:1024'],
            'clear_wa_access_token' => ['nullable', 'boolean'],
            'wa_template_name'     => ['nullable', 'string', 'max:128'],
            'wa_template_language' => ['nullable', 'string', 'max:16'],
            'wa_graph_version'     => ['nullable', 'string', 'max:16'],

            // Internal alerts
            'alerts_enabled'       => ['nullable', 'boolean'],
            'slack_webhook_url'    => ['nullable', 'string', 'url', 'max:1024'],
            'clear_slack_webhook'  => ['nullable', 'boolean'],
            'discord_webhook_url'  => ['nullable', 'string', 'url', 'max:1024'],
            'clear_discord_webhook' => ['nullable', 'boolean'],
        ]);

        // ── WhatsApp ──────────────────────────────────────────────
        // Plain scalars are always written from the submitted value
        // (these aren't secrets); empty clears back to env fallback.
        IntegrationKeySettings::setWhatsappPhoneNumberId($data['wa_phone_number_id'] ?? null);
        IntegrationKeySettings::setWhatsappTemplateName($data['wa_template_name'] ?? null);
        IntegrationKeySettings::setWhatsappTemplateLanguage($data['wa_template_language'] ?? null);
        IntegrationKeySettings::setWhatsappGraphVersion($data['wa_graph_version'] ?? null);

        // Secret: blank leaves the stored value untouched; explicit
        // checkbox clears it.
        if ($request->boolean('clear_wa_access_token')) {
            IntegrationKeySettings::setWhatsappAccessToken(null);
        } elseif (!empty($data['wa_access_token'])) {
            IntegrationKeySettings::setWhatsappAccessToken($data['wa_access_token']);
        }

        // ── Internal alerts ───────────────────────────────────────
        IntegrationKeySettings::setAlertsEnabled($request->boolean('alerts_enabled'));

        if ($request->boolean('clear_slack_webhook')) {
            IntegrationKeySettings::setSlackWebhookUrl(null);
        } elseif (!empty($data['slack_webhook_url'])) {
            IntegrationKeySettings::setSlackWebhookUrl($data['slack_webhook_url']);
        }

        if ($request->boolean('clear_discord_webhook')) {
            IntegrationKeySettings::setDiscordWebhookUrl(null);
        } elseif (!empty($data['discord_webhook_url'])) {
            IntegrationKeySettings::setDiscordWebhookUrl($data['discord_webhook_url']);
        }

        // Per-category mute toggles. Always-on categories (payment) have no
        // form control and are skipped; setAlertCategoryEnabled also force-
        // pins them on defensively.
        foreach (IntegrationKeySettings::alertCategories() as $cat) {
            if ($cat['always_on']) {
                continue;
            }
            IntegrationKeySettings::setAlertCategoryEnabled(
                $cat['key'],
                $request->boolean('alert_cat_' . $cat['key']),
            );
        }

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API keys & plugin settings saved.');
    }

    /**
     * Send a real WhatsApp test message (or log it in preview mode) to a
     * supplied number through the same OtpService path real OTPs use.
     */
    public function testWhatsApp(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'test_number' => ['required', 'string', 'max:32'],
        ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp->sendWhatsApp($data['test_number'], $code);

        if (!IntegrationKeySettings::whatsappConfigured()) {
            return back()->with('info', 'WhatsApp is in preview mode — the test code was logged, not sent. Add credentials to deliver live.');
        }

        return back()->with('success', 'WhatsApp test message dispatched to ' . $data['test_number'] . '.');
    }

    /**
     * Post a sample alert to a chosen webhook to verify it works. Uses
     * the freshly-stored value (force-send, ignoring the enable toggle).
     */
    public function testAlert(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:slack,discord'],
        ]);

        $url = $data['channel'] === 'discord'
            ? IntegrationKeySettings::discordWebhookUrl()
            : IntegrationKeySettings::slackWebhookUrl();

        if (!$url) {
            return back()->with('error', 'No ' . ucfirst($data['channel']) . ' webhook URL is configured. Save one first.');
        }

        $res = InternalAlertDispatcher::sendTest(
            $data['channel'],
            $url,
            '1INME internal alert test',
            'This is a test alert from the API Keys & Plugins hub. If you can read this, the webhook works.',
            ['sent_by' => optional($request->user())->email ?? 'admin', 'time' => now()->toDateTimeString()],
        );

        if (!empty($res['ok'])) {
            return back()->with('success', ucfirst($data['channel']) . ' test alert delivered.');
        }

        return back()->with('error', ucfirst($data['channel']) . ' test alert failed: ' . ($res['error'] ?? 'unknown error') . '.');
    }

    /**
     * Read-only status summary for the existing key-bearing systems so
     * admins see everything in one place without duplicating editors.
     *
     * @return array<int,array{label:string,route:?string,status:array{label:string,tone:string}}>
     */
    private function overview(): array
    {
        // AI Engine: configured when an OpenAI key is stored.
        $aiConfigured = AiEngineSettings::openAiKey() !== null;
        $ai = [
            'label'  => 'AI Engine',
            'desc'   => 'OpenAI key, models, credit packs and voice keys.',
            'route'  => route('admin.ai-engine.edit'),
            'status' => $aiConfigured
                ? ['label' => 'Configured', 'tone' => 'green']
                : ['label' => 'Not configured', 'tone' => 'slate'],
        ];

        // Payment gateways: count how many are enabled.
        try {
            $enabled = collect(app(GatewayManager::class)->allWithSettings())
                ->filter(fn ($r) => (bool) ($r['settings']->is_enabled ?? false))
                ->count();
        } catch (\Throwable $e) {
            $enabled = 0;
        }
        $gateways = [
            'label'  => 'Payment Gateways',
            'desc'   => 'Stripe, PayPal, Razorpay, Cashfree & offline.',
            'route'  => route('admin.payment-gateways.index'),
            'status' => $enabled > 0
                ? ['label' => $enabled . ' enabled', 'tone' => 'green']
                : ['label' => 'None enabled', 'tone' => 'slate'],
        ];

        // Integrations Hub: count active email/sms provider configs.
        try {
            $kinds = array_keys(IntegrationConfigRegistry::kinds());
            $active = IntegrationConfig::query()
                ->whereIn('kind', $kinds)
                ->where('is_active', true)
                ->count();
        } catch (\Throwable $e) {
            $active = 0;
        }
        $hub = [
            'label'  => 'Integrations Hub',
            'desc'   => 'Email & SMS providers used for notifications.',
            'route'  => route('user.integrations.index'),
            'status' => $active > 0
                ? ['label' => $active . ' active', 'tone' => 'green']
                : ['label' => 'None active', 'tone' => 'slate'],
        ];

        return [$ai, $gateways, $hub];
    }
}
