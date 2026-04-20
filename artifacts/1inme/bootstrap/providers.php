<?php

use App\Providers\AppServiceProvider;
use App\Providers\RazorpayServiceProvider;
use App\Modules\Common\Providers\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    RazorpayServiceProvider::class,
    ModuleServiceProvider::class,
];
