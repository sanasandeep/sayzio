<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\PublicAnnouncements;
use Illuminate\Http\Request;

/**
 * Admin screen to author public announcement banners ("public notify"),
 * targeted independently to each audience (marketing pages, guests, logged-in
 * users, and the user dashboard). Backed by the app-settings store via
 * {@see PublicAnnouncements}.
 */
class AnnouncementsController extends Controller
{
    public function index()
    {
        return view('admin.announcements.index', [
            'announcements' => PublicAnnouncements::all(),
            'audiences'     => PublicAnnouncements::AUDIENCES,
        ]);
    }

    public function update(Request $request)
    {
        $audiences = array_keys(PublicAnnouncements::AUDIENCES);

        $data = $request->validate([
            'announcements'                  => ['nullable', 'array'],
            'announcements.*.enabled'        => ['nullable', 'boolean'],
            'announcements.*.message'        => ['nullable', 'string', 'max:280'],
            'announcements.*.link_url'       => ['nullable', 'string', 'max:1000', 'regex:#^https?://#i'],
            'announcements.*.link_label'     => ['nullable', 'string', 'max:60'],
        ]);

        $input = [];
        foreach ($audiences as $audience) {
            $input[$audience] = $data['announcements'][$audience] ?? [];
        }

        PublicAnnouncements::save($input);

        return back()->with('success', 'Announcements saved.');
    }
}
