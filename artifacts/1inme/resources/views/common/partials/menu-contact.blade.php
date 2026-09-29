{{--
    Name and phone, checked before the order is sent.

    Sana, 2026-09-28: "when placing order: name and phone mandatory" and
    "1) Name + phone mandatory for all types".

    ---- Why check here at all when the server checks --------------------

    Because the server's answer arrives after a round trip, as one line of
    red under a button that has been saying "Placing…" for a second, and
    it does not put the cursor in the empty field. Someone holding a phone
    at a table should be told which box is empty, with the keyboard already
    in it, before anything is sent.

    The server still refuses -- this is not the enforcement, it is the
    courtesy. Both exist for different reasons and neither replaces the
    other.

    ---- What counts as a phone number ------------------------------------

    Six or more digits, wherever they sit among spaces, dashes, brackets
    and a leading +. Anything stricter rejects real numbers: India alone
    has ten-digit mobiles written five different ways, and a menu is the
    wrong place to argue with someone about the shape of their own phone
    number. The point of the field is that the kitchen can ring back, not
    that the string parses.
--}}
<script>
(function () {
    window.menuContact = {
        /** Digits only, so formatting never decides whether this passes. */
        digits: function (value) {
            return String(value || '').replace(/[^0-9]/g, '');
        },

        /**
         * The first problem, with the field focused, or null when both are
         * filled in.
         */
        check: function () {
            var name = document.getElementById('fName');
            var phone = document.getElementById('fPhone');

            if (name && !name.value.trim()) {
                name.focus();
                return 'Please add your name so we know whose order this is.';
            }

            if (phone) {
                var digits = this.digits(phone.value);
                if (!digits) {
                    phone.focus();
                    return 'Please add a phone number in case we need to reach you.';
                }
                if (digits.length < 6) {
                    phone.focus();
                    return 'That phone number looks too short. Please check it.';
                }
            }

            return null;
        },
    };
})();
</script>
