@php
    $page = \App\Modules\Common\Models\SitePage::resolveErrorPage('error-500');
@endphp
@include('errors._site-error', ['page' => $page, 'statusCode' => 500])
