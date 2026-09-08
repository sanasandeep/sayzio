{{-- Drawn stand-in for a My Links screenshot. Demo workspace, sample figures. --}}
@php
    $szRows = [
        ['This week&rsquo;s menu',   'oliveandember/menu',      'Menu',    '#E08A00', '1,046', '0,24 17,19 34,21 50,12 67,10 84,5 100,3'],
        ['Order a custom cake',      'oliveandember/cakes',     'Store',   '#E23D6E', '762',   '0,20 17,22 34,15 50,17 67,11 84,9 100,6'],
        ['Table 4 QR',               'oe/t4',                   'QR',      '#0EA5A5', '558',   '0,26 17,21 34,23 50,16 67,18 84,11 100,8'],
        ['Book a tasting',           'oliveandember/tasting',   'Booking', '#4F46E5', '369',   '0,22 17,18 34,20 50,19 67,13 84,14 100,9'],
        ['Wholesale enquiries',      'oliveandember/wholesale', 'Form',    '#9333EA', '224',   '0,19 17,21 34,17 50,18 67,15 84,12 100,11'],
    ];
@endphp
<div class="szmk" role="img" aria-label="The My Links page on a demo workspace, listing five links with their types, clicks and weekly trend">
  <div class="side">
    <div class="brand"><i></i>SAYZIO</div>
    <div class="ws"><u></u><span><b>Olive &amp; Ember</b><em>Demo workspace</em></span></div>
    <nav>
      <span><i></i>Dashboard</span><span class="on"><i></i>All links</span><span><i></i>Create link</span>
      <span><i></i>QR studio</span><span><i></i>Forms</span><span><i></i>Zio AI</span>
    </nav>
  </div>
  <div class="main">
    <div class="top"><span class="crumb">My Links</span><span class="search">Search links</span><span class="new">Create link</span></div>
    <div class="body">
      <div class="chips">
        <span><b>82</b> links</span><span><b>4</b> folders</span><span><b>3,527</b> clicks</span><span><b>1,284</b> scans</span><span>Export to CSV</span>
      </div>
      <div class="tbl">
        <div class="th"><span>Link</span><span>Type</span><span>Clicks</span><span>7 days</span><span>Status</span></div>
        @foreach ($szRows as [$szName, $szSlug, $szType, $szColour, $szClicks, $szPoints])
          <div class="tr">
            <div><b>{!! $szName !!}</b><em>sayzio.app/{{ $szSlug }}</em></div>
            <span class="tag2" style="background:{{ $szColour }}">{{ $szType }}</span>
            <span class="num">{{ $szClicks }}</span>
            <svg viewBox="0 0 100 30" preserveAspectRatio="none"><polyline fill="none" stroke="#7B8CFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" points="{{ $szPoints }}"/></svg>
            <span class="live"><i></i>Live</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
