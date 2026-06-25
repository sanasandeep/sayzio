@php
    $suggestions = [];
    try {
        $suggester = app(\App\Modules\Common\Services\PathSuggester::class);
        if ($suggester->isEnabled()) {
            $suggestions = $suggester->suggest(request()->path());
        }
    } catch (\Throwable $e) {
        // Suggestions are a nice-to-have — never let them break the 404 page.
        $suggestions = [];
    }
@endphp
@include('errors._render', ['statusCode' => 404, 'slug' => 'error-404', 'suggestions' => $suggestions])
