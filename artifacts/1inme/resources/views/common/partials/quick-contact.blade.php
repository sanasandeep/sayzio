{{--
    Standalone multi-channel quick-contact widget.

    Anonymous-friendly counterpart to the (login-gated) Site Assistant: a
    floating launcher that lets any visitor request a Call back / WhatsApp
    call / Email. Submissions post to /assistant/quick-contact, which lands
    them in the admin Contact Inbox and emails the admin. Self-contained
    (own CSS namespace `qc-` + vanilla JS) so it can drop into any layout.
--}}
@php($qcUrl = url('/assistant/quick-contact'))
<div id="qc-widget"
     data-url="{{ $qcUrl }}"
     style="position:fixed;left:20px;bottom:20px;z-index:2147483000;font-family:inherit">
    <div id="qc-panel" class="qc-panel" style="display:none">
        <div class="qc-head">
            <div>
                <div class="qc-title">{{ __('Quick contact') }}</div>
                <div class="qc-sub">{{ __("We'll reach out your way.") }}</div>
            </div>
            <button type="button" class="qc-x" id="qc-close" aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div class="qc-body" id="qc-body"></div>
    </div>
    <button type="button" id="qc-launch" class="qc-launch" aria-label="{{ __('Quick contact') }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
    </button>
</div>

