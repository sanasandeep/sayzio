{{-- Schematic skin footer: hairline columns, no gradients, no cards. --}}
<footer class="sch-footer">
    <div class="sch-wrap">
        <div class="sch-foot-grid">
            <div>
                <a class="sch-brand" href="{{ route('site.schematic.home') }}"><b>SAYZIO</b><span>Est. 2023</span></a>
                <p style="font-size:13.5px;color:var(--ink-3);max-width:34ch;margin:12px 0 0">
                    One address for everything you make, sell, book and say.
                </p>
            </div>
            <div>
                <h4>Product</h4>
                <ul>
                    <li><a href="{{ route('site.schematic.features') }}">Link in Bio</a></li>
                    <li><a href="{{ route('site.schematic.features') }}">Short links</a></li>
                    <li><a href="{{ route('site.schematic.features') }}">QR codes</a></li>
                    <li><a href="{{ route('site.schematic.features') }}">Zio AI</a></li>
                </ul>
            </div>
            <div>
                <h4>Company</h4>
                <ul>
                    <li><a href="{{ route('site.schematic.about') }}">About</a></li>
                    <li><a href="{{ route('site.newsroom') }}">Newsroom</a></li>
                    <li><a href="{{ route('site.contact') }}">Contact</a></li>
                    <li><a href="{{ route('site.schematic.pricing') }}">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h4>Legal</h4>
                <ul>
                    <li><a href="{{ route('site.privacy') }}">Privacy</a></li>
                    <li><a href="{{ route('site.terms') }}">Terms</a></li>
                    <li><a href="{{ route('site.refunds') }}">Refunds</a></li>
                    <li><a href="{{ route('site.gdpr') }}">GDPR</a></li>
                </ul>
            </div>
        </div>
        <div class="sch-colophon">
            <span>&copy; {{ now()->year }} Sayzio</span>
            <span>Hyderabad &middot; India</span>
        </div>
    </div>
</footer>
