(() => {
    'use strict';
    const root = document.getElementById('attendance-app');
    if (!root) return;
    const get = name => document.getElementById('attendance-' + name);
    const dialog = get('capture');
    const video = get('video');
    const photo = get('photo');
    const save = get('submit');
    let challenge, stream, image, capturedAt, location, generation = 0, submitting = false;
    const status = message => { get('capture-status').textContent = message; };
    const token = document.querySelector('meta[name="csrf-token"]').content;
    async function post(url, body) {
        const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body });
        if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session expired or the server returned an unexpected response. Refresh and sign in again.');
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Unable to save. Check your connection and retry.');
        return result;
    }
    function stop() {
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null; video.srcObject = null;
    }
    function reset() {
        generation++; stop(); challenge = null; image = null; location = null; capturedAt = null;
        photo.removeAttribute('src'); photo.hidden = true; video.hidden = true;
        get('take').hidden = true; get('retake').hidden = true;
        get('enable').disabled = false; save.disabled = true;
    }
    function ready() {
        const validLocation = !challenge?.require_location || (location && Date.now() - location.timestamp < 60000);
        save.disabled = submitting || !challenge || Date.now() >= Date.parse(challenge.expires_at)
            || (challenge.require_photo && !image) || !validLocation;
    }
    root.querySelectorAll('[data-attendance-action]').forEach(button => button.addEventListener('click', async () => {
        reset(); dialog.showModal(); status('Preparing a fresh attendance challenge…');
        get('pose').textContent = ''; get('expiry').textContent = '';
        get('capture-title').textContent = button.textContent;
        const current = generation;
        const body = new FormData(); body.set('action', button.dataset.attendanceAction);
        if (button.dataset.attendanceAction === 'in' && root.dataset.qrToken) body.set('qr_token',root.dataset.qrToken);
        try {
            const result = await post(root.dataset.challengeUrl, body);
            if (current !== generation || !dialog.open) return;
            challenge = result;
            get('pose').textContent = result.require_photo ? result.pose : 'Confirm your attendance action.';
            get('enable').textContent = result.photo_available ? 'Enable camera & location' : 'Enable location';
            status(result.require_photo ? 'Capture a selfie with the requested pose. Reviewers will check it manually.' : (result.require_location ? 'Enable location before saving.' : 'Ready to save. Camera and location are optional.'));
            ready();
        } catch (error) { if (current === generation) status(error.message); }
    }));
    get('enable').addEventListener('click', async () => {
        if (!challenge || Date.now() >= Date.parse(challenge.expires_at)) { status('Challenge expired. Close and start again.'); return; }
        const current = generation;
        get('enable').disabled = true; status('Waiting for permissions…');
        // Both requests start only after this explicit action. Optional GPS errors do not block a punch.
        if (navigator.geolocation) navigator.geolocation.getCurrentPosition(position => {
            if (current !== generation || !dialog.open) return;
            location = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy, timestamp: position.timestamp };
            status('Location received (±' + Math.round(location.accuracy) + ' m). ' + (image ? 'Ready to save.' : 'Capture the requested pose.')); ready();
        }, error => {
            if (current !== generation) return;
            status('Location unavailable: ' + error.message + (challenge.require_location ? ' Location is required; retry when ready.' : ' You can continue without it.'));
            get('enable').disabled = false; ready();
        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
        else if (challenge.require_location) { status('Location is unavailable. Use HTTPS in a supported browser.'); get('enable').disabled = false; }
        if (!challenge.photo_available) return;
        try {
            if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera requires HTTPS and a supported browser.');
            stop();
            const acquired = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } }, audio: false });
            if (current !== generation || !dialog.open || Date.now() >= Date.parse(challenge.expires_at)) { acquired.getTracks().forEach(track => track.stop()); return; }
            stream = acquired; video.srcObject = stream; await video.play();
            video.hidden = false; get('take').hidden = false;
            status('Camera ready. Show the requested pose and take a selfie.');
        } catch (error) { if (current === generation) { status(error.message); get('enable').disabled = false; } }
    });
    get('take').addEventListener('click', () => {
        if (!challenge || !stream || !video.videoWidth || Date.now() >= Date.parse(challenge.expires_at)) { status('Camera is not ready or the challenge expired.'); return; }
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const current = generation;
        canvas.toBlob(blob => {
            if (current !== generation || !dialog.open) return;
            image = blob; capturedAt = new Date().toISOString();
            photo.src = canvas.toDataURL('image/jpeg', 0.85); photo.hidden = false; video.hidden = true;
            get('take').hidden = true; get('retake').hidden = false;
            status('Check your photo, then save attendance.'); ready();
        }, 'image/jpeg', 0.85);
    });
    get('retake').addEventListener('click', () => {
        image = null; capturedAt = null; photo.hidden = true; video.hidden = false;
        get('take').hidden = false; get('retake').hidden = true; ready();
    });
    save.addEventListener('click', async () => {
        ready(); if (save.disabled) return;
        if (!navigator.onLine) { status('You are offline. Reconnect and start a fresh capture; punches are not queued.'); return; }
        const body = new FormData(); body.set('challenge_id', challenge.id);
        if (image) { body.set('photo', image, 'capture.jpg'); body.set('captured_at', capturedAt); }
        if (location && Date.now() - location.timestamp < 60000) {
            for (const field of ['latitude','longitude','accuracy']) body.set(field, String(location[field]));
        }
        submitting = true; ready(); status('Saving attendance…');
        try { await post(root.dataset.punchUrl, body); stop(); window.location.assign(root.dataset.indexUrl); }
        catch (error) { status(error.message); submitting = false; ready(); }
    });
    get('capture').querySelector('[data-close-capture]').addEventListener('click', () => { if (!submitting) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (submitting) event.preventDefault(); });
    dialog.addEventListener('close', reset);
    window.addEventListener('pagehide', stop);
    setInterval(() => {
        if (!dialog.open || !challenge) return;
        const seconds = Math.max(0, Math.ceil((Date.parse(challenge.expires_at) - Date.now()) / 1000));
        get('expiry').textContent = seconds ? 'Challenge expires in ' + seconds + ' seconds.' : 'Challenge expired. Close and start again.';
        if (!seconds) stop(); ready();
    }, 1000);
    root.querySelector('[data-correction-local]')?.addEventListener('change', event => {
        const value = event.target.value;
        root.querySelector('[data-correction-iso]').value = value ? new Date(value).toISOString() : '';
    });

    // Bulk selection is based on the actual staff directory; no membership changes occur here.
    root.querySelector('[data-attendance-group]')?.addEventListener('change', event => {
        root.querySelectorAll('[data-attendance-staff] option').forEach(option => { option.selected = event.target.value === 'all' || option.dataset.group === event.target.value; });
    });
    const qrDialog = document.getElementById('attendance-qr-scanner');
    const qrVideo = root.querySelector('[data-qr-video]');
    const qrStatus = root.querySelector('[data-qr-status]');
    let qrStream, qrFrame, stationActive = false, stationExpiry;
    function stopQr() { if (qrFrame) cancelAnimationFrame(qrFrame); if (qrStream) qrStream.getTracks().forEach(track => track.stop()); qrStream = null; if (qrVideo) qrVideo.srcObject = null; }
    function openScanned(value) {
        try {
            const url = new URL(value, window.location.origin);
            const pattern = new URL(root.dataset.scanBase, window.location.origin);
            const prefix = pattern.pathname.replace('__TOKEN__','');
            const token = url.pathname.slice(prefix.length);
            if (url.origin !== window.location.origin || !url.pathname.startsWith(prefix) || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(token) || url.search || url.hash) throw new Error('Scan a workplace QR from this attendance area.');
            stopQr(); window.location.assign(url.href);
        } catch (error) { qrStatus.textContent = error.message; }
    }
    root.querySelector('[data-open-qr-scanner]')?.addEventListener('click', () => qrDialog.showModal());
    root.querySelector('[data-close-qr]')?.addEventListener('click', () => qrDialog.close());
    qrDialog?.addEventListener('close', stopQr);
    root.querySelector('[data-qr-paste]')?.addEventListener('submit', event => { event.preventDefault(); openScanned(root.querySelector('[data-qr-url]').value); });
    root.querySelector('[data-start-qr-camera]')?.addEventListener('click', async () => {
        stopQr(); qrStatus.textContent = 'Waiting for camera permission…';
        try {
            const stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});
            if (!qrDialog.open) { stream.getTracks().forEach(track => track.stop()); return; }
            qrStream = stream; qrVideo.srcObject = stream; await qrVideo.play(); qrStatus.textContent = 'Point the camera at the current workplace QR.';
            const canvas = document.createElement('canvas'), context = canvas.getContext('2d',{willReadFrequently:true});
            function decode() {
                if (!qrStream) return;
                if (qrVideo.readyState >= 2 && qrVideo.videoWidth) {
                    const ratio = Math.min(1,720/qrVideo.videoWidth); canvas.width = Math.round(qrVideo.videoWidth*ratio); canvas.height = Math.round(qrVideo.videoHeight*ratio);
                    context.drawImage(qrVideo,0,0,canvas.width,canvas.height); const pixels = context.getImageData(0,0,canvas.width,canvas.height);
                    const code = window.jsQR?.(pixels.data,pixels.width,pixels.height);
                    if (code) { openScanned(code.data); if (!qrStream) return; }
                }
                qrFrame = requestAnimationFrame(decode);
            }
            decode();
        } catch (error) { qrStatus.textContent = 'Camera unavailable: '+error.message+'. You can paste a scanned QR link instead.'; }
    });
    async function refreshStation() {
        if (!stationActive || document.visibilityState !== 'visible') return;
        const host = root.querySelector('[data-qr-station]');
        try {
            const result = await post(root.dataset.qrSessionUrl,new FormData());
            if (!stationActive) return;
            const image = new Image(); image.alt = 'Workplace attendance QR'; image.width = image.height = 240; image.src = 'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(result.svg);
            root.querySelector('[data-qr-image]').replaceChildren(image); host.hidden = false; stationExpiry = Date.parse(result.expires_at);
        } catch (error) { root.querySelector('[data-qr-expiry]').textContent = error.message; }
    }
    root.querySelector('[data-start-qr-station]')?.addEventListener('click', event => {
        stationActive = !stationActive; event.target.textContent = stationActive ? 'Stop QR display' : 'Start QR display';
        if (stationActive) refreshStation(); else { root.querySelector('[data-qr-station]').hidden = true; stationExpiry = null; }
    });
    setInterval(refreshStation,45000);
    setInterval(() => { if (stationActive && stationExpiry) { const left = Math.max(0,Math.ceil((stationExpiry-Date.now())/1000)); root.querySelector('[data-qr-expiry]').textContent = left ? 'Code expires in '+left+' seconds.' : 'Code expired. Waiting for refresh…'; } },1000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState !== 'visible') stopQr(); else if (stationActive) refreshStation(); });
    window.addEventListener('pagehide',stopQr);

    const requestForm = root.querySelector('[data-self-service-form]');
    function requestFields() {
        if (!requestForm) return;
        const type = requestForm.querySelector('[data-request-type]').value;
        requestForm.querySelectorAll('[data-request-for]').forEach(label => {
            const visible = label.dataset.requestFor.split(',').includes(type); label.hidden = !visible;
            label.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = !visible; });
        });
        const required = { title:['training','general'], requested_days:['encashment'], punch_id:['correction'], proposed_local:['correction'], minutes:['overtime'], category:['general'] };
        Object.entries(required).forEach(([name, types]) => { const input = requestForm.elements.namedItem(name); if (input) input.required = types.includes(type); });
    }
    requestForm?.querySelector('[data-request-type]').addEventListener('change',requestFields);
    requestFields();
})();
