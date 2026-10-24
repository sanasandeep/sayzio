(() => {
    'use strict';
    const config = document.getElementById('sayzio-startup-sound');
    if (!config) return;

    const preferenceKey = 'sayzio.startup-sound.enabled';
    const sessionKey = 'sayzio.startup-sound.played';
    const read = (storage, key) => {
        try { return window[storage].getItem(key); } catch (_) { return null; }
    };
    const write = (storage, key, value) => {
        try { window[storage].setItem(key, value); } catch (_) { /* Private storage may be unavailable. */ }
    };
    let enabled = read('localStorage', preferenceKey) !== 'false';
    let played = read('sessionStorage', sessionKey) === 'true';
    let pending = false;
    let audio;
    const buttons = document.querySelectorAll('[data-startup-sound-toggle]');

    function paint() {
        buttons.forEach(button => {
            button.hidden = false;
            const label = `Turn Sayzio sounds ${enabled ? 'off' : 'on'}`;
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(enabled));
            button.querySelector('[data-startup-sound-icon]').className =
                enabled ? 'fas fa-volume-high' : 'fas fa-volume-xmark';
        });
    }

    function disarm() {
        document.removeEventListener('click', interact);
        document.removeEventListener('keydown', interact);
    }

    function interact(event) {
        // A mute click must never also trigger the pending sound.
        if (!event.isTrusted || event.target.closest?.('[data-startup-sound-toggle]')) return;
        if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
        play();
    }

    function play(reason = 'startup') {
        const chatOpening = reason === 'chat';
        if (!enabled || pending || document.visibilityState !== 'visible'
            || (!chatOpening && (played || config.dataset.dashboard !== 'true'))
            || (audio && audio.paused === false && !audio.ended)) return;
        if (!audio) {
            audio = new Audio(config.dataset.src);
            audio.preload = 'auto';
            audio.addEventListener('playing', () => {
                played = true;
                write('sessionStorage', sessionKey, 'true');
                disarm();
            });
        }
        if (chatOpening) audio.currentTime = 0;
        pending = true;
        audio.play().catch(error => {
            if (error.name === 'NotAllowedError' && !chatOpening && enabled && !played) {
                document.addEventListener('click', interact);
                document.addEventListener('keydown', interact);
            }
            // Missing media or unsupported audio must not interfere with the dashboard.
        }).finally(() => { pending = false; });
    }

    buttons.forEach(button => button.addEventListener('click', () => {
        enabled = !enabled;
        write('localStorage', preferenceKey, String(enabled));
        paint();
        if (enabled) play();
        else {
            disarm();
            if (audio) audio.pause();
        }
    }));
    window.addEventListener('storage', event => {
        if (event.key !== preferenceKey) return;
        enabled = event.newValue !== 'false';
        paint();
        if (!enabled) {
            disarm();
            if (audio) audio.pause();
        }
    });
    // Chat openings are separate from the once-per-session dashboard greeting.
    window.addEventListener('sayzio:zio-open', () => play('chat'));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') play();
        else if (audio) audio.pause();
    });
    window.addEventListener('pagehide', () => {
        disarm();
        if (audio) audio.pause();
    });
    paint();
    if (document.readyState === 'complete') play();
    else window.addEventListener('load', play, { once: true });
})();
