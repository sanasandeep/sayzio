<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormVisitTracker
{
    public function create(Form $form, Request $r): ?string
    {
        if (! Schema::hasTable('form_visits') || preg_match('/bot|crawler|spider|headless/i', (string) $r->userAgent())) {
            return null;
        }
        $ua = strtolower((string) $r->userAgent());
        $id = DB::table('form_visits')->insertGetId(['form_id' => $form->id, 'device' => preg_match('/ipad|tablet/', $ua) ? 'Tablet' : (preg_match('/mobi|android|iphone/', $ua) ? 'Mobile' : 'Desktop'), 'source' => mb_substr((string) parse_url($r->headers->get('referer', ''), PHP_URL_HOST), 0, 255) ?: 'Direct', 'campaign' => mb_substr(is_string($r->query('utm_campaign')) ? $r->query('utm_campaign') : '', 0, 120), 'created_at' => now(), 'updated_at' => now()]);
        $value = $form->id.'.'.$id.'.'.now()->addDay()->timestamp;

        return $value.'.'.hash_hmac('sha256', $value, config('app.key'));
    }

    public function resolve(Form $form, ?string $token): ?object
    {
        if (! $token || ! Schema::hasTable('form_visits')) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 4 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2]) || (int) $parts[0] !== $form->id || (int) $parts[2] < time()) {
            return null;
        }
        if (! hash_equals(hash_hmac('sha256', implode('.', array_slice($parts, 0, 3)), config('app.key')), $parts[3])) {
            return null;
        }

        return DB::table('form_visits')->where('form_id', $form->id)->where('id', $parts[1])->first();
    }

    public function attach(Form $form, ?string $token, int $submission): void
    {
        $v = $this->resolve($form, $token);
        if ($v && Schema::hasColumn('form_visits', 'submission_id')) {
            DB::table('form_visits')->where('id', $v->id)->whereNull('submission_id')->update(['submission_id' => $submission]);
        }
    }

    public function completeSubmission(Form $form, int $submission): void
    {
        if (! Schema::hasTable('form_visits') || ! Schema::hasColumn('form_visits', 'submission_id')) {
            return;
        }$v = DB::table('form_visits')->where('form_id', $form->id)->where('submission_id', $submission)->first();
        if ($v && ! $v->completed_at) {
            DB::table('form_visits')->where('id', $v->id)->update(['completed_at' => now(), 'duration_seconds' => $v->started_at ? min(86400, (int) now()->diffInSeconds(Carbon::parse($v->started_at), true)) : null, 'updated_at' => now()]);
        }
    }

    public function complete(Form $form, ?string $token): void
    {
        $v = $this->resolve($form, $token);
        if (! $v || $v->completed_at) {
            return;
        }
        DB::table('form_visits')->where('id', $v->id)->whereNull('completed_at')->update(['completed_at' => now(), 'duration_seconds' => $v->started_at ? min(86400, max(0, (int) now()->diffInSeconds(Carbon::parse($v->started_at), true))) : null, 'updated_at' => now()]);
    }
}
