<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\PublicAnnouncements;

/**
 * Public, CORS-open JSON feed of active marketing/guest announcements, consumed
 * by the standalone React marketing site (1inme.com) — mirroring the blog feed
 * pattern. No auth: this is public content.
 */
class AnnouncementController extends Controller
{
    public function feed()
    {
        return response()->json(
            ['data' => PublicAnnouncements::publicFeed()],
            200,
            $this->corsHeaders()
        );
    }

    public function feedPreflight()
    {
        return response('', 204, $this->corsHeaders());
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Access-Control-Max-Age'       => '600',
            'Cache-Control'                => 'public, max-age=60',
        ];
    }
}
