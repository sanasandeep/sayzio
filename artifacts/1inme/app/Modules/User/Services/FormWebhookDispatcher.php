<?php

namespace App\Modules\User\Services;

use App\Modules\User\Controllers\FormController;
use App\Modules\User\Models\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class FormWebhookDispatcher
{
    public function send(Form $form, array $hook, array $payload, int $index, bool $test = false): array
    {
        $result = ['success' => false, 'status_code' => null, 'message' => 'Destination blocked: use a public HTTP(S) webhook.'];
        if (app(FormController::class)->isSafeWebhookUrl($hook['url'] ?? '')) {
            try {
                $headers = [];
                if (! empty($hook['header_key']) && ! empty($hook['header_value'])) {
                    $key = preg_replace('/[\r\n\t:\s]+/', '-', trim($hook['header_key']));
                    if ($key) {
                        $headers[$key] = preg_replace('/[\r\n]+/', ' ', trim($hook['header_value']));
                    }
                }
                $method = strtolower($hook['method'] ?? 'post');
                if (! in_array($method, ['post', 'put', 'get'], true)) {
                    $method = 'post';
                }
                $response = Http::withHeaders($headers)->withoutRedirecting()->timeout(10)->{$method}($hook['url'], $payload);
                $result = ['success' => $response->successful(), 'status_code' => $response->status(), 'message' => $response->successful() ? 'Delivered' : 'Destination returned HTTP '.$response->status()];
            } catch (\Throwable $e) {
                $result['message'] = 'Delivery failed: connection timeout or unreachable destination.';
            }
        }
        if (Schema::hasTable('form_webhook_deliveries')) {
            DB::table('form_webhook_deliveries')->insert($result + ['form_id' => $form->id, 'hook_index' => $index, 'is_test' => $test, 'created_at' => now()]);
        }

        return $result;
    }
}
