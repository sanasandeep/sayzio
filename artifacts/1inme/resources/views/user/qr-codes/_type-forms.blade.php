@php
    $field = function ($name, $label, $type = 'text', $extras = '') {
        $bind = "payload['$name']";
        $cls = "w-full px-3 py-2 text-sm rounded-lg outline-none";
        $style = "background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);";
        return [$bind, $cls, $style];
    };
@endphp
<style>.qr-input,.qr-textarea,.qr-select{width:100%;padding:8px 12px;font-size:13px;border-radius:8px;outline:none;background:var(--bg-glass-input);border:1px solid var(--border-glass);color:var(--text-primary);}</style>

<div class="space-y-3">
    {{-- TEXT --}}
    <template x-if="type === 'text'">
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Text</label>
            <textarea x-model="payload.text" rows="4" maxlength="1500" class="qr-textarea" placeholder="Any text…"></textarea>
        </div>
    </template>

    {{-- URL --}}
    <template x-if="type === 'url'">
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">URL</label>
            <input type="url" x-model="payload.url" class="qr-input" placeholder="https://example.com">
        </div>
    </template>

    {{-- PHONE --}}
    <template x-if="type === 'phone'">
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Phone number (with country code)</label>
            <input type="tel" x-model="payload.number" class="qr-input" placeholder="+15551234567">
        </div>
    </template>

    {{-- SMS --}}
    <template x-if="type === 'sms'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Phone number</label>
                <input type="tel" x-model="payload.number" class="qr-input" placeholder="+15551234567">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Message (optional)</label>
                <textarea x-model="payload.message" rows="3" class="qr-textarea" placeholder="Pre-filled SMS body"></textarea>
            </div>
        </div>
    </template>

    {{-- EMAIL --}}
    <template x-if="type === 'email'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Email</label>
                <input type="email" x-model="payload.email" class="qr-input" placeholder="hello@example.com">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Subject</label>
                <input type="text" x-model="payload.subject" class="qr-input">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Body</label>
                <textarea x-model="payload.body" rows="3" class="qr-textarea"></textarea>
            </div>
        </div>
    </template>

    {{-- WHATSAPP --}}
    <template x-if="type === 'whatsapp'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">WhatsApp number (with country code, no +)</label>
                <input type="tel" x-model="payload.number" class="qr-input" placeholder="15551234567">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Pre-filled message</label>
                <textarea x-model="payload.message" rows="3" class="qr-textarea" placeholder="Hi, I'd like to know more about…"></textarea>
            </div>
        </div>
    </template>

    {{-- FACETIME --}}
    <template x-if="type === 'facetime'">
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Phone number or Apple ID</label>
            <input type="text" x-model="payload.contact" class="qr-input" placeholder="+15551234567 or user@icloud.com">
        </div>
    </template>

    {{-- LOCATION --}}
    <template x-if="type === 'location'">
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Latitude</label>
                    <input type="number" step="any" x-model.number="payload.lat" class="qr-input" placeholder="37.7749">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Longitude</label>
                    <input type="number" step="any" x-model.number="payload.lng" class="qr-input" placeholder="-122.4194">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Label (optional)</label>
                <input type="text" x-model="payload.label" class="qr-input" placeholder="My office">
            </div>
            <button type="button" @click="navigator.geolocation && navigator.geolocation.getCurrentPosition(p => { payload.lat = p.coords.latitude; payload.lng = p.coords.longitude; })"
                    class="text-xs px-3 py-1.5 rounded" style="background: var(--bg-glass-hover); color: var(--text-primary);">
                <i class="fas fa-location-arrow"></i> Use my location
            </button>
        </div>
    </template>

    {{-- WIFI --}}
    <template x-if="type === 'wifi'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Network name (SSID)</label>
                <input type="text" x-model="payload.ssid" class="qr-input">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Encryption</label>
                <select x-model="payload.encryption" class="qr-select">
                    <option value="WPA">WPA / WPA2 / WPA3</option>
                    <option value="WEP">WEP</option>
                    <option value="nopass">No password</option>
                </select>
            </div>
            <div x-show="payload.encryption !== 'nopass'">
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Password</label>
                <input type="text" x-model="payload.password" class="qr-input">
            </div>
            <label class="inline-flex items-center gap-2 text-xs" style="color: var(--text-muted);">
                <input type="checkbox" x-model="payload.hidden"> Hidden network
            </label>
        </div>
    </template>

    {{-- EVENT --}}
    <template x-if="type === 'event'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Event title</label>
                <input type="text" x-model="payload.title" class="qr-input">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Starts</label>
                    <input type="datetime-local" x-model="payload.start" class="qr-input">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Ends</label>
                    <input type="datetime-local" x-model="payload.end" class="qr-input">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Location</label>
                <input type="text" x-model="payload.location" class="qr-input">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Description</label>
                <textarea x-model="payload.description" rows="2" class="qr-textarea"></textarea>
            </div>
        </div>
    </template>

    {{-- VCARD --}}
    <template x-if="type === 'vcard'">
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <input type="text" x-model="payload.first_name" placeholder="First name *" class="qr-input">
                <input type="text" x-model="payload.last_name" placeholder="Last name" class="qr-input">
            </div>
            <input type="text" x-model="payload.organization" placeholder="Organization" class="qr-input">
            <input type="text" x-model="payload.title" placeholder="Job title" class="qr-input">
            <input type="tel" x-model="payload.phone" placeholder="Phone" class="qr-input">
            <input type="email" x-model="payload.email" placeholder="Email" class="qr-input">
            <input type="url" x-model="payload.website" placeholder="Website" class="qr-input">
            <input type="text" x-model="payload.address" placeholder="Address" class="qr-input">
        </div>
    </template>

    {{-- CRYPTO --}}
    <template x-if="type === 'crypto'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Currency</label>
                <select x-model="payload.currency" class="qr-select">
                    <option value="bitcoin">Bitcoin (BTC)</option>
                    <option value="ethereum">Ethereum (ETH)</option>
                    <option value="litecoin">Litecoin (LTC)</option>
                    <option value="dogecoin">Dogecoin (DOGE)</option>
                </select>
            </div>
            <input type="text" x-model="payload.address" placeholder="Wallet address" class="qr-input">
            <input type="number" step="any" x-model.number="payload.amount" placeholder="Amount (optional)" class="qr-input">
            <input type="text" x-model="payload.label" placeholder="Label (optional)" class="qr-input">
        </div>
    </template>

    {{-- PAYPAL --}}
    <template x-if="type === 'paypal'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">PayPal.Me username</label>
                <input type="text" x-model="payload.username" class="qr-input" placeholder="yourusername">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" step="0.01" x-model.number="payload.amount" placeholder="Amount" class="qr-input">
                <select x-model="payload.currency" class="qr-select">
                    <option value="">— Currency —</option>
                    <option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="INR">INR</option><option value="CAD">CAD</option><option value="AUD">AUD</option>
                </select>
            </div>
        </div>
    </template>

    {{-- UPI --}}
    <template x-if="type === 'upi'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">UPI ID (VPA)</label>
                <input type="text" x-model="payload.vpa" class="qr-input" placeholder="yourname@bank">
            </div>
            <input type="text" x-model="payload.name" placeholder="Payee name" class="qr-input">
            <input type="number" step="0.01" x-model.number="payload.amount" placeholder="Amount in ₹ (optional)" class="qr-input">
            <input type="text" x-model="payload.note" placeholder="Note (optional)" class="qr-input">
        </div>
    </template>

    {{-- EPC SEPA --}}
    <template x-if="type === 'epc'">
        <div class="space-y-3">
            <input type="text" x-model="payload.name" placeholder="Beneficiary name *" maxlength="70" class="qr-input">
            <input type="text" x-model="payload.iban" placeholder="IBAN *" class="qr-input">
            <input type="text" x-model="payload.bic" placeholder="BIC (optional)" maxlength="11" class="qr-input">
            <input type="number" step="0.01" x-model.number="payload.amount" placeholder="Amount in € (optional)" class="qr-input">
            <input type="text" x-model="payload.remittance" placeholder="Reference / message" maxlength="140" class="qr-input">
        </div>
    </template>

    {{-- PIX --}}
    <template x-if="type === 'pix'">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">PIX key (CPF, email, phone or random)</label>
                <input type="text" x-model="payload.key" class="qr-input" maxlength="77">
            </div>
            <input type="text" x-model="payload.merchant_name" placeholder="Receiver name *" maxlength="25" class="qr-input">
            <input type="text" x-model="payload.merchant_city" placeholder="City *" maxlength="15" class="qr-input">
            <input type="number" step="0.01" x-model.number="payload.amount" placeholder="Amount in R$ (optional)" class="qr-input">
            <input type="text" x-model="payload.txid" placeholder="Transaction ID (optional)" maxlength="25" class="qr-input">
            <input type="text" x-model="payload.description" placeholder="Description (optional)" maxlength="50" class="qr-input">
        </div>
    </template>
</div>
