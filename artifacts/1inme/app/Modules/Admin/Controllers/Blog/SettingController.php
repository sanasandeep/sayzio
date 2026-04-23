<?php

namespace App\Modules\Admin\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Services\BlogSettings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.blogs.settings.edit', [
            'settings' => BlogSettings::all(),
            'roles'    => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'approval_mode'                => 'required|in:auto,returning,manual,closed',
            'allow_guest_viewer_comments'  => 'nullable|boolean',
            'require_email'                => 'nullable|boolean',
            'spam_filter'                  => 'nullable|boolean',
            'comments_per_page'            => 'required|integer|min:5|max:200',
            'default_og_image'             => 'nullable|string|max:500',
            'hero_eyebrow'                 => 'nullable|string|max:120',
            'hero_heading'                 => 'nullable|string|max:200',
            'hero_subheading'              => 'nullable|string|max:500',
            'hero_cta_label'               => 'nullable|string|max:80',
            'hero_cta_url'                 => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'reply_role_slugs'             => 'nullable|array',
            'reply_role_slugs.*'           => 'string|max:80',
            'cta_on_pages'                 => 'nullable|array',
            'cta_on_pages.*'               => 'string|in:features,about,how-it-works,contact,faqs',
        ]);
        $data['allow_guest_viewer_comments'] = (bool) ($data['allow_guest_viewer_comments'] ?? false);
        $data['require_email']               = (bool) ($data['require_email'] ?? false);
        $data['spam_filter']                 = (bool) ($data['spam_filter'] ?? false);
        $data['reply_role_slugs']            = array_values($data['reply_role_slugs'] ?? []);
        $data['cta_on_pages']                = array_values($data['cta_on_pages'] ?? []);

        BlogSettings::save($data);
        return redirect()->route('admin.blogs.settings.edit')->with('success', 'Blog settings saved.');
    }
}
