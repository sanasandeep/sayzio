<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Link;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Deterministic checks of recorded outcomes. No AI, wallet, or external requests. */
class IndustryPerformanceReport
{
    public const PROFILES = [
        'restaurant' => ['label' => 'Restaurant operations', 'goal' => 'Completed orders', 'prompt' => 'Cuisine and service model (tiffins, café, dine-in, takeaway or delivery)'],
        'store' => ['label' => 'Store sales', 'goal' => 'Completed purchases', 'prompt' => 'Product category and fulfillment model'],
        'services' => ['label' => 'Service bookings', 'goal' => 'Completed appointments', 'prompt' => 'Service specialty and appointment model'],
        'salon' => ['label' => 'Salon & spa', 'goal' => 'Completed appointments', 'prompt' => 'Salon specialty and walk-in / appointment model'],
        'club' => ['label' => 'Club memberships', 'goal' => 'Approved members and renewals', 'prompt' => 'Club purpose and membership model'],
        'education' => ['label' => 'Course enrollment', 'goal' => 'Enrollment inquiries', 'prompt' => 'Subjects, course delivery and enrollment process'],
        'property' => ['label' => 'Property inquiries', 'goal' => 'Qualified property inquiries', 'prompt' => 'Property market and sale / rental focus'],
        'directory' => ['label' => 'Contact directory', 'goal' => 'Contact discovery', 'prompt' => 'Organization type and departments served'],
        'nonprofit' => ['label' => 'Campaign participation', 'goal' => 'Donations or volunteer signups', 'prompt' => 'Cause, campaign purpose and donation / volunteer process'],
        'creator' => ['label' => 'Creator audience', 'goal' => 'Visits and content interactions', 'prompt' => 'Content niche and audience'],
        'other' => ['label' => 'Business performance', 'goal' => 'Your stated business outcome', 'prompt' => 'Industry and intended visitor action'],
    ];

    public static function typeProfile(Link $link): ?string
    {
        return ['restaurant_menu' => 'restaurant', 'store_menu' => 'store', 'service_booking' => 'services', 'salon_spa' => 'salon', 'community_club' => 'club', 'education' => 'education', 'real_estate' => 'property', 'contact_directory' => 'directory', 'nonprofit_campaign' => 'nonprofit'][$link->type] ?? null;
    }

    public static function profile(Link $link): ?string
    {
        $profile = self::typeProfile($link) ?? data_get($link->settings, 'performance_business.profile');

        return isset(self::PROFILES[$profile ?? '']) ? $profile : null;
    }

