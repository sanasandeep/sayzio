@once
<script>
window.addressAssist = function (options) {
    return Object.assign({
        billingCountry: '', country: '', cityVal: '', regionVal: '', taxKind: 'NONE',
        cityEdited: !!options.cityVal, regionEdited: !!options.regionVal, lookupTimer: null, lookupVersion: 0,
        postalName: 'postal_code', regionCodes: false, places: [], lookupMessage: '',
        lookupUrl: @js(route('user.profile.postal.lookup')),
        regions: @js(['IN' => \App\Services\TaxCalculator::IN_STATES, 'US' => \App\Services\TaxCalculator::US_STATES]),
        onCountryInput(value) {
            this.country = value; this.billingCountry = value; this.places = [];
            this.scheduleLookup();
        },
        scheduleLookup() {
            this.lookupVersion++; clearTimeout(this.lookupTimer);
            this.lookupTimer = setTimeout(() => this.doLookup(), 600);
        },
        applyPlace(place) {
            if (!this.cityEdited || !this.cityVal) this.cityVal = place.city || this.cityVal;
            if (!this.regionEdited || !this.regionVal) {
                this.regionVal = this.regionCodes ? (place.region_code || place.region || this.regionVal) : (place.region || place.region_code || this.regionVal);
            }
            this.lookupMessage = 'Address suggestions filled. Check them before saving.';
        },
        async doLookup() {
            const country = (this.country || this.billingCountry || '').trim().toUpperCase();
            const field = this.$el.querySelector('[name="' + this.postalName + '"]') || this.$el.querySelector('[data-postal]');
            const postal = field ? field.value.trim() : '';
            const version = ++this.lookupVersion;
            this.places = [];
            if (!/^[A-Z]{2}$/.test(country) || !postal) { this.lookupMessage = 'Choose a country and enter a PIN / ZIP code.'; return; }
            this.lookupMessage = 'Looking up postal code…';
            try {
                const response = await fetch(this.lookupUrl + '?country=' + encodeURIComponent(country) + '&postal_code=' + encodeURIComponent(postal), { headers: {'X-Requested-With':'XMLHttpRequest'} });
                if (!response.ok) throw new Error('Lookup unavailable');
                const data = await response.json();
                if (version !== this.lookupVersion) return;
                this.places = data.places || (data.city ? [data] : []);
                if (this.places.length === 1) this.applyPlace(this.places[0]);
                else this.lookupMessage = this.places.length ? 'Choose your locality below.' : 'No postal match found. Enter city and state manually.';
            } catch (error) {
                if (version === this.lookupVersion) this.lookupMessage = 'Lookup unavailable. You can enter the address manually.';
            }
        }
    }, options);
};
</script>
@endonce
