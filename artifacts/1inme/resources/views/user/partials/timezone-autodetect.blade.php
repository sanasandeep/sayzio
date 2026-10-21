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
--}}
@once
@push('scripts')
<script>
(function () {
    var selects = document.querySelectorAll('select[data-tz-autodetect]');
    if (!selects.length) return;

    var tz;
    try {
        tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (e) {
        return;            // no Intl, or it refused -- the server default stands
    }
    if (!tz) return;

    Array.prototype.forEach.call(selects, function (sel) {
        // An identifier the server did not offer (a very new zone, or an
        // alias like Asia/Calcutta) must not blank the control, so only
        // move to a value that is actually in the list.
        var present = Array.prototype.some.call(sel.options, function (o) {
            return o.value === tz;
        });
        if (!present || sel.value === tz) return;

        sel.value = tz;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
</script>
@endpush
@endonce