    public static function build(Link $link, CarbonInterface $from, CarbonInterface $to, array $traffic): array
    {
        $profile = self::profile($link);
        $context = data_get($link->settings, 'performance_business', []);
        $result = ['profile' => $profile, 'label' => self::PROFILES[$profile]['label'] ?? 'Set your business context', 'goal' => $context['goal'] ?? (self::PROFILES[$profile]['goal'] ?? ''), 'context' => $context, 'metrics' => [], 'checks' => [], 'limited' => ($traffic['visits'] ?? 0) < 50, 'period' => [$from->toDateString(), $to->toDateString()]];
        $metric = function ($label, $value, $note, $kind = 'clicks') use (&$result) {
            $result['metrics'][] = compact('label', 'value', 'note', 'kind');
        };
        $check = function ($title, $detail, $tone = 'tip') use (&$result) {
            $result['checks'][] = compact('title', 'detail', 'tone');
        };
        if (! $profile) {
            $check('Tell us what this link is for', 'Choose an industry, audience and goal below. We will use those details instead of assuming this is a creator page.');

            return $result;
        }
        $metric('Page visits', number_format($traffic['visits'] ?? 0), 'Selected period · excludes bots', 'visitors');
        $range = [$from->copy()->utc(), $to->copy()->utc()];
        if (in_array($profile, ['restaurant', 'store'])) {
            $table = $profile === 'restaurant' ? 'restaurant_orders' : 'store_orders';
            $q = DB::table($table)->where('link_id', $link->id)->whereBetween('created_at', $range);
            $total = (clone $q)->count();
            $done = (clone $q)->where('status', 'completed')->count();
            $open = (clone $q)->whereIn('status', ['new', 'accepted', 'preparing', 'packing', 'ready'])->count();
            $cancelled = (clone $q)->where('status', 'cancelled')->count();
            $metric($profile === 'restaurant' ? 'Orders placed' : 'Purchases placed', number_format($total), 'Recorded in Sayzio', 'pages');
            $metric('Completed', number_format($done), $cancelled.' cancelled · '.$open.' still open', 'sessions');
            if ($open) {
                $check($open.' orders still need attention', $profile === 'restaurant' ? 'Review tickets in Orders / Kitchen before promoting more orders. Open orders are not counted as completed.' : 'Review open purchases and fulfillment before launching another promotion.', 'warning');
            }
            if ($cancelled) {
                $check($cancelled.' cancelled orders', 'Review unavailable items, fulfillment delays and order notes. Cancellations are recorded outcomes; they do not identify the cause.', 'warning');
            }
            if (! $total) {
                $check($profile === 'restaurant' ? 'No Sayzio orders in this period' : 'No Sayzio purchases in this period', 'Check whether visitors can place an order and whether you take orders offline or through another platform. Those external sales are not measured here.');
            }
            if ($done) {
                $check($done.' completed '.($profile === 'restaurant' ? 'restaurant orders' : 'purchases'), 'Compare completed order volume across equal date ranges. Item views and page clicks are not sales.', 'win');
            }
        } elseif (in_array($profile, ['services', 'salon'])) {
            $q = DB::table('service_booking_requests')->where('link_id', $link->id)->whereBetween('created_at', $range);
            $total = (clone $q)->count();
            $pending = (clone $q)->where('status', 'pending')->count();
            $done = (clone $q)->where('status', 'completed')->count();
            $missed = (clone $q)->where('status', 'no_show')->count();
            $metric('Booking requests', number_format($total), 'Requests created in this period', 'pages');
            $metric('Completed appointments', number_format($done), $missed.' no-shows · '.$pending.' pending', 'sessions');
            if ($pending) {
                $check($pending.' booking requests awaiting a decision', 'Review availability and confirm or decline each request. A request is not a completed appointment.', 'warning');
            }
            if ($missed) {
                $check($missed.' appointments marked no-show', 'Review reminder timing and your rescheduling process before increasing booking traffic.', 'warning');
            }
            if (! $total) {
                $check('No appointment requests recorded', 'Check service durations, prices and bookable hours. If you use walk-ins or an external booking system, enter that model below so these figures are interpreted correctly.');
            }
            if ($done) {
                $check($done.' appointments completed', 'Track completed appointments separately from requests and cancellations.', 'win');
            }
        } elseif ($profile === 'club') {
            $q = DB::table('club_memberships')->where('link_id', $link->id);
            $today = now($from->getTimezone())->toDateString();
            $active = (clone $q)->where('status', 'active')->where(fn ($b) => $b->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today))->count();
            $pending = (clone $q)->where('status', 'pending')->count();
            $expired = (clone $q)->where('status', 'active')->whereDate('expires_at', '<', $today)->count();
            $soon = (clone $q)->where('status', 'active')->whereBetween('expires_at', [$today, now($from->getTimezone())->addDays(30)->toDateString()])->count();
            $metric('Active members', number_format($active), 'Current roster · excludes expired memberships', 'visitors');
            $metric('Renewals due', number_format($soon), 'Expiring within 30 days · current roster', 'sessions');
            if ($pending) {
                $check($pending.' applications awaiting approval', 'Review membership applications. Approval and payment are separate; do not treat a paid invoice as automatic approval.', 'warning');
            }
            if ($expired) {
                $check($expired.' memberships have expired', 'Review renewals or close expired memberships. Expired memberships are excluded from the active count.', 'warning');
            }
            if ($soon) {
                $check($soon.' renewals due within 30 days', 'Check reminder configuration and the membership renewal duration before contacting members.', 'tip');
            }
            if (! $pending && ! $expired && ! $soon) {
                $check('Membership queue has no pending actions', 'No pending approvals or upcoming expiries were found in the current roster. This is an operational check, not a growth score.', 'win');
            }
        } elseif (in_array($profile, ['education', 'property'])) {
            $ids = DB::table('listing_catalogs')->where('link_id', $link->id)->select('id');
            $q = DB::table('listing_inquiries')->whereIn('catalog_id', $ids)->whereBetween('created_at', $range);
            $total = (clone $q)->count();
            $new = (clone $q)->where('status', 'new')->count();
            $metric($profile === 'education' ? 'Course inquiries' : 'Property inquiries', number_format($total), 'Sayzio inquiry submissions in this period', 'pages');
            $metric('New inquiries', number_format($new), 'Awaiting a recorded follow-up status', 'visitors');
            if ($new) {
                $check($new.' inquiries need follow-up', $profile === 'education' ? 'Review requested courses, preferred dates and batches. Record follow-up status; inquiries are not confirmed enrollments.' : 'Review requested listings and viewing dates. Record follow-up status; inquiries are not property sales.', 'warning');
            }
            if (! $total) {
                $check($profile === 'education' ? 'No course inquiries recorded' : 'No property inquiries recorded', $profile === 'education' ? 'Check course descriptions, fees, delivery mode and enrollment contact options. Offline admissions are not measured.' : 'Check listing availability, property details and viewing contact options. External leads are not measured.');
            }
        } else {
            $metric('Content interactions', number_format($traffic['interactions'] ?? 0), 'All tracked block clicks · not verified goal completions', 'clicks');
            $check(match ($profile) {
                'directory' => 'Check contact reachability','nonprofit' => 'Check the campaign participation path','creator' => 'Check your audience action',default => 'Define the action you want visitors to take'
            }, match ($profile) {
                'directory' => 'Review phone/email links, department labels and contact freshness. Block clicks do not prove a successful call or message.','nonprofit' => 'Verify the donation link or volunteer signup form. External donations and completed volunteer applications cannot be inferred from clicks.','creator' => 'Use the stated niche and goal below to identify the main content action. Content clicks do not prove follows or subscriptions.',default => 'Enter your industry, audience and goal below. Clicks alone cannot establish whether the visitor completed that action.'
            });
        }
        if (empty($context['industry']) || empty($context['audience']) || empty($context['goal'])) {
            $check('Complete your business context',self::PROFILES[$profile]['prompt'].': add this, your target audience and primary goal below. We will not infer market demand or recommend generic growth tactics.');
        } else {
            $check('Your operating context',$context['industry'].' · Audience: '.$context['audience'].' · Goal: '.$context['goal'].'. The checks above use recorded '.self::PROFILES[$profile]['goal'].'; custom goals need their own tracked outcome.');
        }

        return $result;
    }
}
