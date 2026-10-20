<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use Tests\TestCase;

/**
 * A downloaded Sayzio event has never alerted anyone: there was no VALARM
 * anywhere in the codebase, so "add to calendar" produced an entry that
 * sat silently in a calendar until the day it happened.
 *
 * The interesting part is not that alarms now exist. It is that the
 * attendee chooses them in a query string, and an ICS body is a
 * newline-delimited format -- text interpolated into it can open a line
 * of its own and forge calendar properties. So the request only ever
 * selects a key from a fixed map; it never contributes a character. The
 * injection tests below are the ones worth keeping.
 */
class EventRemindersRideAlongTest extends TestCase
{
    public function test_a_download_with_no_choice_carries_the_sensible_pair(): void
    {
        $this->assertSame([1440, 60], IcsData::sanitizeReminders(null));
        $this->assertSame([1440, 60], IcsData::sanitizeReminders(''));
    }

    public function test_none_means_none(): void
    {
        $this->assertSame([], IcsData::sanitizeReminders('none'));
        $this->assertSame([], IcsData::sanitizeReminders('NONE'));
        $this->assertSame([], IcsData::sanitizeReminders(' none '));
    }

    public function test_a_choice_is_honoured_and_ordered_furthest_out_first(): void
    {
        $this->assertSame([1440, 60, 15], IcsData::sanitizeReminders('15,1440,60'));
        $this->assertSame([15], IcsData::sanitizeReminders('15'));
        $this->assertSame([10080, 1440], IcsData::sanitizeReminders([1440, 10080]));
    }

    public function test_duplicates_collapse_and_the_count_is_capped(): void
    {
        $this->assertSame([60], IcsData::sanitizeReminders('60,60,60'));
        $this->assertLessThanOrEqual(
            IcsData::MAX_REMINDERS,
            count(IcsData::sanitizeReminders('0,15,60,180,1440,10080'))
        );
    }

    /**
     * The one that matters. Every value here is either a forgery attempt
     * or a near-miss that must not be rounded into a real reminder.
     */
    public function test_nothing_outside_the_allowlist_survives(): void
    {
        $hostile = [
            "60\r\nBEGIN:VALARM\r\nACTION:AUDIO",   // a whole forged alarm
            "15\nDESCRIPTION:gotcha",               // bare newline
            '60; DROP TABLE',
            '60abc', 'abc', '-60', '0x3C', '1e3',
            '99999', '61',                          // plausible but not allowlisted
            '<script>', '../../etc/passwd', 'null', '{}',
        ];

        foreach ($hostile as $value) {
            $this->assertSame(
                [],
                IcsData::sanitizeReminders($value),
                'This was not rejected: ' . var_export($value, true)
            );
        }
    }

    /**
     * A JSON caller sending a bare number is making a choice, not
     * declining to make one. This fell through to the defaults until
     * running the sanitiser showed it: an explicit 61 was answered with
     * reminders at a day and an hour, which nobody asked for.
     */
    public function test_a_bare_integer_is_a_choice_not_an_absence(): void
    {
        $this->assertSame([60], IcsData::sanitizeReminders(60));
        $this->assertSame([], IcsData::sanitizeReminders(61));
        $this->assertSame([], IcsData::sanitizeReminders(-60));
    }

    public function test_a_hostile_value_mixed_with_a_good_one_keeps_only_the_good_one(): void
    {
        $this->assertSame(
            [60],
            IcsData::sanitizeReminders("60,15x,\r\nBEGIN:VALARM")
        );
    }

    /**
     * Every allowlisted key maps to a syntactically valid ICS duration.
     * If someone adds a key and forgets the trigger, this fails rather
     * than shipping an alarm no calendar can parse.
     */
    public function test_every_allowlisted_key_has_a_valid_trigger(): void
    {
        $this->assertNotEmpty(IcsData::REMINDER_TRIGGERS);

        foreach (IcsData::REMINDER_TRIGGERS as $minutes => $trigger) {
            $this->assertIsInt($minutes);
            $this->assertMatchesRegularExpression(
                '/^-P(?:\d+[WD]|T\d+[HM])$/',
                $trigger,
                "Key {$minutes} has an unparseable trigger: {$trigger}"
            );
        }

        foreach (IcsData::DEFAULT_REMINDERS as $minutes) {
            $this->assertArrayHasKey($minutes, IcsData::REMINDER_TRIGGERS, 'A default with no trigger.');
        }
    }
}
