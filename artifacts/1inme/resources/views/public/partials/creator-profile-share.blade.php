<div class="cp-share flex flex-wrap items-center gap-2 my-4" x-data="{copied:false}" aria-label="Share profile">
    <button type="button" class="cp-share-button" @click="if(navigator.share){navigator.share({title:@js($shareName),url:@js($shareUrl)}).catch(()=>{});}else if(navigator.clipboard){navigator.clipboard.writeText(@js($shareUrl)).then(()=>copied=true);}"><i class="fas fa-share-nodes" aria-hidden="true"></i> Share</button>
    <button type="button" class="cp-share-button" @click="navigator.clipboard?.writeText(@js($shareUrl)).then(()=>copied=true)">Copy profile link</button>
    <a class="cp-share-button" href="{{ route('creator-profile.vcard',$shareHandle) }}" download>Save contact</a>
    <a class="cp-share-button" href="{{ 'https://wa.me/?text='.rawurlencode($shareName.' '.$shareUrl) }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
    <a class="cp-share-button" href="{{ 'https://t.me/share/url?url='.rawurlencode($shareUrl).'&text='.rawurlencode($shareName) }}" target="_blank" rel="noopener noreferrer">Telegram</a>
    <a class="cp-share-button" href="{{ 'sms:?body='.rawurlencode($shareName.' '.$shareUrl) }}">Message</a>
    <details class="relative"><summary class="cp-share-button cursor-pointer">QR code</summary><div class="p-4 rounded-xl mt-2" style="background:white;border:1px solid #cbd5e1"><img src="{{ route('creator-profile.share-qr',$shareHandle) }}" alt="QR code for {{ $shareName }}" width="200" height="200" loading="lazy"><a href="{{ route('creator-profile.share-qr',$shareHandle) }}" download="profile-qr.svg" class="block text-sm mt-2" style="color:#1d4ed8">Download QR</a></div></details>
    <span x-show="copied" x-cloak role="status" class="text-xs">Profile link copied</span>
</div>
<style>.cp-share-button{display:inline-flex;min-height:40px;align-items:center;gap:6px;border:1px solid var(--border-soft,#cbd5e1);border-radius:10px;padding:9px 12px;font-size:12px;font-weight:600;text-decoration:none;color:inherit;background:var(--bg-card,transparent)}.cp-share-button:focus-visible{outline:2px solid #6385dc;outline-offset:3px}</style>
