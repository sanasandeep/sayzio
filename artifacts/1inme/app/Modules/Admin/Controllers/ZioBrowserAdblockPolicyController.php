<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use Illuminate\Http\Request;

/**
 * Admin-mandated Zio Browser ad-block policy (Task #6453).
 *
 * Stores a versioned allow/block domain policy in the `zio_browser_adblock_policy`
 * app setting. Domains on the block list have ad blocking force-enabled in the
 * Zio Browser (unbypassable by users); domains on the allow list have ads
 * force-allowed. Every change bumps the version (which drives the public API's
 * ETag) and appends an audit entry (kept to the most recent 50).
 */
class ZioBrowserAdblockPolicyController extends Controller
{
    public const SETTING_KEY = 'zio_browser_adblock_policy';
    private const MAX_AUDIT = 50;
    private const MAX_DOMAINS = 500;

    /** Current policy with defaults applied. */
    public static function policy(): array
    {
        $raw = AppSetting::get(self::SETTING_KEY);
        $data = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);

        return [
            'version'    => max(0, (int) ($data['version'] ?? 0)),
            'allow'      => array_values(array_filter((array) ($data['allow'] ?? []), 'is_string')),
            'block'      => array_values(array_filter((array) ($data['block'] ?? []), 'is_string')),
            'updated_at' => $data['updated_at'] ?? null,
            'audit'      => array_values((array) ($data['audit'] ?? [])),
        ];
    }

    public function index()
    {
        return view('admin.zio-adblock-policy.index', ['policy' => self::policy()]);
    }

    /** Add one or many domains (newline/comma separated) to a list. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'list'    => 'required|in:allow,block',
            'domains' => 'required|string|max:20000',
        ]);

        $list = $validated['list'];
        $policy = self::policy();

        $candidates = preg_split('/[\s,]+/', strtolower($validated['domains'])) ?: [];
        $added = [];
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $domain = self::normalizeDomain($candidate);
            if ($domain === null) {
                if (trim($candidate) !== '') {
                    $skipped++;
                }
                continue;
            }
            if (in_array($domain, $policy[$list], true)) {
                continue;
            }
            if (count($policy['allow']) + count($policy['block']) + count($added) >= self::MAX_DOMAINS) {
                $skipped++;
                continue;
            }
            // Keep the lists disjoint — the latest add wins.
            $other = $list === 'allow' ? 'block' : 'allow';
            $policy[$other] = array_values(array_diff($policy[$other], [$domain]));
            $policy[$list][] = $domain;
            $added[] = $domain;
        }

        if ($added !== []) {
            $this->persist($policy, $request, 'add', $list, $added);
        }

        $message = $added === []
            ? 'No new domains were added.'
            : count($added) . ' domain' . (count($added) === 1 ? '' : 's') . ' added to the ' . $list . ' list.';
        if ($skipped > 0) {
            $message .= " {$skipped} invalid or over-limit entr" . ($skipped === 1 ? 'y was' : 'ies were') . ' skipped.';
        }

        return redirect()->route('admin.zio-adblock-policy.index')->with('success', $message);
    }

    /** Remove a domain from a list. */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'list'   => 'required|in:allow,block',
            'domain' => 'required|string|max:253',
        ]);

        $list = $validated['list'];
        $domain = self::normalizeDomain($validated['domain']);
        $policy = self::policy();

        if ($domain === null || !in_array($domain, $policy[$list], true)) {
            return redirect()->route('admin.zio-adblock-policy.index')->with('error', 'Domain not found on that list.');
        }

        $policy[$list] = array_values(array_diff($policy[$list], [$domain]));
        $this->persist($policy, $request, 'remove', $list, [$domain]);

        return redirect()->route('admin.zio-adblock-policy.index')
            ->with('success', "Removed {$domain} from the {$list} list.");
    }

    private function persist(array $policy, Request $request, string $action, string $list, array $domains): void
    {
        $policy['version'] = $policy['version'] + 1;
        $policy['updated_at'] = now()->toIso8601String();

        $audit = $policy['audit'];
        array_unshift($audit, [
            'action'  => $action,
            'list'    => $list,
            'domains' => array_values($domains),
            'admin'   => (string) ($request->user('admin')?->email ?? 'unknown'),
            'at'      => $policy['updated_at'],
        ]);
        $policy['audit'] = array_slice($audit, 0, self::MAX_AUDIT);

        AppSetting::put(self::SETTING_KEY, $policy);
    }

    /** Normalize a candidate domain; null when invalid. */
    public static function normalizeDomain(string $raw): ?string
    {
        $host = strtolower(trim($raw));
        if ($host === '') {
            return null;
        }
        // Accept full URLs too.
        if (str_contains($host, '://')) {
            $host = parse_url($host, PHP_URL_HOST) ?: $host;
        }
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $host = rtrim($host, '.');

        if (strlen($host) > 253 || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            return null;
        }

        return $host;
    }
}
