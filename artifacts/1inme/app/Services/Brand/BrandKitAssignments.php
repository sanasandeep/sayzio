<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use Illuminate\Support\Facades\DB;

class BrandKitAssignments
{
    public const LINK_FIELDS = ['font_family', 'font_color', 'button_color', 'block_theme'];

    public function deleteKit(BrandKit $kit): void
    {
        DB::transaction(function () use ($kit) {
            foreach ($this->links($kit) as $link) {
                $this->detach($link);
            }
            foreach ($this->qrCodes($kit) as $qr) {
                $design = $qr->design ?? [];
                unset($design['brand_identity']);
                $qr->update(['design' => $design]);
            }
            $kit->delete();
        });
    }

    public function revision(BrandKit $kit): string
    {
        return hash('sha256', json_encode($kit->config));
    }

    public function assign(BrandKit $kit, Link $link, string $mode = 'follow', array $overrideFields = []): void
    {
        abort_unless((int) $kit->workspace_id === (int) $link->workspace_id, 403);
        abort_if(! empty($kit->config['archived_at']), 422, 'Restore this kit before assigning it.');
        $settings = $link->settings ?? [];
        $bio = $settings['biolink'] ?? [];
        $overrides = [];
        foreach (array_intersect(self::LINK_FIELDS, $overrideFields) as $field) {
            $overrides[$field] = $bio[$field] ?? null;
        }
        if ($link->isBiolinkFamily()) {
            app(AiBrandKitService::class)->applyToBiolink($kit, $link);
            $settings = $link->fresh()->settings ?? [];
            foreach ($overrides as $field => $value) {
                $settings['biolink'][$field] = $value;
            }
        }
        $settings['brand_identity'] = ['kit_id' => $kit->id, 'mode' => $overrides ? 'custom' : $mode, 'overrides' => $overrides, 'revision' => $this->revision($kit), 'applied_at' => now()->toIso8601String()];
        $link->forceFill(['settings' => $settings])->save();
    }

    public function detach(Link $link): void
    {
        $settings = $link->settings ?? [];
        unset($settings['brand_identity']);
        $link->forceFill(['settings' => $settings])->save();
    }

    public function links(BrandKit $kit)
    {
        return Link::where('user_id', $kit->user_id)->where('settings->brand_identity->kit_id', $kit->id)->get();
    }

    public function publish(BrandKit $kit, array $ids): int
    {
        $links = $this->links($kit)->whereIn('id', $ids);
        $count = 0;
        foreach ($links as $link) {
            $identity = $link->settings['brand_identity'] ?? [];
            $this->assign($kit, $link, $identity['mode'] ?? 'follow', array_keys($identity['overrides'] ?? []));
            $count++;
        }

        return $count;
    }

    public const QR_FIELDS = ['fg_color', 'bg_color', 'corner_square_color', 'corner_dot_color'];

    public function assignQr(BrandKit $kit, QrCode $qr, string $mode = 'follow', array $fields = []): void
    {
        abort_unless((int) $kit->workspace_id === (int) $qr->workspace_id, 403);
        abort_if(! empty($kit->config['archived_at']), 422, 'Restore this kit first.');
        $old = $qr->design ?? [];
        $overrides = array_intersect_key($old, array_flip(array_intersect(self::QR_FIELDS, $fields)));
        app(AiBrandKitService::class)->applyToQr($kit, $qr);
        $design = array_replace($qr->fresh()->design ?? [], $overrides);
        $design['brand_identity'] = ['kit_id' => $kit->id, 'mode' => $overrides ? 'custom' : $mode, 'overrides' => $overrides, 'revision' => $this->revision($kit)];
        $qr->forceFill(['design' => $design])->save();
    }

    public function qrCodes(BrandKit $kit)
    {
        return QrCode::where('user_id', $kit->user_id)->where('design->brand_identity->kit_id', $kit->id)->get();
    }

    public function captureQrOverrides(QrCode $qr, array $design): array
    {
        $identity = $qr->design['brand_identity'] ?? null;
        if (! $identity) {
            return $design;
        }
        foreach (self::QR_FIELDS as $key) {
            if (($qr->design[$key] ?? null) !== ($design[$key] ?? null)) {
                $identity['overrides'][$key] = $design[$key] ?? null;
                $identity['mode'] = 'custom';
            }
        }
        $design['brand_identity'] = $identity;

        return $design;
    }

    public function captureOverrides(Link $link, array $settings): array
    {
        $identity = $settings['brand_identity'] ?? null;
        if (! $identity) {
            return $settings;
        }
        $before = $link->settings['biolink'] ?? [];
        $after = $settings['biolink'] ?? [];
        foreach (self::LINK_FIELDS as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $identity['overrides'][$key] = $after[$key] ?? null;
                $identity['mode'] = 'custom';
            }
        }
        $settings['brand_identity'] = $identity;

        return $settings;
    }
}
