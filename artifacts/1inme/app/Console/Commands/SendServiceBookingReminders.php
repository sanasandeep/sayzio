<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\ServiceBookingRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly: email appointment reminder(s) to confirmed booking visitors before
 * their scheduled slot.
 *
 * Reminder lead time is read from the Service Booking page's settings:
 *   service_bookings.settings['reminder_lead_minutes']
 *
 * This may be a single integer (e.g. 1440 = 24 hours) or an array of integers
 * (e.g. [1440, 120] = 24 h and 2 h). Defaults to 1440 (one day ahead).
 *
 * Deduplication: each (booking, lead_minutes) pair is stored in
 *   service_booking_requests.meta['reminders_sent']
 * so replays and overlapping runs never double-send.
 *
 * Only STATUS_CONFIRMED bookings receive reminders (pending / cancelled /
 * declined / completed are skipped).
 */
class SendServiceBookingReminders extends Command
{
    protected $signature   = 'bookings:send-reminders';
    protected $description = 'Email appointment reminders to confirmed booking visitors.';

    public function handle(): int
    {
        $now  = now();
        $sent = 0;

        // Load all confirmed future bookings. Visitor reminders need a
        // customer email; staff reminders only need an assigned team member
        // with a notification email, so filter per-recipient below.
        $bookings = ServiceBookingRequest::with(['serviceBooking', 'items', 'link', 'staff'])
            ->withoutGlobalScope('workspace')
            ->where('status', ServiceBookingRequest::STATUS_CONFIRMED)
            ->where('slot_start', '>', $now)
            ->cursor();

        foreach ($bookings as $booking) {
            $config = $booking->serviceBooking;
            if (!$config) {
                continue;
            }

            $settings   = (array) ($config->settings ?? []);
            $leadConfig = $settings['reminder_lead_minutes'] ?? 1440;
            $leads      = is_array($leadConfig) ? $leadConfig : [$leadConfig];

            foreach ($leads as $lead) {
                $lead = (int) $lead;
                if ($lead <= 0) {
                    continue;
                }

                // Is the slot within the 1-hour window around the lead time?
                $diffMinutes = (int) $now->diffInMinutes(Carbon::parse($booking->slot_start), false);
                if ($diffMinutes < 0) {
                    continue;
                }
                if ($diffMinutes > $lead || $diffMinutes < ($lead - 60)) {
                    continue;
                }

                $link = $booking->link;
                $tz   = $config->effectiveTimezone();
                $when = Carbon::parse($booking->slot_start)->setTimezone($tz)->format('D, M j · g:i A');
                $serviceNames = $booking->items->pluck('name')->implode(', ');
                if ($serviceNames === '') {
                    $serviceNames = 'your appointment';
                }
                $leadLabel = $lead >= 60
                    ? round($lead / 60) . ' hour' . (round($lead / 60) !== 1.0 ? 's' : '')
                    : $lead . ' minute' . ($lead !== 1 ? 's' : '');

                // Visitor reminder.
                if ($booking->customer_email && !$booking->wasReminderSent($lead)) {
                    try {
                        Emailer::send('service_booking.reminder', $booking->customer_email, [
                            'customer'   => $booking->customer_name,
                            'services'   => $serviceNames,
                            'when'       => $when,
                            'lead_label' => $leadLabel,
                            'link_title' => $link?->title ?? 'your appointment',
                            'status_url' => \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('sb.public.booking.page', ['token' => $booking->public_token])),
                        ], [
                            'related'  => $booking,
                            'to_name'  => $booking->customer_name,
                        ]);
                        $booking->markReminderSent($lead);
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->warn("Failed reminder for booking {$booking->id} (lead={$lead}): {$e->getMessage()}");
                    }
                }

                // Assigned team member reminder (Task #6338).
                $staff      = $booking->staff;
                $staffEmail = trim((string) ($staff?->email ?? ''));
                if ($staff && $staffEmail !== '' && !$booking->wasStaffReminderSent($lead)) {
                    try {
                        Emailer::send('service_booking.staff_reminder', $staffEmail, [
                            'staff_name' => $staff->name,
                            'customer'   => $booking->customer_name,
                            'services'   => $serviceNames,
                            'when'       => $when,
                            'lead_label' => $leadLabel,
                            'link_title' => $link?->title ?? 'your appointment',
                        ], [
                            'related' => $booking,
                            'to_name' => $staff->name,
                        ]);
                        $booking->markStaffReminderSent($lead);
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->warn("Failed staff reminder for booking {$booking->id} (lead={$lead}): {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info("Sent {$sent} booking reminder(s).");

        return self::SUCCESS;
    }
}
