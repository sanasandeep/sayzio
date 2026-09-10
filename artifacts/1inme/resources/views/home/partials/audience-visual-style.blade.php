{{-- Styles for the three audience panel mocks (see audience-visual.blade.php).
     Included once by the audience section, OUTSIDE the <template> elements,
     because a template's content is inert and a <style> inside one never
     reaches the document. --}}
<style>
    /* ===== audience panel mocks (av- = audience visual) ===== */
    .av { width: 100%; max-width: 330px; display: grid; gap: 14px; }
    .av-cap {
        margin: 0; font-size: 10.5px; font-weight: 700; letter-spacing: .11em;
        text-transform: uppercase; opacity: .5; text-align: center;
        text-wrap: balance;
    }

    /* ---- the phone shell, shared by the creator and networking mocks ---- */
    .av-phone {
        width: 100%; border-radius: 26px; padding: 10px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.05);
        box-shadow: 0 30px 60px -30px rgba(0,0,0,.6);
    }
    html.light-mode .av-phone { border-color: #E2E5F0; background: #fff; box-shadow: 0 30px 60px -34px rgba(11,16,51,.28); }
    .av-screen {
        border-radius: 18px; overflow: hidden; position: relative;
        background: #0E1226; color: #EEF1FF;
        padding: 16px 14px 14px;
    }
    html.light-mode .av-screen { background: #F7F8FC; color: #0F172A; }
    /* The screen's own header band takes the panel's pair, which is the one
       place in the mock the accent belongs: it is the user's chosen theme. */
    .av-screen::before {
        content: ""; position: absolute; inset: 0 0 auto 0; height: 74px;
        background: linear-gradient(135deg, var(--g1, #3d6bff), var(--g2, #7c5cff));
        opacity: .9;
    }
    .av-notch {
        position: absolute; top: 7px; left: 50%; transform: translateX(-50%);
        width: 52px; height: 4px; border-radius: 999px;
        background: rgba(255,255,255,.5); z-index: 2;
    }

    .av-ava {
        position: relative; z-index: 1; width: 52px; height: 52px; border-radius: 50%;
        margin: 26px auto 0; display: grid; place-items: center;
        font-weight: 800; font-size: 17px; color: #fff;
        background: linear-gradient(140deg, var(--g2, #7c5cff), var(--g1, #3d6bff));
        border: 3px solid #0E1226;
    }
    html.light-mode .av-ava { border-color: #F7F8FC; }
    .av-name { position: relative; text-align: center; font-weight: 800; font-size: 14px; margin-top: 8px; }
    .av-handle { position: relative; text-align: center; font-size: 11px; opacity: .6; margin-top: 1px; }

    .av-rows { position: relative; display: grid; gap: 7px; margin-top: 12px; }
    .av-row {
        display: flex; align-items: center; gap: 9px;
        padding: 9px 11px; border-radius: 11px; font-size: 11.5px; font-weight: 600;
        border: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.06);
    }
    html.light-mode .av-row { border-color: #E6E8F2; background: #fff; }
    .av-row i { font-size: 11px; opacity: .8; width: 13px; text-align: center; }
    .av-row .av-row-n { margin-left: auto; font-size: 10.5px; font-weight: 700; opacity: .5; }
    /* The one row that is doing the earning gets the accent, because the
       point of the creator mock is that the page can take money. */
    .av-row.is-pay {
        border-color: color-mix(in srgb, var(--g1, #3d6bff) 55%, transparent);
        background: color-mix(in srgb, var(--g1, #3d6bff) 16%, transparent);
    }
    /* 16% of the accent over a near-white screen is indistinguishable from
       the plain rows, which loses the one thing this row is here to say. */
    html.light-mode .av-row.is-pay { background: color-mix(in srgb, var(--g1, #3d6bff) 15%, #fff); }

    /* ---- floating stat bubble, hung off the phone ---- */
    .av-float {
        position: absolute; z-index: 3;
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 11px; border-radius: 999px; white-space: nowrap;
        font-size: 10.5px; font-weight: 700; color: #fff;
        background: rgba(15,18,28,.9); border: 1px solid rgba(255,255,255,.12);
        box-shadow: 0 14px 30px -14px rgba(0,0,0,.7);
    }
    .av-float i { font-size: 9px; color: var(--g1, #3d6bff); }
    .av-float--tr { top: 62px; right: -16px; }
    /* Hung off the phone's bottom edge rather than over its face: at
       `bottom: 40px` it sat across the last link row. */
    .av-float--bl { bottom: -13px; left: 4px; }

    .av-stage { position: relative; }

    /* ---- business: a printed pack, and the QR that outlives the print ---- */
    .av-pack {
        position: relative; border-radius: 16px; padding: 18px 16px;
        border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.05);
        display: grid; gap: 12px; justify-items: center;
    }
    html.light-mode .av-pack { border-color: #E2E5F0; background: #fff; }
    .av-qr {
        width: 96px; height: 96px; border-radius: 12px; padding: 8px; background: #fff;
        display: grid; grid-template-columns: repeat(7, 1fr); grid-template-rows: repeat(7, 1fr); gap: 2px;
    }
    .av-qr span { border-radius: 1px; background: #0E1226; }
    .av-qr span.o { background: transparent; }
    .av-url {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11.5px; font-weight: 600;
    }
    .av-swap {
        display: grid; gap: 6px; width: 100%; margin-top: 2px;
    }
    .av-swap-row {
        display: flex; align-items: center; gap: 8px;
        padding: 7px 10px; border-radius: 9px; font-size: 11px;
        border: 1px solid rgba(255,255,255,.09); background: rgba(255,255,255,.04);
    }
    html.light-mode .av-swap-row { border-color: #E6E8F2; background: #F7F8FC; }
    .av-swap-row.is-now {
        border-color: color-mix(in srgb, var(--g1, #3d6bff) 55%, transparent);
        background: color-mix(in srgb, var(--g1, #3d6bff) 14%, transparent);
    }
    .av-swap-row s { opacity: .45; }
    .av-swap-row .av-when { margin-left: auto; font-size: 10px; font-weight: 700; opacity: .55; }

    /* ---- networking: the tap ---- */
    .av-tap {
        position: absolute; z-index: 3; right: -10px; top: 96px;
        width: 46px; height: 46px; border-radius: 50%;
        display: grid; place-items: center; color: #fff; font-size: 15px;
        background: linear-gradient(140deg, var(--g1, #3d6bff), var(--g2, #7c5cff));
        box-shadow: 0 0 0 6px color-mix(in srgb, var(--g1, #3d6bff) 22%, transparent);
    }
    .av-save {
        display: flex; align-items: center; justify-content: center; gap: 7px;
        margin-top: 10px; padding: 9px 12px; border-radius: 11px;
        font-size: 11.5px; font-weight: 700; color: #fff;
        background: linear-gradient(135deg, var(--g1, #3d6bff), var(--g2, #7c5cff));
        position: relative;
    }
</style>
