<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\Contact;
use Closure;
use Illuminate\Http\Request;

class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $plan = $user->plan;
        if (!$plan || !$plan->features) {
            return $next($request);
        }

        $features = $plan->features;

        switch ($feature) {
            case 'links':
                $maxLinks = $features['max_links'] ?? 5;
                if ($maxLinks !== -1 && $user->links()->count() >= $maxLinks) {
                    return back()->with('error', "You've reached your plan's link limit ({$maxLinks}). Upgrade your plan for more links.");
                }
                break;

            case 'biolinks':
                $maxBiolinks = $features['max_biolinks'] ?? 1;
                if ($maxBiolinks !== -1 && $user->links()->where('type', 'biolink')->count() >= $maxBiolinks) {
                    return back()->with('error', "You've reached your plan's Link in Bio limit ({$maxBiolinks}). Upgrade your plan for more.");
                }
                break;

            case 'projects':
                $maxProjects = $features['max_projects'] ?? 1;
                if ($maxProjects !== -1 && $user->projects()->count() >= $maxProjects) {
                    return back()->with('error', "You've reached your plan's project limit ({$maxProjects}). Upgrade your plan for more.");
                }
                break;

            case 'pixels':
                if (empty($features['pixels'])) {
                    return back()->with('error', 'Tracking is not available on your current plan. Upgrade to access this feature.');
                }
                break;

            case 'custom_domains':
                if (empty($features['custom_domains'])) {
                    return back()->with('error', 'Custom domains are not available on your current plan. Upgrade to access this feature.');
                }
                break;

            case 'link_protection':
                if (empty($features['link_protection'])) {
                    return back()->with('error', 'Link protection is not available on your current plan. Upgrade to access this feature.');
                }
                break;

            case 'seo_settings':
                if (empty($features['seo_settings'])) {
                    return back()->with('error', 'SEO settings are not available on your current plan. Upgrade to access this feature.');
                }
                break;

            case 'utm_params':
                if (empty($features['utm_params'])) {
                    return back()->with('error', 'UTM parameters are not available on your current plan. Upgrade to access this feature.');
                }
                break;

            case 'contacts_max':
                $maxContacts = (int) ($features['contacts_max'] ?? 5000);
                if ($maxContacts !== -1 && Contact::where('user_id', $user->id)->count() >= $maxContacts) {
                    return back()->with('error', "You've reached your plan's contact limit ({$maxContacts}). Upgrade your plan to add more contacts.");
                }
                break;

            case 'contacts_google_sync':
                if (empty($features['contacts_google_sync'])) {
                    return redirect()->route('user.contacts.index')->with('error', 'Google Contacts sync is not available on your current plan. Upgrade to connect your Google account.');
                }
                break;
        }

        return $next($request);
    }
}
