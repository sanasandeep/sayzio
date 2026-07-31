<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingAvailabilityRule;
use App\Modules\User\Models\ServiceBookingBlockedDate;
use App\Modules\User\Models\ServiceBookingCategory;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Owner-side builder + bookings dashboard for the Service Booking link type
 * (Task #3085). Mirrors RestaurantMenuController: a lazily-created config row,
 * AJAX JSON CRUD for the catalog (categories / services), weekly availability
 * rules, blocked dates, and a near-real-time bookings dashboard with a
 * server-enforced status workflow. No payment is ever taken.
 */
class ServiceBookingController extends Controller
{
    /** Resolve the booking config row for a link, creating it on first edit. */
    protected function bookingFor(Link $link): ServiceBooking
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_unless($link->type === Link::TYPE_SERVICE_BOOKING, 404);

        return ServiceBooking::firstOrCreate(
            ['link_id' => $link->id],
            [
                'user_id'             => $link->user_id,
                'mode'                => ServiceBooking::MODE_BOOKING,
                'currency'            => 'USD',
                'slot_length_minutes' => 30,
                'lead_time_minutes'   => 120,
                'max_days_ahead'      => 30,
                'timezone'            => \App\Support\PlatformTimezone::platformDefault(),
            ]
        );
    }

    /** Guard a child row belongs to this booking config. */
    protected function assertOwns(ServiceBooking $config, $model): void
    {
        abort_if((int) $model->service_booking_id !== (int) $config->id, 404);
    }

    /**
     * Validate an optional staff_id in the payload belongs to this booking
     * config. Returns a 422 JSON response on mismatch, null when fine.
     */
    protected function staffScopeError(ServiceBooking $config, array $data)
    {
        $staffId = $data['staff_id'] ?? null;
        if ($staffId === null || $staffId === '') {
            return null;
        }
        $owns = $config->staff()->where('id', (int) $staffId)->exists();

        return $owns ? null : response()->json(['error' => [
            'message' => 'That team member was not found on this booking page.',
            'code'    => 'invalid_staff',
        ]], 422);
    }

    public function editor(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $config->load(['categories', 'services', 'availabilityRules', 'blockedDates', 'staff.services']);

        $openBookings = ServiceBookingRequest::where('service_booking_id', $config->id)
            ->whereIn('status', ServiceBookingRequest::OPEN_STATUSES)
            ->count();

        $calendarAccounts = \App\Modules\User\Models\CalendarAccount::where('user_id', $config->user_id)
            ->orderBy('id')
            ->get(['id', 'provider', 'display_name', 'account_email']);

        return view('user.links.service-booking.editor', [
            'link'             => $link,
            'config'           => $config,
            'openBookings'     => $openBookings,
            'calendarAccounts' => $calendarAccounts,
            'staffCap'         => (int) $link->user->getPlanFeature('max_service_booking_staff'),
            'calendarSyncAllowed' => (bool) $link->user->getPlanFeature('service_booking_calendar_sync'),
        ]);
    }

    public function saveSettings(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);

        $data = $request->validate([
            'mode'                    => 'required|in:display,booking',
            'currency'                => 'required|string|size:3',
            'accent_color'            => 'nullable|string|max:16',
            'slot_length_minutes'     => 'required|integer|min:5|max:1440',
            'lead_time_minutes'       => 'required|integer|min:0|max:43200',
            'max_days_ahead'          => 'required|integer|min:1|max:365',
            'timezone'                => 'nullable|string|max:64',
            'settings'                => 'nullable|array',
            'tax_enabled'             => 'sometimes|boolean',
            'tax_rate'                => 'nullable|numeric|min:0|max:100',
            'tax_inclusive'           => 'sometimes|boolean',
            'tax_label'               => 'nullable|string|max:24',
            'whatsapp_number'         => 'sometimes|nullable|string|max:40',
            'reminder_lead_minutes'   => 'sometimes|nullable',
            // Task #6325 — buffers, visitor self-service, calendar sync.
            'buffer_before_minutes'   => 'sometimes|nullable|integer|min:0|max:480',
            'buffer_after_minutes'    => 'sometimes|nullable|integer|min:0|max:480',
            'self_service_allow_cancel'     => 'sometimes|boolean',
            'self_service_allow_reschedule' => 'sometimes|boolean',
            'self_service_cutoff_hours'     => 'sometimes|nullable|integer|min:0|max:720',
            'calendar_sync_enabled'   => 'sometimes|boolean',
            'calendar_sync_account_id' => 'sometimes|nullable|integer',
        ]);

        $settings = $data['settings'] ?? ($config->settings ?? []);

        // Optional WhatsApp click-to-chat number (Task #3102) — stored raw in
        // the `settings` JSON; normalized to wa.me form at send time.
        if ($request->has('whatsapp_number')) {
            $wa = trim((string) ($data['whatsapp_number'] ?? ''));
            if ($wa === '') {
                unset($settings['whatsapp_number']);
            } else {
                $settings['whatsapp_number'] = $wa;
            }
        }

        // Reminder lead time(s) — single int or comma-separated list (or array).
        if ($request->has('reminder_lead_minutes')) {
            $raw = $data['reminder_lead_minutes'] ?? null;
            if ($raw === null || $raw === '') {
                unset($settings['reminder_lead_minutes']);
            } elseif (is_array($raw)) {
                $settings['reminder_lead_minutes'] = array_values(array_filter(array_map('intval', $raw)));
            } else {
                $parsed = array_values(array_filter(array_map('intval', explode(',', (string) $raw))));
                $settings['reminder_lead_minutes'] = count($parsed) === 1 ? $parsed[0] : $parsed;
            }
        }

        // Tax / GST line — accepted either flat (web editor, mobile API) or
        // pre-nested under `settings`. Stored in the config `settings` JSON.
        if ($request->has('tax_enabled') || $request->has('tax_rate')
            || $request->has('tax_inclusive') || $request->has('tax_label')) {
            $settings['tax'] = [
                'enabled'   => (bool) ($data['tax_enabled'] ?? false),
                'rate'      => round((float) ($data['tax_rate'] ?? 0), 3),
                'inclusive' => (bool) ($data['tax_inclusive'] ?? false),
                'label'     => trim((string) ($data['tax_label'] ?? 'GST')) ?: 'GST',
            ];
        }

        // Global booking buffers (Task #6325).
        if ($request->has('buffer_before_minutes') || $request->has('buffer_after_minutes')) {
            $settings['buffers'] = [
                'before' => max(0, (int) ($data['buffer_before_minutes'] ?? ($settings['buffers']['before'] ?? 0))),
                'after'  => max(0, (int) ($data['buffer_after_minutes'] ?? ($settings['buffers']['after'] ?? 0))),
            ];
        }

        // Visitor self-service reschedule / cancel (Task #6325).
        if ($request->has('self_service_allow_cancel') || $request->has('self_service_allow_reschedule')
            || $request->has('self_service_cutoff_hours')) {
            $prev = $settings['self_service'] ?? [];
            $settings['self_service'] = [
                'allow_cancel'     => $request->has('self_service_allow_cancel') ? (bool) $data['self_service_allow_cancel'] : (bool) ($prev['allow_cancel'] ?? true),
                'allow_reschedule' => $request->has('self_service_allow_reschedule') ? (bool) $data['self_service_allow_reschedule'] : (bool) ($prev['allow_reschedule'] ?? true),
                'cutoff_hours'     => $request->has('self_service_cutoff_hours') ? max(0, (int) ($data['self_service_cutoff_hours'] ?? 24)) : (int) ($prev['cutoff_hours'] ?? 24),
            ];
        }

        // Google Calendar two-way sync (Task #6325) — plan-gated.
        if ($request->has('calendar_sync_enabled') || $request->has('calendar_sync_account_id')) {
            $enabled = $request->has('calendar_sync_enabled')
                ? (bool) $data['calendar_sync_enabled']
                : (bool) ($settings['calendar_sync']['enabled'] ?? false);
            if ($enabled && !$link->user->getPlanFeature('service_booking_calendar_sync')) {
                return response()->json(['error' => [
                    'message' => 'Calendar sync is not included in your plan.',
                    'code'    => 'plan_gated',
                ]], 422);
            }
            $accountId = $request->has('calendar_sync_account_id')
                ? ($data['calendar_sync_account_id'] ?? null)
                : ($settings['calendar_sync']['account_id'] ?? null);
            if ($accountId !== null) {
                $owns = \App\Modules\User\Models\CalendarAccount::where('id', (int) $accountId)
                    ->where('user_id', $config->user_id)->exists();
                if (!$owns) {
                    return response()->json(['error' => [
                        'message' => 'That calendar account was not found.',
                        'code'    => 'invalid_calendar_account',
                    ]], 422);
                }
            }
            $settings['calendar_sync'] = [
                'enabled'    => $enabled,
                'account_id' => $accountId !== null ? (int) $accountId : null,
            ];
        }

        $tz = trim((string) ($data['timezone'] ?? ''));
        if ($tz !== '' && !in_array($tz, timezone_identifiers_list(), true)) {
            return response()->json(['error' => [
                'message' => 'That timezone is not recognized.',
                'code'    => 'invalid_timezone',
            ]], 422);
        }

        $config->update([
            'mode'                => $data['mode'],
            'currency'            => strtoupper($data['currency']),
            'accent_color'        => $data['accent_color'] ?? $config->accent_color,
            'slot_length_minutes' => $data['slot_length_minutes'],
            'lead_time_minutes'   => $data['lead_time_minutes'],
            'max_days_ahead'      => $data['max_days_ahead'],
            'timezone'            => $tz !== '' ? $tz : ($config->timezone ?: 'UTC'),
            'settings'            => $settings,
        ]);

        return response()->json(['data' => ['config' => $config->fresh()]]);
    }

    // ── Categories ───────────────────────────────────────────────
    public function storeCategory(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $category = ServiceBookingCategory::create([
            'service_booking_id' => $config->id,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'sort_order'         => (int) ServiceBookingCategory::where('service_booking_id', $config->id)->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['category' => $category]], 201);
    }

    public function updateCategory(Request $request, Link $link, ServiceBookingCategory $category)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $category);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $category->update($data);

        return response()->json(['data' => ['category' => $category->fresh()]]);
    }

    public function destroyCategory(Request $request, Link $link, ServiceBookingCategory $category)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $category);

        // Detach services from the deleted category rather than deleting them.
        ServiceBookingService::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderCategories(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($data['order'] as $i => $id) {
            ServiceBookingCategory::where('service_booking_id', $config->id)->where('id', $id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    // ── Services ─────────────────────────────────────────────────
    public function storeService(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $data = $request->validate([
            'category_id'      => 'nullable|integer',
            'name'             => 'required|string|max:160',
            'description'      => 'nullable|string|max:800',
            'price'            => 'nullable|numeric|min:0|max:9999999',
            'currency'         => 'nullable|string|size:3',
            'duration_minutes' => 'required|integer|min:5|max:1440',
            'photo_url'        => 'nullable|string|max:1024',
            'is_unavailable'   => 'sometimes|boolean',
            'payment_mode'     => 'sometimes|in:none,deposit,full',
            'deposit_type'     => 'sometimes|nullable|in:fixed,percent',
            'deposit_value'    => 'sometimes|nullable|numeric|min:0|max:9999999',
            'capacity'              => 'sometimes|nullable|integer|min:1|max:500',
            'buffer_before_minutes' => 'sometimes|nullable|integer|min:0|max:480',
            'buffer_after_minutes'  => 'sometimes|nullable|integer|min:0|max:480',
        ]);

        if (!empty($data['category_id'])) {
            ServiceBookingCategory::where('service_booking_id', $config->id)->findOrFail($data['category_id']);
        }

        $service = ServiceBookingService::create([
            'service_booking_id' => $config->id,
            'category_id'        => $data['category_id'] ?? null,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'price'              => $data['price'] ?? 0,
            'currency'           => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'duration_minutes'   => $data['duration_minutes'],
            'photo_url'          => $data['photo_url'] ?? null,
            'is_unavailable'     => (bool) ($data['is_unavailable'] ?? false),
            'payment_mode'       => $data['payment_mode'] ?? ServiceBookingService::PAYMENT_MODE_NONE,
            'deposit_type'       => $data['deposit_type'] ?? null,
            'deposit_value'      => $data['deposit_value'] ?? null,
            'capacity'           => isset($data['capacity']) ? max(1, (int) $data['capacity']) : 1,
            'buffer_before_minutes' => $data['buffer_before_minutes'] ?? null,
            'buffer_after_minutes'  => $data['buffer_after_minutes'] ?? null,
            'sort_order'         => (int) ServiceBookingService::where('service_booking_id', $config->id)->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['service' => $service]], 201);
    }

    public function updateService(Request $request, Link $link, ServiceBookingService $service)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $service);

        $data = $request->validate([
            'category_id'      => 'nullable|integer',
            'name'             => 'sometimes|required|string|max:160',
            'description'      => 'nullable|string|max:800',
            'price'            => 'sometimes|numeric|min:0|max:9999999',
            'currency'         => 'nullable|string|size:3',
            'duration_minutes' => 'sometimes|integer|min:5|max:1440',
            'photo_url'        => 'nullable|string|max:1024',
            'is_unavailable'   => 'sometimes|boolean',
            'is_active'        => 'sometimes|boolean',
            'payment_mode'     => 'sometimes|in:none,deposit,full',
            'deposit_type'     => 'sometimes|nullable|in:fixed,percent',
            'deposit_value'    => 'sometimes|nullable|numeric|min:0|max:9999999',
            'capacity'              => 'sometimes|nullable|integer|min:1|max:500',
            'buffer_before_minutes' => 'sometimes|nullable|integer|min:0|max:480',
            'buffer_after_minutes'  => 'sometimes|nullable|integer|min:0|max:480',
        ]);

        if (array_key_exists('capacity', $data)) {
            $data['capacity'] = $data['capacity'] !== null ? max(1, (int) $data['capacity']) : 1;
        }
        if (array_key_exists('category_id', $data) && !empty($data['category_id'])) {
            ServiceBookingCategory::where('service_booking_id', $config->id)->findOrFail($data['category_id']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $service->update($data);

        return response()->json(['data' => ['service' => $service->fresh()]]);
    }

    public function destroyService(Request $request, Link $link, ServiceBookingService $service)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $service);
        $service->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderServices(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($data['order'] as $i => $id) {
            ServiceBookingService::where('service_booking_id', $config->id)->where('id', $id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    // ── Weekly availability rules ────────────────────────────────
    public function storeAvailability(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $data = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'staff_id'    => 'nullable|integer',
        ]);

        if ($err = $this->staffScopeError($config, $data)) {
            return $err;
        }

        $rule = ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $config->id,
            'staff_id'           => $data['staff_id'] ?? null,
            'day_of_week'        => $data['day_of_week'],
            'start_time'         => $data['start_time'],
            'end_time'           => $data['end_time'],
            'is_active'          => true,
        ]);

        return response()->json(['data' => ['rule' => $rule]], 201);
    }

    public function updateAvailability(Request $request, Link $link, ServiceBookingAvailabilityRule $rule)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $rule);

        $data = $request->validate([
            'day_of_week' => 'sometimes|integer|min:0|max:6',
            'start_time'  => 'sometimes|date_format:H:i',
            'end_time'    => 'sometimes|date_format:H:i',
            'is_active'   => 'sometimes|boolean',
            'staff_id'    => 'sometimes|nullable|integer',
        ]);

        if (array_key_exists('staff_id', $data) && ($err = $this->staffScopeError($config, $data))) {
            return $err;
        }

        $start = $data['start_time'] ?? $rule->start_time;
        $end   = $data['end_time'] ?? $rule->end_time;
        if (strtotime($end) <= strtotime($start)) {
            return response()->json(['error' => [
                'message' => 'End time must be after start time.',
                'code'    => 'invalid_window',
            ]], 422);
        }

        $rule->update($data);

        return response()->json(['data' => ['rule' => $rule->fresh()]]);
    }

    public function destroyAvailability(Request $request, Link $link, ServiceBookingAvailabilityRule $rule)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $rule);
        $rule->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    // ── Blocked dates ────────────────────────────────────────────
    public function storeBlockedDate(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $data = $request->validate([
            'date'     => 'required|date',
            'reason'   => 'nullable|string|max:160',
            'staff_id' => 'nullable|integer',
        ]);

        if ($err = $this->staffScopeError($config, $data)) {
            return $err;
        }

        $staffId = $data['staff_id'] ?? null;
        $date = \Carbon\Carbon::parse($data['date'])->format('Y-m-d');
        $dupe = ServiceBookingBlockedDate::where('service_booking_id', $config->id)
            ->whereDate('date', $date)
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId), fn ($q) => $q->whereNull('staff_id'))
            ->exists();
        if ($dupe) {
            return response()->json(['error' => [
                'message' => 'That date is already blocked.',
                'code'    => 'duplicate_date',
            ]], 422);
        }

        $blocked = ServiceBookingBlockedDate::create([
            'service_booking_id' => $config->id,
            'staff_id'           => $staffId,
            'date'               => $date,
            'reason'             => $data['reason'] ?? null,
        ]);

        return response()->json(['data' => ['blocked_date' => $blocked]], 201);
    }

    public function destroyBlockedDate(Request $request, Link $link, ServiceBookingBlockedDate $blockedDate)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $blockedDate);
        $blockedDate->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    // ── Staff / team members (Task #6325) ────────────────────────
    /** Serialize a staff member for editor JSON responses. */
    protected function staffPayload(\App\Modules\User\Models\ServiceBookingStaff $staff): array
    {
        $staff->loadMissing('services');

        return [
            'id'                  => $staff->id,
            'name'                => $staff->name,
            'title'               => $staff->title,
            'bio'                 => $staff->bio,
            'email'               => $staff->email,
            'photo_url'           => $staff->photo_url,
            'is_active'           => (bool) $staff->is_active,
            'sort_order'          => (int) $staff->sort_order,
            'calendar_account_id' => $staff->calendar_account_id,
            'service_ids'         => $staff->services->pluck('id')->map(fn ($i) => (int) $i)->values()->all(),
        ];
    }

    public function storeStaff(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);

        $cap = (int) $link->user->getPlanFeature('max_service_booking_staff');
        $current = $config->staff()->count();
        if ($cap !== -1 && $current >= max(0, $cap)) {
            return response()->json(['error' => [
                'message' => 'Your plan allows up to ' . max(0, $cap) . ' team member' . ($cap === 1 ? '' : 's') . ' per booking page. Upgrade to add more.',
                'code'    => 'plan_limit',
            ]], 422);
        }

        $data = $request->validate([
            'name'                => 'required|string|max:120',
            'title'               => 'nullable|string|max:120',
            'bio'                 => 'nullable|string|max:2000',
            'email'               => 'nullable|email|max:190',
            'photo_url'           => 'nullable|string|max:2048',
            'is_active'           => 'sometimes|boolean',
            'calendar_account_id' => 'nullable|integer',
            'service_ids'         => 'sometimes|array',
            'service_ids.*'       => 'integer',
        ]);

        if ($err = $this->validateStaffCalendarAccount($config, $data)) {
            return $err;
        }

        $staff = $config->staff()->create([
            'name'                => trim($data['name']),
            'title'               => $data['title'] ?? null,
            'bio'                 => $data['bio'] ?? null,
            'email'               => $data['email'] ?? null,
            'photo_url'           => $data['photo_url'] ?? null,
            'is_active'           => (bool) ($data['is_active'] ?? true),
            'calendar_account_id' => $data['calendar_account_id'] ?? null,
            'sort_order'          => ($config->staff()->max('sort_order') ?? 0) + 1,
        ]);

        $this->syncStaffServices($config, $staff, $data);

        return response()->json(['data' => ['staff' => $this->staffPayload($staff)]], 201);
    }

    public function updateStaff(Request $request, Link $link, \App\Modules\User\Models\ServiceBookingStaff $staff)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $staff);

        $data = $request->validate([
            'name'                => 'sometimes|required|string|max:120',
            'title'               => 'nullable|string|max:120',
            'bio'                 => 'nullable|string|max:2000',
            'email'               => 'nullable|email|max:190',
            'photo_url'           => 'nullable|string|max:2048',
            'is_active'           => 'sometimes|boolean',
            'calendar_account_id' => 'sometimes|nullable|integer',
            'service_ids'         => 'sometimes|array',
            'service_ids.*'       => 'integer',
        ]);

        if ($err = $this->validateStaffCalendarAccount($config, $data)) {
            return $err;
        }

        $staff->fill(array_filter([
            'name'      => isset($data['name']) ? trim($data['name']) : null,
        ], fn ($v) => $v !== null));
        foreach (['title', 'bio', 'email', 'photo_url'] as $key) {
            if ($request->has($key)) {
                $staff->{$key} = $data[$key] ?? null;
            }
        }
        if ($request->has('is_active')) {
            $staff->is_active = (bool) $data['is_active'];
        }
        if ($request->has('calendar_account_id')) {
            $staff->calendar_account_id = $data['calendar_account_id'] ?? null;
        }
        $staff->save();

        $this->syncStaffServices($config, $staff, $data);

        return response()->json(['data' => ['staff' => $this->staffPayload($staff->fresh())]]);
    }

    public function destroyStaff(Request $request, Link $link, \App\Modules\User\Models\ServiceBookingStaff $staff)
    {
        $config = $this->bookingFor($link);
        $this->assertOwns($config, $staff);
        $staff->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderStaff(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        foreach (array_values($ids) as $i => $id) {
            $config->staff()->where('id', (int) $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    /** Restrict a staff member to a subset of services (empty = all). */
    protected function syncStaffServices(ServiceBooking $config, \App\Modules\User\Models\ServiceBookingStaff $staff, array $data): void
    {
        if (!array_key_exists('service_ids', $data)) {
            return;
        }
        $valid = $config->services()->whereIn('id', array_map('intval', $data['service_ids'] ?? []))
            ->pluck('id')->all();
        $staff->services()->sync($valid);
    }

    /** Ensure a linked calendar account belongs to the page owner. */
    protected function validateStaffCalendarAccount(ServiceBooking $config, array $data)
    {
        $accountId = $data['calendar_account_id'] ?? null;
        if ($accountId === null) {
            return null;
        }
        $owns = \App\Modules\User\Models\CalendarAccount::where('id', (int) $accountId)
            ->where('user_id', $config->user_id)->exists();

        return $owns ? null : response()->json(['error' => [
            'message' => 'That calendar account was not found.',
            'code'    => 'invalid_calendar_account',
        ]], 422);
    }

    // ── Bookings dashboard ───────────────────────────────────────
    public function bookings(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);

        $bookings = ServiceBookingRequest::with(['items', 'staff'])
            ->where('service_booking_id', $config->id)
            ->latest()
            ->limit(100)
            ->get();

        return view('user.links.service-booking.bookings', [
            'link'     => $link,
            'config'   => $config,
            'bookings' => $bookings,
        ]);
    }

    /** Near-real-time polling endpoint for the bookings dashboard. */
    public function pollBookings(Request $request, Link $link)
    {
        $config = $this->bookingFor($link);

        $query = ServiceBookingRequest::with(['items', 'staff'])->where('service_booking_id', $config->id);

        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) {
                // ignore bad cursor, return recent set
            }
        }

        $bookings = $query->latest('updated_at')->limit(100)->get();

        $openCount = ServiceBookingRequest::where('service_booking_id', $config->id)
            ->whereIn('status', ServiceBookingRequest::OPEN_STATUSES)
            ->count();

        return response()->json(['data' => [
            'bookings'    => $bookings,
            'open_count'  => $openCount,
            'server_time' => now()->toIso8601String(),
        ]]);
    }

    public function updateBookingStatus(Request $request, Link $link, ServiceBookingRequest $booking)
    {
        $config = $this->bookingFor($link);
        abort_if((int) $booking->service_booking_id !== (int) $config->id, 404);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', ServiceBookingRequest::STATUSES),
        ]);

        if (!$booking->canTransitionTo($data['status'])) {
            return response()->json(['error' => [
                'message' => "Can't move a booking from '{$booking->status}' to '{$data['status']}'",
                'code'    => 'invalid_transition',
            ]], 422);
        }

        $changed = $data['status'] !== $booking->status;
        $booking->update(['status' => $data['status']]);

        if ($changed) {
            $fresh = $booking->fresh(['items', 'serviceBooking', 'link']);

            // When the owner cancels a paid booking, trigger a refund.
            if ($data['status'] === ServiceBookingRequest::STATUS_CANCELLED && $fresh->isRefundable()) {
                try {
                    app(\App\Services\Monetization\MonetizationCheckout::class)
                        ->refundBookingRequest($fresh->id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('booking.cancel.refund_failed', [
                        'booking' => $fresh->id, 'err' => $e->getMessage(),
                    ]);
                }
            }

            app(\App\Modules\Common\Services\ServiceBookingRequestService::class)
                ->notifyStatusChange($fresh);
        }

        return response()->json(['data' => ['booking' => $booking->fresh('items')]]);
    }
}
