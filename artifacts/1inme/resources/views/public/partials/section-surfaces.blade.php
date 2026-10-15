{{-- How a band separates from the one above it, and how a surface that
     stays dark keeps its ink when the page turns white.

     This was written for the homepage and lived in that file's <style>. Every
     marketing page has the same two problems -- 42 pages, 200-odd bands, none
     of them declaring a separator, and dark product mocks losing their text in
     light mode on most of them -- so it is one partial, included by
     home.blade.php and by public/layouts/site.blade.php, rather than a rule
     set that only one page in the site obeys.

     Deliberately written to be order-independent, because it is now included
     at a different point on two different pages:

       - .sec-ground paints through :where(), which weighs nothing, so any
         band's own background rule beats it whatever the source order;
       - the light-mode ink rule outranks the two sheets it has to beat
         (marketing-anim.css and public/partials/surfaces.blade.php) on
         specificity rather than on position;
       - .card-lit-cta and .surface-lit-keep escape that rule through a
         :not() rather than by repainting after it.

     Nothing here depends on being read last. --}}
@once
<link rel="stylesheet" href="{{ asset('css/marketing/marketing-sections-46162a96ca4f.css') }}">
@endonce
