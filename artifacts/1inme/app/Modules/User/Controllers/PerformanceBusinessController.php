<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\IndustryPerformanceReport;
use Illuminate\Http\Request;

class PerformanceBusinessController extends Controller
{
    public function update(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) workspace_owner_id(), 403);
        $data = $request->validate(['profile' => 'required|in:'.implode(',', array_keys(IndustryPerformanceReport::PROFILES)), 'industry' => 'required|string|max:200', 'audience' => 'required|string|max:200', 'goal' => 'required|string|max:200']);
        // Specialized link types always keep their actual industry profile.
        $data['profile'] = IndustryPerformanceReport::typeProfile($link) ?? $data['profile'];
        $settings = $link->settings ?? [];
        $settings['performance_business'] = $data;
        $link->update(['settings' => $settings]);

        return redirect()->to(route('user.links.show', $link).'?'.http_build_query($request->only(['from', 'to', 'period', 'source', 'country', 'device'])).'#business-performance')->with('success', 'Business performance context updated. No AI or coins used.');
    }
}
