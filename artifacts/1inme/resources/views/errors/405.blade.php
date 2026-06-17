@php
    $page = \App\Modules\Common\Models\SitePage::resolveErrorPage('error-405');
@endphp
@include('errors._site-error', ['page' => $page, 'statusCode' => 405])