<style>
    #qc-widget *{box-sizing:border-box}
    .qc-launch{display:flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:50%;border:0;cursor:pointer;color:#fff;box-shadow:0 10px 30px rgba(61,107,255,.45);background:linear-gradient(135deg,#3d6bff,#a855f7)}
    .qc-panel{position:absolute;bottom:64px;left:0;width:320px;max-width:calc(100vw - 40px);border-radius:16px;overflow:hidden;background:rgba(15,14,26,.94);border:1px solid rgba(255,255,255,.08);box-shadow:0 20px 60px rgba(0,0,0,.4);backdrop-filter:blur(16px)}
    .qc-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.08)}
    .qc-title{font-size:14px;font-weight:700;color:#e9e7f5}
    .qc-sub{font-size:12px;color:rgba(233,231,245,.6)}
    .qc-x{border:0;background:transparent;cursor:pointer;color:rgba(233,231,245,.6);font-size:20px;line-height:1;padding:2px 6px}
    .qc-body{padding:12px}
    .qc-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
    .qc-tab{flex:1;min-width:80px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#cbd5e1;padding:7px 8px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer}
    .qc-tab.qc-on{background:#3d6bff;border-color:transparent;color:#fff}
    .qc-input,.qc-msg{width:100%;padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#e9e7f5;font-size:13px;font-family:inherit;outline:none;margin-bottom:8px}
    .qc-msg{resize:none}
    .qc-send{width:100%;padding:9px 12px;border-radius:10px;border:0;cursor:pointer;color:#fff;font-weight:600;font-size:13px;background:linear-gradient(135deg,#3d6bff,#a855f7)}
    .qc-send[disabled]{opacity:.6;cursor:not-allowed}
    .qc-err{margin-bottom:8px;font-size:12px;color:#ef4444}
    .qc-done{padding:16px 8px;text-align:center;font-size:13px;color:#e9e7f5;line-height:1.5}
    html.light-mode .qc-panel{background:rgba(255,255,255,.97);border:1px solid rgba(17,17,30,.08)}
    html.light-mode .qc-title{color:#1e1b2e}
    html.light-mode .qc-sub,html.light-mode .qc-x{color:rgba(30,27,46,.6)}
    html.light-mode .qc-tab{background:rgba(15,23,42,.05);border:1px solid rgba(15,23,42,.12);color:#475569}
    html.light-mode .qc-tab.qc-on{background:#3d6bff;color:#fff;border-color:transparent}
    html.light-mode .qc-input,html.light-mode .qc-msg{background:rgba(17,17,30,.03);border:1px solid rgba(17,17,30,.1);color:#1e1b2e}
    html.light-mode .qc-done{color:#1e1b2e}
</style>

<script>
(function(){
    var root=document.getElementById('qc-widget');
    if(!root) return;
    var url=root.getAttribute('data-url');
    var panel=document.getElementById('qc-panel');
    var bodyEl=document.getElementById('qc-body');
    var launch=document.getElementById('qc-launch');
    var closeBtn=document.getElementById('qc-close');
    var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
    var built=false, busy=false;

    var CHANNELS=[
        {value:'callback', label:@json(__('Call back')),     field:'phone', placeholder:@json(__('Your phone (+91, 10 digits)')), inputType:'tel'},
        {value:'whatsapp', label:@json(__('WhatsApp call')), field:'phone', placeholder:@json(__('WhatsApp number (with country code)')), inputType:'tel'},
        {value:'email',    label:@json(__('Email')),          field:'email', placeholder:@json(__('Your email')), inputType:'email'}
    ];
    var T={
        msg:@json(__('How can we help? (optional)')),
        send:@json(__('Send request')),
        sending:@json(__('Sending…')),
        ok:@json(__("Thanks! We've got your request and will be in touch soon.")),
        netErr:@json(__('Network error. Please try again.')),
        genErr:@json(__('Something went wrong. Please try again.'))
    };

    function el(tag,attrs,text){
        var n=document.createElement(tag);
        if(attrs) for(var k in attrs){ if(attrs.hasOwnProperty(k)) n.setAttribute(k, attrs[k]); }
        if(text!=null) n.textContent=text;
        return n;
    }

    function buildForm(){
        bodyEl.innerHTML='';
        var selected='callback';
        // Time-trap: stamp when the form opened so the server can reject a
        // submission filled+posted implausibly fast (a bot signal). A same-clock
        // delta, so it carries no wall-clock/timezone info.
        var openedAt=Date.now();
        var errBox=el('div',{class:'qc-err'}); errBox.style.display='none';
        var tabs=el('div',{class:'qc-tabs'});
        var contact=el('input',{type:'tel',class:'qc-input',placeholder:CHANNELS[0].placeholder});
        var msg=el('textarea',{class:'qc-msg',rows:'2',placeholder:T.msg});
        // Honeypot: an off-screen decoy a real visitor never sees or fills but
        // automated form-fillers tend to complete. A non-empty value makes the
        // server silently drop the submission. Hidden from sighted users and AT
        // (aria-hidden + tabindex -1) and excluded from autofill.
        var trap=el('input',{
            type:'text', name:'website', tabindex:'-1', autocomplete:'off',
            'aria-hidden':'true'
        });
        trap.style.cssText='position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;pointer-events:none';
        var send=el('button',{type:'button',class:'qc-send'}, T.send);

        function applyChannel(ch){
            selected=ch.value;
            contact.value='';
            contact.setAttribute('type', ch.inputType);
            contact.setAttribute('placeholder', ch.placeholder);
            contact.style.borderColor='';
            Array.prototype.forEach.call(tabs.children,function(b){
                b.classList.toggle('qc-on', b.getAttribute('data-ch')===ch.value);
            });
        }
        CHANNELS.forEach(function(ch){
            var b=el('button',{type:'button',class:'qc-tab','data-ch':ch.value}, ch.label);
            b.onclick=function(){ applyChannel(ch); };
            tabs.appendChild(b);
        });

        send.onclick=function(){
            if(busy) return;
            var val=(contact.value||'').trim();
            if(!val){ contact.style.borderColor='#ef4444'; return; }
            var payload={channel:selected, message:(msg.value||'').trim(), website:(trap.value||''), elapsed_ms:(Date.now()-openedAt)};
            if(selected==='email'){ payload.email=val; } else { payload.phone=val; }
            busy=true; send.disabled=true; send.textContent=T.sending; errBox.style.display='none';
            fetch(url,{
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body:JSON.stringify(payload)
            }).then(function(r){ return r.json().catch(function(){ return {}; }); })
              .then(function(d){
                  if(d && d.ok){
                      bodyEl.innerHTML='';
                      bodyEl.appendChild(el('div',{class:'qc-done'}, d.message || T.ok));
                  } else {
                      errBox.textContent=(d && d.error) || T.genErr; errBox.style.display='';
                  }
              }).catch(function(){ errBox.textContent=T.netErr; errBox.style.display=''; })
              .then(function(){ busy=false; send.disabled=false; send.textContent=T.send; });
        };

        bodyEl.appendChild(errBox);
        bodyEl.appendChild(tabs);
        bodyEl.appendChild(contact);
        bodyEl.appendChild(trap);
        bodyEl.appendChild(msg);
        bodyEl.appendChild(send);
        applyChannel(CHANNELS[0]);
    }

    function toggle(){
        var show=panel.style.display==='none';
        if(show && !built){ buildForm(); built=true; }
        panel.style.display=show?'block':'none';
    }
    launch.onclick=toggle;
    closeBtn.onclick=function(){ panel.style.display='none'; };
})();
</script>
