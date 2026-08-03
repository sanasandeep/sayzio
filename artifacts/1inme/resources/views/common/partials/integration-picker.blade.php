{{--
   Reusable integration-config picker. Renders a select bound to the user's
   saved configurations of a given kind. Falls back to a friendly "no configs"
   message linking to the integrations hub.

   Variables:
   - $name        : form field name (e.g. 'email_config_id')
   - $kind        : 'payment' | 'sms' | 'email'
   - $value       : currently selected ID (nullable)
   - $allowEmpty  : (bool) if true, render an empty "Use system default" option
   - $emptyLabel  : optional text for that option (default: "— Use account default —")
   - $providers   : optional whitelist of provider keys to show (otherwise all)
--}}
@php
    use App\Modules\User\Models\IntegrationConfig;
    use App\Modules\User\Support\IntegrationConfigRegistry;

    $allowEmpty = $allowEmpty ?? true;
    $emptyLabel = $emptyLabel ?? 'Use account default';
    $providers  = $providers ?? null;

    // Configs are account-level: list them regardless of the active workspace
    // (the workspace global scope would otherwise hide connections created
    // while a different workspace was active).
    $query = IntegrationConfig::withoutGlobalScope('workspace')
        ->where('user_id', auth()->id())->kind($kind)->active()
        ->orderByDesc('is_default')->orderBy('provider')->orderBy('name');
    if ($providers) $query->whereIn('provider', $providers);
    $options = $query->get();

    // Email configs have a first-class management page ("SMTP Connections",
    // Task #6632) — send users there instead of the generic create form so
    // they can also test, default, and reuse connections in one place.
    $createUrl = $kind === 'email'
        ? route('user.email-connections.index')
        : route('user.integrations.create', ['kind' => $kind]);
    $kindMeta  = IntegrationConfigRegistry::kinds()[$kind];
@endphp

@if($options->isEmpty())
    <div class="px-3 py-2.5 rounded-lg text-xs flex items-center gap-2"
         style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass); color: var(--text-muted);">
        <i class="fas fa-circle-info"></i>
        <span>No {{ strtolower($kindMeta['label']) }} configurations saved yet.</span>
        <a href="{{ $createUrl }}" target="_blank" class="ml-auto font-semibold underline" style="color: var(--accent);">
            Add one <i class="fas fa-external-link-alt text-[9px]"></i>
        </a>
        <input type="hidden" name="{{ $name }}" value="">
    </div>
@else
    <div class="flex items-center gap-2">
        <select name="{{ $name }}" class="theme-input flex-1">
            @if($allowEmpty)
                <option value="">{{ $emptyLabel }}</option>
            @endif
            @foreach($options as $opt)
                <option value="{{ $opt->id }}" @selected((string) $value === (string) $opt->id)>
                    {{ $opt->name }} ({{ $opt->providerLabel() }}){{ $opt->is_default ? ' · default' : '' }}
                </option>
            @endforeach
        </select>
        <a href="{{ $createUrl }}" target="_blank"
           class="px-3 py-2 rounded-lg text-xs font-semibold flex-shrink-0"
           style="background: var(--bg-glass-input); color: var(--text-primary);"
           title="{{ $kind === 'email' ? 'Manage your email connections' : 'Add another configuration' }}">
            <i class="fas fa-plus"></i>
        </a>
    </div>
@endif
