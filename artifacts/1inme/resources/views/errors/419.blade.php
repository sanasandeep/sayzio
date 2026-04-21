@php
    $page = \App\Modules\Common\Models\SitePage::resolveErrorPage('error-419');
@endphp
@include('errors._site-error', ['page' => $page, 'statusCode' => 419])
