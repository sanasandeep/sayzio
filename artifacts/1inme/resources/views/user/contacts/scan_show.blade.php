@extends('user.layouts.app')

@section('title', 'Review scan')

@php
    $confidence = $extracted['confidence']['overall'] ?? 0;
    $confPct = (int) round($confidence * 100);
    $confColor = $confPct >= 75 ? '#10b981' : ($confPct >= 50 ? '#f59e0b' : '#ef4444');
    $socials = $extracted['socials'] ?? [];
    $branding = $extracted['branding'] ?? [];
    $primaryHex = $branding['primary_color_hex'] ?? null;
    $secondaryHex = $branding['secondary_color_hex'] ?? null;
    $hasColors = (bool) ($primaryHex || $secondaryHex);
    $products = array_values(array_filter((array) ($extracted['products'] ?? []), 'is_array'));
@endphp

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Review the scan',
        'subtitle' => 'Edit anything that looks off, then save it as a contact, seed a biolink draft, or both.',
        'icon' => 'fa-magnifying-glass',
        'chips' => [
            ['icon' => 'fa-bolt text-pink-400', 'text' => $scan->credits_spent . ' credits used'],
            ['icon' => 'fa-percent', 'text' => $confPct . '% confidence', 'style' => 'color:' . $confColor],
        ],
    ])

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @if($scan->status !== 'completed')
        <div class="card-premium p-6 text-center">
            <p class="text-sm" style="color: var(--text-muted);">
                @if($scan->status === 'failed')
                    <i class="fas fa-times-circle text-red-400 mr-1"></i>
                    Scan failed: {{ $scan->error ?: 'unknown error' }}
                @else
                    <i class="fas fa-spinner fa-spin mr-1"></i> Processing — refresh in a moment.
                @endif
            </p>
            <a href="{{ route('user.contacts.scan.create') }}" class="inline-block mt-4 text-xs font-semibold" style="color:#a78bfa;">
                <i class="fas fa-arrow-left mr-1"></i> Try another upload
            </a>
        </div>
    @else
    @if(count($duplicates))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.20); color: var(--text-primary);">
        <p class="font-semibold mb-1" style="color: #f59e0b;">
            <i class="fas fa-triangle-exclamation mr-1"></i> Possible duplicate{{ count($duplicates) === 1 ? '' : 's' }}
        </p>
        <ul class="text-xs space-y-1" style="color: var(--text-muted);">
            @foreach($duplicates as $d)
            <li>
                Existing contact{{ count($d['contacts']) === 1 ? '' : 's' }} share this {{ $d['type'] }}: <strong>{{ $d['value'] }}</strong> —
                @foreach($d['contacts'] as $c)
                    <a class="underline" href="{{ route('user.contacts.show', $c['id']) }}">{{ $c['name'] }}</a>{{ !$loop->last ? ',' : '' }}
                @endforeach
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('user.contacts.scan.save', $scan) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf

        <div class="lg:col-span-1">
            @if(!empty($extracted['logo_url']))
            <div class="card-premium p-4 mb-4">
                <h4 class="text-xs font-bold uppercase tracking-wide mb-3" style="color: var(--text-muted);">
                    <i class="fas fa-sparkles text-fuchsia-400 mr-1"></i> Detected logo
                </h4>
                <div class="flex items-center justify-center p-4 rounded-lg" style="background: rgba(255,255,255,.04);">
                    <img src="{{ $extracted['logo_url'] }}" alt="Detected logo" style="max-height: 140px; max-width: 100%; object-fit: contain;">
                </div>
                <p class="mt-2 text-[11px]" style="color: var(--text-faint);">Saved to your vault — used as the avatar when seeding a biolink page.</p>
            </div>
            @endif

            @php $sources = $scan->sourceFiles(); @endphp
            <div class="card-premium p-4">
                <h4 class="text-xs font-bold uppercase tracking-wide mb-3" style="color: var(--text-muted);">
                    Original upload{{ count($sources) === 1 ? '' : 's' }}
                </h4>
                <div class="space-y-3">
                @foreach($sources as $sf)
                    @if(str_starts_with((string) $sf->mime_type, 'image/'))
                        <img src="{{ $sf->url }}" alt="" class="rounded-lg w-full" style="max-height: 240px; object-fit: contain; background: rgba(0,0,0,.2);">
                    @else
                        <a href="{{ $sf->url }}" target="_blank" class="block text-center p-4 rounded-lg" style="background: rgba(255,255,255,.04); border:1px dashed rgba(255,255,255,.10); color: var(--text-muted);">
                            <i class="fas fa-file-pdf text-2xl mb-1 text-red-400 block"></i>
                            <span class="text-xs">Open PDF</span>
                        </a>
                    @endif
                @endforeach
                </div>

                <div class="mt-4 text-xs" style="color: var(--text-muted);">
                    <div class="flex items-center justify-between py-1.5 border-b" style="border-color:rgba(255,255,255,.06);">
                        <span>Overall</span>
                        <span class="font-mono" style="color:{{ $confColor }};">{{ $confPct }}%</span>
                    </div>
                    @foreach(['name','email','phone','company'] as $k)
                    @php $v = (int) round(($extracted['confidence'][$k] ?? 0) * 100); @endphp
                    <div class="flex items-center justify-between py-1">
                        <span class="capitalize">{{ $k }}</span>
                        <span class="font-mono opacity-80">{{ $v }}%</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-5">
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">Person & company</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @php
                        $field = function($name, $label, $value, $type='text') {
                            $val = e((string) ($value ?? ''));
                            return <<<HTML
                                <label class="block">
                                    <span class="block text-[11px] font-semibold mb-1" style="color: var(--text-muted);">{$label}</span>
                                    <input type="{$type}" name="{$name}" value="{$val}"
                                        class="w-full text-sm rounded-lg px-3 py-2"
                                        style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                                </label>
                            HTML;
                        };
                    @endphp
                    {!! $field('full_name', 'Full name', $extracted['full_name'] ?? '') !!}
                    {!! $field('title', 'Title / role', $extracted['title'] ?? '') !!}
                    {!! $field('first_name', 'First name', $extracted['first_name'] ?? '') !!}
                    {!! $field('last_name', 'Last name', $extracted['last_name'] ?? '') !!}
                    {!! $field('company', 'Company', $extracted['company'] ?? '') !!}
                    {!! $field('website', 'Website', $extracted['website'] ?? '') !!}
                </div>
                <label class="block mt-3">
                    <span class="block text-[11px] font-semibold mb-1" style="color: var(--text-muted);">Tagline</span>
                    <input type="text" name="tagline" value="{{ $extracted['tagline'] ?? '' }}" class="w-full text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                </label>
                <label class="block mt-3">
                    <span class="block text-[11px] font-semibold mb-1" style="color: var(--text-muted);">Description / brochure copy</span>
                    <textarea name="description" rows="3" class="w-full text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">{{ $extracted['description'] ?? '' }}</textarea>
                </label>
                <label class="block mt-3">
                    <span class="block text-[11px] font-semibold mb-1" style="color: var(--text-muted);">Address</span>
                    <input type="text" name="address" value="{{ $extracted['address'] ?? '' }}" class="w-full text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                </label>
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">
                    Phone numbers
                </h3>
                @php $phones = $extracted['phones'] ?: [['value'=>'','label'=>'']]; @endphp
                <div class="space-y-2">
                    @foreach($phones as $i => $p)
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" name="phones[{{ $i }}][label]" value="{{ $p['label'] ?? '' }}" placeholder="Label (Mobile, Work…)" class="text-xs rounded-lg px-2 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                        <input type="text" name="phones[{{ $i }}][value]" value="{{ $p['value'] ?? '' }}" placeholder="+44 20 7946 0958" class="col-span-2 text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">
                    Email addresses
                </h3>
                @php $emails = $extracted['emails'] ?: [['value'=>'','label'=>'']]; @endphp
                <div class="space-y-2">
                    @foreach($emails as $i => $em)
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" name="emails[{{ $i }}][label]" value="{{ $em['label'] ?? '' }}" placeholder="Label (Work, Personal…)" class="text-xs rounded-lg px-2 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                        <input type="email" name="emails[{{ $i }}][value]" value="{{ $em['value'] ?? '' }}" placeholder="name@example.com" class="col-span-2 text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Social handles</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach(['instagram'=>'Instagram','tiktok'=>'TikTok','youtube'=>'YouTube','twitter'=>'X / Twitter','linkedin'=>'LinkedIn','facebook'=>'Facebook'] as $k => $label)
                    <label class="block">
                        <span class="block text-[11px] font-semibold mb-1" style="color: var(--text-muted);">{{ $label }}</span>
                        <input type="text" name="socials[{{ $k }}]" value="{{ $socials[$k] ?? '' }}" placeholder="username" class="w-full text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="card-premium p-5">
                <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">
                        <i class="fas fa-palette text-fuchsia-400 mr-1"></i> Brand colors
                    </h3>
                    <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-muted);">
                        <input type="checkbox" name="use_brand_colors" value="1" {{ $hasColors ? 'checked' : '' }}>
                        Theme the biolink with these
                    </label>
                </div>
                @if(!$hasColors)
                    <p class="text-xs mb-3" style="color: var(--text-faint);">
                        We didn't detect any brand colors — pick your own below to theme the page.
                    </p>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $colorField = function($name, $label, $value) {
                            $val = e($value ?: '#7C3AED');
                            return <<<HTML
                                <label class="block">
                                    <span class="block text-[11px] font-semibold mb-1" style="color: var(--text-muted);">{$label}</span>
                                    <div class="flex items-center gap-2">
                                        <input type="color" value="{$val}"
                                            oninput="this.nextElementSibling.value = this.value.toUpperCase()"
                                            class="h-9 w-12 rounded-lg cursor-pointer" style="background:transparent;border:1px solid rgba(255,255,255,.10);">
                                        <input type="text" name="{$name}" value="{$val}" maxlength="7" placeholder="#RRGGBB"
                                            oninput="const v=this.value; if(/^#[0-9A-Fa-f]{6}$/.test(v)) this.previousElementSibling.value=v;"
                                            class="w-full text-sm rounded-lg px-3 py-2 font-mono uppercase"
                                            style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                                    </div>
                                </label>
                            HTML;
                        };
                    @endphp
                    {!! $colorField('brand_color_primary', 'Primary', $primaryHex) !!}
                    {!! $colorField('brand_color_secondary', 'Secondary', $secondaryHex) !!}
                </div>
                <p class="mt-3 text-[11px]" style="color: var(--text-faint);">The primary color becomes the biolink theme color when you seed a page draft.</p>
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">
                    <i class="fas fa-box-open text-fuchsia-400 mr-1"></i> Products
                </h3>
                @if(count($products))
                    <p class="text-xs mb-3" style="color: var(--text-muted);">Kept products seed a Product block each in the biolink draft. Clear a name to drop one.</p>
                    <div class="space-y-3">
                        @foreach($products as $i => $p)
                        <div class="rounded-lg p-3" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <input type="text" name="products[{{ $i }}][name]" value="{{ $p['name'] ?? '' }}" placeholder="Product name" class="sm:col-span-2 text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                                <input type="text" name="products[{{ $i }}][price]" value="{{ $p['price'] ?? '' }}" placeholder="Price (e.g. $29)" class="text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                            </div>
                            <input type="text" name="products[{{ $i }}][description]" value="{{ $p['description'] ?? '' }}" placeholder="Short description (optional)" class="mt-2 w-full text-sm rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs" style="color: var(--text-faint);">No products were detected on this scan. Brochures with a product list will surface them here.</p>
                @endif
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">What should we do with this?</h3>
                <label class="flex items-start gap-3 mb-3 cursor-pointer">
                    <input type="checkbox" name="create_contact" value="1" {{ $from === 'wizard' ? '' : 'checked' }} class="mt-1">
                    <span class="text-sm">
                        <span class="block font-semibold" style="color: var(--text-primary);">Save as a contact</span>
                        <span class="block text-xs" style="color: var(--text-muted);">Adds a new entry to your address book.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="create_biolink" value="1" {{ $from === 'wizard' ? 'checked' : '' }} class="mt-1">
                    <span class="text-sm">
                        <span class="block font-semibold" style="color: var(--text-primary);">Seed a biolink page draft</span>
                        <span class="block text-xs" style="color: var(--text-muted);">Pre-fills the biolink wizard so you can publish a page from this card.</span>
                    </span>
                </label>

                <div class="flex items-center justify-between mt-5 gap-3 flex-wrap">
                    <a href="{{ route('user.contacts.scan.create') }}" class="text-xs" style="color: var(--text-muted);">
                        <i class="fas fa-rotate-right mr-1"></i> Scan another
                    </a>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition" style="background: linear-gradient(135deg,#7c3aed,#ec4899);">
                        <i class="fas fa-floppy-disk mr-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
