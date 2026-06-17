<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Support\AuthMethods;
use Illuminate\View\View;

/**
 * View composer that shares the login-method policy with the public
 * login/register modal. The modal is included via the marketing site
 * header/layout rather than the dedicated login controller, so without
 * this composer it has no access to the real settings and would render
 * the Mobile (WhatsApp) tab unconditionally — letting visitors pick it
 * and only get rejected by the controller after submitting.
 *
 * Mirrors the data the dedicated login page receives from AuthController.
 */
class AuthModalComposer
{
    /** View that should receive the auth-method policy. */
    public const VIEW = 'public.partials.auth-modal';

    public function compose(View $view): void
    {
        $view->with([
            'mobileLoginEnabled'  => AuthMethods::mobileLoginEnabled(),
            'allowedCountryCodes' => AuthMethods::allowedCountryCodes(),
        ]);
    }
}
