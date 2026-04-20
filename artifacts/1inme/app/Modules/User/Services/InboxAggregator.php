<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class InboxAggregator
{
    public const SOURCE_FORM = 'form_submission';
    public const SOURCE_EMAIL_SUBSCRIBE = 'email_subscribe';
    public const SOURCE_EMAIL_COLLECTOR = 'email_collector';
    public const SOURCE_PHONE_COLLECTOR = 'phone_collector';
    public const SOURCE_CONTACT_FORM = 'contact_form';
    public const SOURCE_WHATSAPP_CHANNEL = 'whatsapp_channel';
    public const SOURCE_WHATSAPP_NUMBER = 'whatsapp_number';

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_FORM => 'Form Submission',
            self::SOURCE_EMAIL_SUBSCRIBE => 'Newsletter Signup',
            self::SOURCE_EMAIL_COLLECTOR => 'Email Collector',
            self::SOURCE_PHONE_COLLECTOR => 'Phone Collector',
            self::SOURCE_CONTACT_FORM => 'Contact Form',
            self::SOURCE_WHATSAPP_CHANNEL => 'WhatsApp Channel',
            self::SOURCE_WHATSAPP_NUMBER => 'WhatsApp Number',
        ];
    }

    public function __construct(protected int $userId) {}

    public function unreadCount(): int
    {
        return Cache::remember("inbox.unread.{$this->userId}", 30, function () {
            $forms = FormSubmission::query()
                ->whereIn('form_id', Form::where('user_id', $this->userId)->pluck('id'))
                ->where('is_read', false)
                ->where('is_spam', false)
                ->count();
            $subs = Subscriber::where('user_id', $this->userId)
                ->where('is_read', false)
                ->where('is_spam', false)
                ->count();
            return $forms + $subs;
        });
    }

    public static function bustCache(int $userId): void
    {
        Cache::forget("inbox.unread.{$userId}");
    }

    /**
     * Build a paginated, filterable, normalized list combining form submissions and subscribers.
     */
    public function paginate(array $filters, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $sourceFilter = $filters['source'] ?? null;
        $formId       = $filters['form_id'] ?? null;
        $linkId       = $filters['link_id'] ?? null;
        $unreadOnly   = !empty($filters['unread']);
        $starredOnly  = !empty($filters['starred']);
        $spamOnly     = !empty($filters['spam']);
        $dateFrom     = $filters['date_from'] ?? null;
        $dateTo       = $filters['date_to'] ?? null;
        $search       = trim((string)($filters['q'] ?? ''));

        $formIds = Form::where('user_id', $this->userId)->pluck('id');

        // -------- Form submissions projection --------
        $formProjection = collect();
        $includeForms = $sourceFilter === null
            || $sourceFilter === self::SOURCE_FORM;
        if ($includeForms && !$linkId) {
            $q = FormSubmission::query()->whereIn('form_id', $formIds);
            if ($formId) $q->where('form_id', $formId);
            if ($spamOnly) $q->where('is_spam', true); else $q->where('is_spam', false);
            if ($unreadOnly) $q->where('is_read', false);
            if ($starredOnly) $q->where('is_starred', true);
            if ($dateFrom) $q->where('created_at', '>=', $dateFrom);
            if ($dateTo) $q->where('created_at', '<=', $dateTo . ' 23:59:59');
            if ($search !== '') {
                $needle = '%' . $search . '%';
                $q->where(function ($w) use ($needle) {
                    $w->whereRaw('data::text ILIKE ?', [$needle])
                      ->orWhere('ip', 'ilike', $needle);
                });
            }
            $rows = $q->select(['id', 'form_id', 'created_at', 'is_read', 'is_starred', 'is_spam'])->get();
            foreach ($rows as $r) {
                $formProjection->push([
                    'source_type' => self::SOURCE_FORM,
                    'item_id'     => $r->id,
                    'parent_id'   => $r->form_id,
                    'created_at'  => $r->created_at,
                    'is_read'     => (bool)$r->is_read,
                    'is_starred'  => (bool)$r->is_starred,
                    'is_spam'     => (bool)$r->is_spam,
                ]);
            }
        }

        // -------- Subscribers projection --------
        $subProjection = collect();
        $includeSubs = $sourceFilter === null
            || in_array($sourceFilter, [
                self::SOURCE_EMAIL_SUBSCRIBE,
                self::SOURCE_EMAIL_COLLECTOR,
                self::SOURCE_PHONE_COLLECTOR,
                self::SOURCE_CONTACT_FORM,
                self::SOURCE_WHATSAPP_CHANNEL,
                self::SOURCE_WHATSAPP_NUMBER,
            ], true);
        if ($includeSubs && !$formId) {
            $q = Subscriber::where('user_id', $this->userId);
            if ($linkId) $q->where('link_id', $linkId);
            if ($spamOnly) $q->where('is_spam', true); else $q->where('is_spam', false);
            if ($unreadOnly) $q->where('is_read', false);
            if ($starredOnly) $q->where('is_starred', true);
            if ($dateFrom) $q->where('created_at', '>=', $dateFrom);
            if ($dateTo) $q->where('created_at', '<=', $dateTo . ' 23:59:59');
            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('email', 'ilike', '%' . $search . '%')
                      ->orWhere('name', 'ilike', '%' . $search . '%')
                      ->orWhere('phone', 'ilike', '%' . $search . '%');
                });
            }
            // Coarse subscriber-type filter mapped from source
            if ($sourceFilter === self::SOURCE_EMAIL_SUBSCRIBE
                || $sourceFilter === self::SOURCE_EMAIL_COLLECTOR) {
                $q->where('type', 'email');
            } elseif ($sourceFilter === self::SOURCE_CONTACT_FORM) {
                $q->where('type', 'contact_form');
            } elseif ($sourceFilter === self::SOURCE_PHONE_COLLECTOR) {
                $q->where('type', 'phone');
            } elseif ($sourceFilter === self::SOURCE_WHATSAPP_CHANNEL) {
                $q->where('type', 'whatsapp_channel');
            } elseif ($sourceFilter === self::SOURCE_WHATSAPP_NUMBER) {
                $q->where('type', 'whatsapp_number');
            }
            $rows = $q->select(['id', 'link_id', 'block_id', 'type', 'created_at', 'subscribed_at', 'is_read', 'is_starred', 'is_spam'])->get();
            // Refine source mapping using block.type when available
            $blockTypes = $rows->pluck('block_id')->filter()->unique()->isNotEmpty()
                ? BiolinkBlock::whereIn('id', $rows->pluck('block_id')->filter()->unique())->pluck('type', 'id')
                : collect();
            foreach ($rows as $r) {
                $sourceType = $this->mapSubscriberSource($r->type, $blockTypes[$r->block_id] ?? null);
                if ($sourceFilter && $sourceFilter !== $sourceType) continue;
                $subProjection->push([
                    'source_type' => $sourceType,
                    'item_id'     => $r->id,
                    'parent_id'   => $r->link_id,
                    'created_at'  => $r->subscribed_at ?? $r->created_at,
                    'is_read'     => (bool)$r->is_read,
                    'is_starred'  => (bool)$r->is_starred,
                    'is_spam'     => (bool)$r->is_spam,
                ]);
            }
        }

        $merged = $formProjection->concat($subProjection)
            ->sortByDesc(fn($i) => optional($i['created_at'])->getTimestamp() ?? 0)
            ->values();

        $total = $merged->count();
        $pageItems = $merged->forPage($page, $perPage)->values();

        // Hydrate with full models + relations
        $formIdsOnPage = $pageItems->where('source_type', self::SOURCE_FORM)->pluck('item_id');
        $subIdsOnPage = $pageItems->where('source_type', '!=', self::SOURCE_FORM)->pluck('item_id');

        $formSubs = $formIdsOnPage->isNotEmpty()
            ? FormSubmission::with('form')->whereIn('id', $formIdsOnPage)->get()->keyBy('id')
            : collect();
        $subs = $subIdsOnPage->isNotEmpty()
            ? Subscriber::with(['link:id,alias', 'block:id,link_id,type,settings'])
                ->whereIn('id', $subIdsOnPage)->get()->keyBy('id')
            : collect();

        $hydrated = $pageItems->map(function ($i) use ($formSubs, $subs) {
            if ($i['source_type'] === self::SOURCE_FORM) {
                $row = $formSubs->get($i['item_id']);
                if (!$row) return null;
                $name = $row->data['name'] ?? $row->data['email'] ?? ('#' . $row->id);
                $preview = collect($row->data ?? [])
                    ->reject(fn($v, $k) => in_array($k, ['name', 'email']) || is_array($v))
                    ->take(2)->map(fn($v, $k) => "$k: $v")->implode(' · ');
                return (object)[
                    'source_type'  => self::SOURCE_FORM,
                    'source_label' => 'Form: ' . ($row->form?->title ?? '—'),
                    'item_id'      => $row->id,
                    'parent_id'    => $row->form_id,
                    'parent_route' => 'user.forms.submissions.show',
                    'created_at'   => $row->created_at,
                    'is_read'      => (bool)$row->is_read,
                    'is_starred'   => (bool)$row->is_starred,
                    'is_spam'      => (bool)$row->is_spam,
                    'name'         => $name,
                    'preview'      => $preview ?: ($row->data['email'] ?? 'Submission'),
                    'raw'          => $row,
                ];
            }
            $row = $subs->get($i['item_id']);
            if (!$row) return null;
            $sourceType = $this->mapSubscriberSource($row->type, $row->block?->type);
            $linkLabel = $row->link?->alias ? ('/' . $row->link->alias) : ($row->source ?? 'biolink');
            $message = is_array($row->metadata ?? null) ? trim((string)($row->metadata['message'] ?? '')) : '';
            $preview = $message !== ''
                ? \Illuminate\Support\Str::limit($message, 120)
                : (trim(implode(' · ', array_filter([$row->email, $row->phone, $row->channel_url]))) ?: '—');
            return (object)[
                'source_type'  => $sourceType,
                'source_label' => self::sourceLabels()[$sourceType] . ' on ' . $linkLabel,
                'item_id'      => $row->id,
                'parent_id'    => $row->link_id,
                'parent_route' => null,
                'created_at'   => $row->subscribed_at ?? $row->created_at,
                'is_read'      => (bool)$row->is_read,
                'is_starred'   => (bool)$row->is_starred,
                'is_spam'      => (bool)$row->is_spam,
                'name'         => $row->name ?: ($row->email ?: ($row->phone ?: ('#' . $row->id))),
                'preview'      => $preview,
                'raw'          => $row,
            ];
        })->filter()->values();

        return new LengthAwarePaginator(
            $hydrated, $total, $perPage, $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function mapSubscriberSource(?string $subscriberType, ?string $blockType): string
    {
        if ($blockType === 'email_collector') return self::SOURCE_EMAIL_COLLECTOR;
        if ($blockType === 'phone_collector') return self::SOURCE_PHONE_COLLECTOR;
        if ($blockType === 'contact_form')    return self::SOURCE_CONTACT_FORM;
        if ($blockType === 'email_subscribe') return self::SOURCE_EMAIL_SUBSCRIBE;
        if ($blockType === 'whatsapp_channel_subscribe') return self::SOURCE_WHATSAPP_CHANNEL;
        if ($blockType === 'whatsapp_number_subscribe')  return self::SOURCE_WHATSAPP_NUMBER;

        return match ($subscriberType) {
            'whatsapp_channel' => self::SOURCE_WHATSAPP_CHANNEL,
            'whatsapp_number'  => self::SOURCE_WHATSAPP_NUMBER,
            'phone'            => self::SOURCE_PHONE_COLLECTOR,
            'contact_form'     => self::SOURCE_CONTACT_FORM,
            default            => self::SOURCE_EMAIL_SUBSCRIBE,
        };
    }
}
