{{-- Mounts ONLY the shared "coming soon" modal from store-buttons (its @once
     block, teleported to <body>) without showing the badge row. Used by pages
     that render their own CTA buttons but still dispatch the
     `open-store-coming-soon` window event when no store URL is configured.
     The wrapping div hides the duplicate badges; the modal itself teleports
     out of this subtree so it stays fully functional. --}}
<div class="hidden" aria-hidden="true">
    @include('public.partials.store-buttons')
</div>
