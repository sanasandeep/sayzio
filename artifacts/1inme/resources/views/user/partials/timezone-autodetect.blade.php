{{--
    Preselect the viewer's actual timezone on a NEW-record form.

    The server has already chosen a sensible value -- the user's saved
    timezone, else the platform default (IST) via PlatformTimezone -- so
    this page is correct with JavaScript disabled. This only improves on
    it when the browser knows better, which it usually does: someone
    creating an event is almost always creating it where they are.

    Deliberately NOT applied to edit forms. A stored timezone is a
    decision the organiser already made, and an attendee-facing event
    must not silently change timezone because the organiser opened the
    editor from another country.

    The caller opts in per control:

        <select name="timezone" data-tz-autodetect>

    and emits that attribute only when `old('timezone')` is empty, so a
    validation redisplay keeps whatever the person actually submitted.

    ---------------------------------------------------------------
    WHY THE ALIAS MAP EXISTS

    Chrome in India reports `Asia/Calcutta`. PHP's
    timezone_identifiers_list(), which builds the options, offers
    `Asia/Kolkata` and not `Asia/Calcutta`. Chrome's own
    Intl.supportedValuesOf('timeZone') contains `Asia/Calcutta` and NOT
    `Asia/Kolkata` -- so the two sides use different canonical names for
    the same zone and neither will convert: ICU treats the backward name
    as canonical, IANA/PHP treats the modern one as canonical.

    Without this map the detection finds no matching option and does
    nothing, which makes the whole feature a no-op for most Indian
    users -- the people it was built for.

    Neither PHP's intl extension (IntlTimeZone::getCanonicalID returns
    the input here) nor transition-data fingerprinting resolves it, so
    the mapping is explicit. It covers the backward names browsers
    actually report, not all 81 that exist; an unmapped one still falls
    through safely to the server's value.
--}}
@once
@push('scripts')
<script>
(function () {
    var selects = document.querySelectorAll('select[data-tz-autodetect]');
    if (!selects.length) return;

    // Backward-compatible identifiers a browser may report, mapped to the
    // name PHP actually offers. Every key here is absent from
    // timezone_identifiers_list() and every value is present in it --
    // BrowserTimezoneAliasesResolveToRealOptionsTest holds that invariant,
    // because a tzdata update can move a name from one side to the other.
    var ALIASES = {
        'Asia/Calcutta': 'Asia/Kolkata',
        'Asia/Saigon': 'Asia/Ho_Chi_Minh',
        'Asia/Katmandu': 'Asia/Kathmandu',
        'Asia/Rangoon': 'Asia/Yangon',
        'Asia/Dacca': 'Asia/Dhaka',
        'Asia/Thimbu': 'Asia/Thimphu',
        'Asia/Macao': 'Asia/Macau',
        'Asia/Ulan_Bator': 'Asia/Ulaanbaatar',
        'Asia/Chungking': 'Asia/Shanghai',
        'Asia/Harbin': 'Asia/Shanghai',
        'Asia/Istanbul': 'Europe/Istanbul',
        'Europe/Kiev': 'Europe/Kyiv',
        'Europe/Uzhgorod': 'Europe/Kyiv',
        'Europe/Zaporozhye': 'Europe/Kyiv',
        'Europe/Nicosia': 'Asia/Nicosia',
        'America/Buenos_Aires': 'America/Argentina/Buenos_Aires',
        'America/Cordoba': 'America/Argentina/Cordoba',
        'America/Mendoza': 'America/Argentina/Mendoza',
        'America/Indianapolis': 'America/Indiana/Indianapolis',
        'America/Godthab': 'America/Nuuk',
        'America/Montreal': 'America/Toronto',
        'America/Santa_Isabel': 'America/Tijuana',
        'Africa/Asmera': 'Africa/Asmara',
        'Atlantic/Faeroe': 'Atlantic/Faroe',
        'Pacific/Ponape': 'Pacific/Pohnpei',
        'Pacific/Truk': 'Pacific/Chuuk',
        'Australia/Canberra': 'Australia/Sydney',
        'Australia/NSW': 'Australia/Sydney',
        'US/Eastern': 'America/New_York',
        'US/Central': 'America/Chicago',
        'US/Mountain': 'America/Denver',
        'US/Pacific': 'America/Los_Angeles',
        'US/Arizona': 'America/Phoenix',
        'US/Hawaii': 'Pacific/Honolulu',
        'US/Alaska': 'America/Anchorage'
    };

    var reported;
    try {
        reported = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (e) {
        return;            // no Intl, or it refused -- the server default stands
    }
    if (!reported) return;

    Array.prototype.forEach.call(selects, function (sel) {
        var options = Array.prototype.map.call(sel.options, function (o) { return o.value; });

        var tz = options.indexOf(reported) !== -1
            ? reported
            : (ALIASES[reported] && options.indexOf(ALIASES[reported]) !== -1 ? ALIASES[reported] : null);

        // An identifier the server does not offer and the map does not
        // cover must not blank the control: leave the server's value.
        if (!tz || sel.value === tz) return;

        sel.value = tz;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
</script>
@endpush
@endonce
