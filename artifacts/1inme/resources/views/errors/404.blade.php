@php
    $page = \App\Modules\Common\Models\SitePage::resolveErrorPage('error-404');
@endphp
@include('errors._site-error', ['page' => $page, 'statusCode' => 404])
