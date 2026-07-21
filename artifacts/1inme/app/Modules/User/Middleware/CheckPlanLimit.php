<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\VaultCredential;
use Closure;
use Illuminate\Http\Request;

class CheckPlanLimit
{
    /**
     * Append a concrete "Upgrade to <Plan>" suffix to a rejection message
     * by looking up the cheapest active plan that unlocks $key. Falls back
     * to the original message when no qualifying plan is found.
     */
    private function withTarget(string $message, $user, string $key, $current = null): string
    {
        if (!$user) return $message;
        $target = method_exists($user, 'planThatUnlocks') ? $user->planThatUnlocks($key, $current) : null;
        if (!$target) return $message;
        return rtrim($message, '. ') . '. Upgrade to the ' . $target->name . ' plan to unlock it.';
    }

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
                if ($maxBiolinks !== -1 && $user->links()->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->count() >= $maxBiolinks) {
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
                // Contacts are an account-wide address book (matching the
                // Contacts page and API), so count across all workspaces.
                if ($maxContacts !== -1 && Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->count() >= $maxContacts) {
                    return back()->with('error', "You've reached your plan's contact limit ({$maxContacts}). Upgrade your plan to add more contacts.");
                }
                break;

            case 'contacts_google_sync':
                if (empty($features['contacts_google_sync'])) {
                    return redirect()->route('user.contacts.index')->with('error', 'Google Contacts sync is not available on your current plan. Upgrade to connect your Google account.');
                }
                break;

            case 'forms':
                $maxForms = (int) ($features['max_forms'] ?? 1);
                $cur = $user->forms()->count();
                if ($maxForms !== -1 && $cur >= $maxForms) {
                    return back()->with('error', $this->withTarget("You've reached your plan's form limit ({$maxForms}).", $user, 'max_forms', $cur));
                }
                break;

            case 'buzz_popups':
                if (empty($features['buzz_popups'])) {
                    return back()->with('error', $this->withTarget('Social-proof buzz popups are not available on your current plan.', $user, 'buzz_popups'));
                }
                $maxItems = (int) ($features['max_buzz_items'] ?? 0);
                $cur = SocialProof::where('user_id', $user->id)->count();
                if ($maxItems !== -1 && $cur >= $maxItems) {
                    return back()->with('error', $this->withTarget("You've reached your plan's buzz-popup campaign limit ({$maxItems}).", $user, 'max_buzz_items', $cur));
                }
                break;

            case 'splash_pages':
                if (empty($features['splash_pages'])) {
                    return back()->with('error', $this->withTarget('Splash pages are not available on your current plan.', $user, 'splash_pages'));
                }
                $maxSplash = (int) ($features['max_splash_pages'] ?? 0);
                $cur = $user->splashPages()->count();
                if ($maxSplash !== -1 && $cur >= $maxSplash) {
                    return back()->with('error', $this->withTarget("You've reached your plan's splash-page limit ({$maxSplash}).", $user, 'max_splash_pages', $cur));
                }
                break;

            case 'files':
                if (array_key_exists('files', $features) && empty($features['files'])) {
                    return back()->with('error', $this->withTarget('File hosting is not available on your current plan.', $user, 'files'));
                }
                $maxFiles = (int) ($features['max_files'] ?? -1);
                $cur = UserFile::where('user_id', $user->id)->whereNull('context')->count();
                if ($maxFiles !== -1 && $cur >= $maxFiles) {
                    return back()->with('error', $this->withTarget("You've reached your plan's file limit ({$maxFiles}).", $user, 'max_files', $cur));
                }
                break;

            case 'vaults':
                if (empty($features['vaults'])) {
                    return back()->with('error', $this->withTarget('The credential vault is not available on your current plan.', $user, 'vaults'));
                }
                $maxVault = (int) ($features['max_vault_items'] ?? 0);
                $cur = VaultCredential::where('created_by_user_id', $user->id)->count();
                if ($maxVault !== -1 && $cur >= $maxVault) {
                    return back()->with('error', $this->withTarget("You've reached your plan's vault item limit ({$maxVault}).", $user, 'max_vault_items', $cur));
                }
                break;

            case 'tasks':
                if (array_key_exists('tasks', $features) && empty($features['tasks'])) {
                    return back()->with('error', $this->withTarget('Task boards are not available on your current plan.', $user, 'tasks'));
                }
                $maxBoards = (int) ($features['max_task_boards'] ?? -1);
                if ($maxBoards !== -1) {
                    $count = TaskBoard::query()
                        ->whereNull('archived_at')
                        ->where(function ($q) use ($user) {
                            $q->where(function ($q2) use ($user) {
                                $q2->where('scope', 'personal')->where('owner_user_id', $user->id);
                            })->orWhere('scope', 'team');
                        })->count();
                    if ($count >= $maxBoards) {
                        return back()->with('error', $this->withTarget("You've reached your plan's task-board limit ({$maxBoards}).", $user, 'max_task_boards', $count));
                    }
                }
                break;

            case 'leads':
                if (empty($features['leads'])) {
                    return back()->with('error', $this->withTarget('Leads capture is not available on your current plan.', $user, 'leads'));
                }
                $maxLeads = (int) ($features['max_leads'] ?? -1);
                // Account-wide count, consistent with contacts_max above.
                $cur = Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->count();
                if ($maxLeads !== -1 && $cur >= $maxLeads) {
                    return back()->with('error', $this->withTarget("You've reached your plan's lead limit ({$maxLeads}).", $user, 'max_leads', $cur));
                }
                break;

            case 'creator_profile_public':
                if (empty($features['creator_profile_public'])) {
                    return back()->with('error', $this->withTarget('A public creator profile is not available on your current plan.', $user, 'creator_profile_public'));
                }
                break;

            case 'events':
                if (empty($features['events'])) {
                    return back()->with('error', $this->withTarget('Events are not available on your current plan.', $user, 'events'));
                }
                $maxEvents = (int) ($features['max_events'] ?? 0);
                $cur = Link::where('user_id', $user->id)->where('type', 'ics')->count();
                if ($maxEvents !== -1 && $cur >= $maxEvents) {
                    return back()->with('error', $this->withTarget("You've reached your plan's event limit ({$maxEvents}).", $user, 'max_events', $cur));
                }
                break;

            case 'calendar_sync':
                if (empty($features['calendar_sync'])) {
                    return back()->with('error', $this->withTarget('Calendar sync is not available on your current plan.', $user, 'calendar_sync'));
                }
                break;

            case 'verification_eligible':
                if (empty($features['verification_eligible'])) {
                    return back()->with('error', $this->withTarget('Profile verification is not available on your current plan.', $user, 'verification_eligible'));
                }
                break;

            case 'ai_minds':
                $cur = \App\Modules\User\Models\AiMind::where('user_id', $user->id)->count();
                if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'minds', $cur)) {
                    return back()->with('error', \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'minds', 'AI Mind', $cur));
                }
                break;

            case 'ai_personas':
                $cur = \App\Modules\User\Models\AiPersonaAgent::where('user_id', $user->id)->count();
                if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'personas', $cur)) {
                    return back()->with('error', \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'personas', 'Persona', $cur));
                }
                break;

            case 'ai_companions':
                $cur = \App\Modules\User\Models\AiCompanion::where('user_id', $user->id)->count();
                if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'companions', $cur)) {
                    return back()->with('error', \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'companions', 'Companion', $cur));
                }
                break;

            case 'link_password':
            case 'link_expiry':
            case 'link_geo_targeting':
            case 'link_device_targeting':
            case 'link_deep_link':
            case 'link_smart_rules':
            case 'link_active_window':
                if (empty($features[$feature])) {
                    return back()->with('error', $this->withTarget('This per-link advanced setting is not available on your current plan.', $user, $feature));
                }
                break;
        }

        return $next($request);
    }
}
