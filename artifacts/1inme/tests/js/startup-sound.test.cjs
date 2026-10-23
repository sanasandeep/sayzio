const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/startup-sound.js', 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function boot({ dashboard = true, local = new Map(), session = new Map(), failure, hidden = false } = {}) {
    const events = new Map();
    const on = (name, fn) => { if (!events.has(name)) events.set(name, new Set()); events.get(name).add(fn); };
    const off = (name, fn) => events.get(name)?.delete(fn);
    const fire = (name, event = {}) => [...(events.get(name) || [])].forEach(fn => fn(event));
    const attrs = {};
    const button = { hidden: true, setAttribute: (k, v) => attrs[k] = v, querySelector: () => ({}), addEventListener: (_, fn) => button.click = fn };
    let calls = 0, pauses = 0;
    const document = { readyState: 'complete', visibilityState: hidden ? 'hidden' : 'visible',
        getElementById: () => ({ dataset: { dashboard: String(dashboard), src: '/audio/logo.mp3' } }),
        querySelectorAll: () => [button], addEventListener: on, removeEventListener: off };
    class Audio {
        addEventListener(_, fn) { this.playing = fn; }
        play() { calls++; if (failure) return Promise.reject({ name: failure }); this.playing(); return Promise.resolve(); }
        pause() { pauses++; }
    }
    const storage = map => ({ getItem: key => map.get(key), setItem: (key, value) => map.set(key, value) });
    vm.runInNewContext(source, { document, Audio, window: { localStorage: storage(local), sessionStorage: storage(session), addEventListener: on } });
    return { button, attrs, document, fire, local, session, calls: () => calls, pauses: () => pauses,
        unblock: () => failure = undefined,
        interact: (muting = false) => fire('click', { isTrusted: true, target: { closest: () => muting ? button : null } }) };
}
test('dashboard plays once across refresh and navigation', async () => {
    const session = new Map(); const app = boot({ session }); await flush();
    app.interact(); app.fire('visibilitychange'); await flush();
    assert.equal(app.calls(), 1); assert.equal(boot({ session }).calls(), 0);
    assert.equal(boot({ dashboard: false }).calls(), 0);
});
test('autoplay fallback consumes the session only after playback', async () => {
    const app = boot({ failure: 'NotAllowedError' }); await flush();
    assert.equal(app.session.size, 0); app.interact(true); assert.equal(app.calls(), 1);
    app.unblock(); app.interact(); await flush();
    assert.equal(app.calls(), 2); assert.equal(app.session.size, 1);
    app.interact(); assert.equal(app.calls(), 2);
});
test('mute cancels fallback and persists; enabling can play the pending logo', async () => {
    const app = boot({ failure: 'NotAllowedError' }); await flush();
    app.button.click(); app.interact(); assert.equal(app.calls(), 1);
    assert.equal(app.attrs['aria-pressed'], 'false'); assert.equal(boot({ local: app.local }).calls(), 0);
    app.unblock(); app.button.click(); await flush(); assert.equal(app.calls(), 2);
    app.button.click(); assert.equal(app.pauses(), 2);
});
test('background tab waits; unsupported media does not retry on interaction', async () => {
    const app = boot({ hidden: true }); assert.equal(app.calls(), 0);
    app.document.visibilityState = 'visible'; app.fire('visibilitychange'); await flush(); assert.equal(app.calls(), 1);
    const broken = boot({ failure: 'NotSupportedError' }); await flush(); broken.interact(); assert.equal(broken.calls(), 1);
});
