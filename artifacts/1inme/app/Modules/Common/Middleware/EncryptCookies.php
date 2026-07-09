<?php

namespace App\Modules\Common\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

/**
 * App-level cookie encrypter that leaves the audience-prompt
 * self-identification cookies (`ap_type_{link_id}`) unencrypted.
 *
 * These cookies are written client-side by the audience-prompt Alpine
 * component (plain `document.cookie`), so they can never carry Laravel's
 * encryption envelope. Without this exclusion the base EncryptCookies
 * middleware fails to decrypt them and replaces the value with null,
 * which silently breaks both the subscriber persona stamping in
 * RedirectController::subscribe() and the visitor-type display-rule
 * targeting in BiolinkBlock. The base class only supports exact-name
 * exclusions, so we override isDisabled() for the dynamic prefix.
 */
class EncryptCookies extends Middleware
{
    public function isDisabled($name)
    {
        if (str_starts_with((string) $name, 'ap_type_')) {
            return true;
        }

        return parent::isDisabled($name);
    }
}
