<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in with Apple - Sayzio</title><style>body{font:16px system-ui;background:#10121a;color:#eee;max-width:460px;margin:10vh auto;padding:24px}button,a{color:inherit}button{background:#fff;color:#111;border:0;border-radius:10px;padding:14px 24px;cursor:pointer}a{display:block;margin-top:20px}</style></head><body>
<div class="p-6 max-w-md mx-auto space-y-5"><h1 class="text-2xl font-bold">Sign in with Apple</h1><p>Use your Apple account to continue to Sayzio.</p><button id="apple-login" class="btn-primary" type="button">Continue with Apple</button><p id="apple-error" role="alert"></p><a href="{{ route('user.login') }}" class="underline">Use another sign-in method</a></div>
<script src="https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js"></script>
<script>
document.getElementById('apple-login').addEventListener('click',async function(){this.disabled=true;const error=document.getElementById('apple-error');error.textContent='';try{
AppleID.auth.init({clientId:@js(\App\Services\AppleSignIn::serviceId()),scope:'name email',redirectURI:@js(route('user.apple.login')),state:@js($nonce),nonce:@js($nonce),usePopup:true});
const result=await AppleID.auth.signIn();if(result.authorization.state!==@js($nonce))throw new Error('Sign-in state mismatch.');
const response=await fetch(@js(route('user.apple.complete')),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':@js(csrf_token())},body:JSON.stringify({id_token:result.authorization.id_token,nonce:@js($nonce),name:[result.user?.name?.firstName,result.user?.name?.lastName].filter(Boolean).join(' ')})});const data=await response.json();if(!response.ok)throw new Error(data.error?.message||data.message||'Sign-in could not finish. If you use two-factor authentication, continue through another sign-in method.');location.assign(data.redirect);
}catch(e){error.textContent=e.message||'Sign-in cancelled. Reload this page to try again.';}finally{this.disabled=false;}});
</script>
</body></html>
