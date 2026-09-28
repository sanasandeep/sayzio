{{--
    What a block needs in order to look like itself.

    Goes in the <head> of any public page that includes
    common/partials/biolink-block-list. scripts/check-block-assets.php fails
    the build if one includes the list and not this, because the failure it
    guards against is silent: the blocks render, they just render as raw
    HTML, and only a visitor notices.

    Every block in common/blocks/* is written in Tailwind utility classes
    and FontAwesome icon classes. A Link in Bio has always had both in its
    head. The menus never did, so from the day they could render blocks
    until 2026-09-27 every block on a menu page came out unstyled -- a
    Heading at the browser's default h2, a full-width button shrunk to the
    width of its label, blank squares where icons go.

    A page that already loads these in its own head (common/biolink) must
    not include this partial as well; the guard treats @vite in the head as
    satisfying the requirement, so it stays out of the double-load.
--}}
@vite(['resources/css/app.css'])
@include('common.partials.fontawesome')

{{-- Tailwind's preflight sets line-height:inherit everywhere, which grows a
     menu page's own item rows about 8% taller than they have rendered since
     the page type existed. Those rows are not Tailwind's to reflow, so they
     restate the line-height they were built with. Scoped to .items so it
     touches nothing a block draws. --}}
<style>
    .items .item .name,
    .items .item .desc,
    .items .item .price { line-height: 1.3; }
</style>
