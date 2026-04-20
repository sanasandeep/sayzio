<?php

use App\Providers\AppServiceProvider;
use App\Providers\RazorpayServiceProvider;
use App\Providers\StripeServiceProvider;
use App\Modules\Common\Providers\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    RazorpayServiceProvider::class,
    StripeServiceProvider::class,
    ModuleServiceProvider::class,
];
