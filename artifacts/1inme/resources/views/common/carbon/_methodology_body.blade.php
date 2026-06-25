@php use App\Modules\Common\Services\Carbon\CarbonEmissionsModel; @endphp
<h1>How we estimate (and offset) the carbon footprint of a Link in Bio</h1>
<p class="text-gray-500">Model version: <code>{{ CarbonEmissionsModel::MODEL_VERSION }}</code></p>

<h2>The model in one line</h2>
<pre class="bg-white border rounded p-3 text-xs overflow-x-auto">grams CO₂ = page_views × bytes_per_view × kWh/byte × device_factor × grid_intensity_g_per_kWh</pre>

<h2>Inputs</h2>
<ul>
    <li><strong>page_views</strong> — distinct PageSession rows for the link in the period. Bots/crawlers are already excluded upstream.</li>
    <li><strong>bytes_per_view</strong> — tier table keyed by active Link in Bio block count
        ({{ count(CarbonEmissionsModel::COMPLEXITY_TIERS) }} tiers; sparse landing → media-rich).
        We don't fetch the live page during snapshotting to keep the job network-free and reproducible.</li>
    <li><strong>kWh/byte</strong> — Sustainable Web Design v4 published transmission constant
        (<code>0.81 kWh/GB</code>).</li>
    <li><strong>device_factor</strong> — weighted average of mobile / tablet / desktop shares.
        Mobile devices on cellular are more efficient; desktop is the baseline.</li>
    <li><strong>grid_intensity_g_per_kWh</strong> — country-weighted average of the grid carbon intensity
        (Ember/IEA 2024). Visitors from a country we don't track fall back to the global average
        ({{ \App\Modules\Common\Services\Carbon\GridIntensityTable::GLOBAL_AVG_G_PER_KWH }} g/kWh).</li>
</ul>

<h2>What's in scope</h2>
<ul>
    <li>Network transfer + end-user device energy for visits to opted-in Link in Bio pages.</li>
</ul>

<h2>What's NOT in scope</h2>
<ul>
    <li>Embedded video provider footprint (YouTube/Vimeo handle their own).</li>
    <li>Email send footprint (digest, newsletter).</li>
    <li>Sayzio's own platform footprint — this is creator-funded only.</li>
</ul>

<h2>Auto-offset</h2>
<p>Once a month we estimate the prior month's footprint, then (for opted-in Link in Bio pages)
purchase offsets via your connected provider (Cloverly / Patch / sandbox). Purchases
are billed to the workspace via a draft invoice line item.</p>

<h3>Budget cap behaviour</h3>
<ul>
    <li><strong>Pause</strong> — if the estimated cost exceeds the cap, no offset is purchased; status is recorded as <code>capped</code>.</li>
    <li><strong>Partial</strong> — buy as many grams as fit the cap exactly; remainder rolls forward to the next snapshot.</li>
</ul>

<h2>QA &amp; recalibration</h2>
<p>The bytes-per-view tier table should be recalibrated quarterly against
real measured page weights from a sample of representative Link in Bio pages. The
recalibration script lives in <code>scripts/</code> (out of scope for v1
of this feature) and writes its findings into a new model version
without rewriting historical snapshots.</p>
