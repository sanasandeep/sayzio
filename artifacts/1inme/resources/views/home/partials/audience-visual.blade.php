{{--
    The product mock shown beside each audience panel's copy.

    Sana asked for "realistic images" in these modals. There are no
    photographs to use and a stock one would be worse than nothing -- a person
    at a laptop tells a reader nothing about what Sayzio does. So each of these
    is a drawing of the actual product, in the shape the audience would meet
    it: a phone for the creator page, a printed pack and its dynamic QR for the
    business, a tap-to-share card for the networking one.

    Drawn in markup rather than shipped as images for the same reasons the rest
    of the page is: it stays sharp at any size, it follows the theme, and the
    copy inside it can be corrected without opening a design tool.

    The stylesheet for these mocks lives in `audience-visual-style.blade.php`,
    included once by the audience section itself.

    It cannot live here. This partial renders INSIDE a `<template>`, and a
    template's content is inert: its <style> never applies to the document.
    With `@once` it was worse than that -- the directive emitted the block for
    the first audience only, so it was sitting inside the Creators template,
    which meant the styles existed exactly while the Creators modal was open
    and the other two panels rendered as unstyled text.

    `$key` is one of: creators | business | networking.
--}}

@if($key === 'creators')
    <div class="av">
        <div class="av-stage">
            <span class="av-float av-float--tr"><i class="fas fa-arrow-trend-up"></i> 1,284 taps today</span>
            <span class="av-float av-float--bl"><i class="fas fa-heart"></i> 42 tips this week</span>
            <div class="av-phone">
                <div class="av-screen">
                    <span class="av-notch" aria-hidden="true"></span>
                    <div class="av-ava">MA</div>
                    <p class="av-name">Maya Anders</p>
                    <p class="av-handle">1in.me/maya</p>
                    <div class="av-rows">
                        <div class="av-row is-pay"><i class="fas fa-mug-hot"></i> Tip me <span class="av-row-n">&#8377;99+</span></div>
                        <div class="av-row"><i class="fas fa-bag-shopping"></i> Spring drop <span class="av-row-n">new</span></div>
                        <div class="av-row"><i class="fab fa-youtube"></i> Latest video <span class="av-row-n">312</span></div>
                        <div class="av-row"><i class="fas fa-comment-dots"></i> Message me</div>
                    </div>
                </div>
            </div>
        </div>
        <p class="av-cap">Everything behind one link</p>
    </div>

@elseif($key === 'business')
    <div class="av">
        <div class="av-pack">
            <div class="av-qr" aria-hidden="true">
                @php
                    // A fixed pattern, not a real encoding: it reads as a QR at
                    // this size, and a scannable one pointing anywhere real
                    // would be a link nobody maintains.
                    $__qr = '1101011 1001101 0110110 1011011 0110101 1001110 1101011';
                @endphp
                @foreach(str_split(str_replace(' ', '', $__qr)) as $__bit)
                    <span class="{{ $__bit === '1' ? '' : 'o' }}"></span>
                @endforeach
            </div>
            <p class="av-url">yourbrand.link/menu</p>
            <div class="av-swap">
                <div class="av-swap-row"><i class="fas fa-clock-rotate-left"></i> <s>Spring menu</s> <span class="av-when">was</span></div>
                <div class="av-swap-row is-now"><i class="fas fa-circle-check"></i> Autumn menu <span class="av-when">now</span></div>
            </div>
        </div>
        <p class="av-cap">Print once, repoint anytime</p>
    </div>

@else
    <div class="av">
        <div class="av-stage">
            <span class="av-tap" aria-hidden="true"><i class="fas fa-wifi"></i></span>
            <span class="av-float av-float--bl"><i class="fas fa-location-dot"></i> 18 taps &middot; Bengaluru</span>
            <div class="av-phone">
                <div class="av-screen">
                    <span class="av-notch" aria-hidden="true"></span>
                    <div class="av-ava">RS</div>
                    <p class="av-name">Rahul Shah</p>
                    <p class="av-handle">Head of Partnerships &middot; Sayzio</p>
                    <div class="av-rows">
                        <div class="av-row"><i class="fas fa-envelope"></i> rahul@yourbrand.com</div>
                        <div class="av-row"><i class="fas fa-phone"></i> +91 98xxx xxx41</div>
                        <div class="av-row"><i class="fab fa-linkedin"></i> /in/rahulshah</div>
                    </div>
                    <div class="av-save"><i class="fas fa-address-card"></i> Save contact</div>
                </div>
            </div>
        </div>
        <p class="av-cap">Tapped, saved, always current</p>
    </div>
@endif
