@extends('user.layouts.app')
@section('title', 'Service Booking - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .sb-grid { display:grid; grid-template-columns: minmax(0,1fr) 340px; gap:20px; align-items:start; }
    @media (max-width:1100px){ .sb-grid { grid-template-columns: minmax(0,1fr); } }
    .sb-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:20px; margin-bottom:16px; backdrop-filter:blur(20px); }
    .sb-card h5 { color:var(--text-primary); font-weight:700; margin:0 0 14px; font-size:15px; display:flex; justify-content:space-between; align-items:center; }
    .sb-label { display:block; font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:6px; }
    .sb-input, .sb-select, .sb-textarea { width:100%; border:1px solid var(--border-glass); border-radius:.75rem; background:var(--bg-glass-input); color:var(--text-primary); padding:10px 12px; font-size:14px; outline:none; }
    .sb-textarea { resize:vertical; min-height:60px; }
    .sb-row { margin-bottom:14px; }
    .sb-btn { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; border:0; border-radius:999px; font-weight:600; font-size:13.5px; color:#fff; cursor:pointer; background:linear-gradient(135deg,#5c83ff,#6366f1); }
    .sb-btn.sm { padding:6px 12px; font-size:12.5px; }
    .sb-btn.ghost { background:transparent; color:var(--text-muted); border:1px solid var(--border-glass); }
    .sb-btn.danger { background:transparent; color:#ef4444; border:1px solid rgba(239,68,68,.4); }
    .sb-item { display:flex; gap:12px; align-items:flex-start; padding:12px; border:1px solid var(--border-glass); border-radius:.85rem; margin-bottom:10px; background:var(--bg-glass-input); }
    .sb-item .meta { flex:1; min-width:0; }
    .sb-item .nm { font-weight:650; color:var(--text-primary); font-size:14.5px; }
    .sb-item .ds { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
    .sb-item .pr { font-size:13px; color:#5c83ff; font-weight:700; margin-top:4px; }
    .sb-cat { border:1px solid var(--border-glass); border-radius:1rem; padding:16px; margin-bottom:16px; }
    .sb-cat-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .sb-cat-head .ct { font-weight:700; color:var(--text-primary); font-size:16px; }
    .sb-pill { font-size:11px; padding:3px 9px; border-radius:999px; background:rgba(239,68,68,.15); color:#ef4444; font-weight:600; }
    .sb-table-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px dashed var(--border-glass); }
    .sb-mode-toggle { display:flex; gap:8px; }
    .sb-mode-toggle label { flex:1; text-align:center; padding:10px; border:1px solid var(--border-glass); border-radius:.75rem; cursor:pointer; font-size:13px; font-weight:600; color:var(--text-muted); }
    .sb-mode-toggle input { display:none; }
    .sb-mode-toggle input:checked + span { color:#5c83ff; }
    .sb-modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:60; padding:16px; }
    .sb-modal { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:22px; width:100%; max-width:480px; max-height:90vh; overflow:auto; }
    .sb-day-row { display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px dashed var(--border-glass); flex-wrap:wrap; }
    .sb-day-name { width:42px; font-weight:700; color:var(--text-primary); font-size:13px; }
    .sb-time { width:118px; }
</style>

<div class="max-w-7xl mx-auto" x-data="serviceBookingEditor()" x-init="init()">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary)">{{ $link->title ?: $link->alias }}</h1>
            <p class="text-sm" style="color:var(--text-muted)">Service Booking · /{{ $link->alias }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('user.links.service-booking.bookings', $link) }}" class="sb-btn ghost">
                <i class="fas fa-calendar-check"></i> Bookings @if($openBookings > 0)<span class="sb-pill">{{ $openBookings }}</span>@endif
            </a>
            <a href="{{ url('/'.$link->alias) }}" target="_blank" class="sb-btn ghost"><i class="fas fa-external-link-alt"></i> View</a>
        </div>
    </div>

    <div class="sb-grid">
        <div>
            <!-- Categories + services -->
            <div class="flex justify-between items-center mb-3">
                <h2 class="font-bold text-lg" style="color:var(--text-primary)">Services</h2>
                <div class="flex gap-2">
                    <button class="sb-btn sm ghost" @click="openCategory()"><i class="fas fa-plus"></i> Category</button>
                    <button class="sb-btn sm" @click="openService(null)"><i class="fas fa-plus"></i> Service</button>
                </div>
            </div>

            <template x-if="categories.length === 0 && services.length === 0">
                <div class="sb-card" style="text-align:center;color:var(--text-muted)">
                    No services yet. Add a category and your first service to get started.
                </div>
            </template>

            <template x-for="(cat, ci) in categories" :key="cat.id">
                <div class="sb-cat">
                    <div class="sb-cat-head">
                        <div class="ct" x-text="cat.name"></div>
                        <div class="flex gap-2">
                            <button class="sb-btn sm ghost" title="Move up" :disabled="ci===0" @click="moveCategory(cat,-1)"><i class="fas fa-arrow-up"></i></button>
                            <button class="sb-btn sm ghost" title="Move down" :disabled="ci===categories.length-1" @click="moveCategory(cat,1)"><i class="fas fa-arrow-down"></i></button>
                            <button class="sb-btn sm" @click="openService(cat.id)"><i class="fas fa-plus"></i> Service</button>
                            <button class="sb-btn sm ghost" @click="openCategory(cat)"><i class="fas fa-pen"></i></button>
                            <button class="sb-btn sm danger" @click="deleteCategory(cat)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <template x-for="(svc, ii) in servicesFor(cat.id)" :key="svc.id">
                        <div class="sb-item" :style="svc.is_unavailable ? 'opacity:.55' : ''">
                            <img x-show="svc.photo_url" :src="svc.photo_url" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;">
                            <div class="meta">
                                <div class="nm" x-text="svc.name"></div>
                                <div class="ds" x-show="svc.description" x-text="svc.description"></div>
                                <div class="pr"><span x-text="config.currency"></span> <span x-text="(+svc.price).toFixed(2)"></span>
                                    <span style="color:var(--text-muted);font-weight:500" x-text="' · ' + svc.duration_minutes + ' min'"></span>
                                    <span x-show="svc.is_unavailable" class="sb-pill" style="margin-left:6px">Unavailable</span></div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button class="sb-btn sm ghost" title="Move up" :disabled="ii===0" @click="moveService(cat.id,svc,-1)"><i class="fas fa-arrow-up"></i></button>
                                <button class="sb-btn sm ghost" title="Move down" :disabled="ii===servicesFor(cat.id).length-1" @click="moveService(cat.id,svc,1)"><i class="fas fa-arrow-down"></i></button>
                                <button class="sb-btn sm ghost" @click="openService(cat.id, svc)"><i class="fas fa-pen"></i></button>
                                <button class="sb-btn sm danger" @click="deleteService(svc)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </template>
                    <template x-if="servicesFor(cat.id).length === 0">
                        <p class="text-sm" style="color:var(--text-muted)">No services in this category yet.</p>
                    </template>
                </div>
            </template>

            <!-- Uncategorized services -->
            <template x-if="servicesFor(null).length > 0">
                <div class="sb-cat">
                    <div class="sb-cat-head"><div class="ct">Uncategorized</div></div>
                    <template x-for="(svc, ii) in servicesFor(null)" :key="svc.id">
                        <div class="sb-item" :style="svc.is_unavailable ? 'opacity:.55' : ''">
                            <img x-show="svc.photo_url" :src="svc.photo_url" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;">
                            <div class="meta">
                                <div class="nm" x-text="svc.name"></div>
                                <div class="ds" x-show="svc.description" x-text="svc.description"></div>
                                <div class="pr"><span x-text="config.currency"></span> <span x-text="(+svc.price).toFixed(2)"></span>
                                    <span style="color:var(--text-muted);font-weight:500" x-text="' · ' + svc.duration_minutes + ' min'"></span>
                                    <span x-show="svc.is_unavailable" class="sb-pill" style="margin-left:6px">Unavailable</span></div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button class="sb-btn sm ghost" @click="openService(null, svc)"><i class="fas fa-pen"></i></button>
                                <button class="sb-btn sm danger" @click="deleteService(svc)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Settings + availability -->
        <div>
            <div class="sb-card">
                <h5>Settings</h5>
                <div class="sb-row">
                    <label class="sb-label">Mode</label>
                    <div class="sb-mode-toggle">
                        <label><input type="radio" value="display" x-model="config.mode" @change="saveSettings()"><span>Display only</span></label>
                        <label><input type="radio" value="booking" x-model="config.mode" @change="saveSettings()"><span>Accept bookings</span></label>
                    </div>
                    <p class="text-xs mt-2" style="color:var(--text-muted)">Booking mode lets visitors pick a free slot and send a request. No online payment — you arrange payment directly.</p>
                </div>
                <div class="sb-row">
                    <label class="sb-label">Currency</label>
                    <input class="sb-input" x-model="config.currency" maxlength="3" @change="saveSettings()" style="text-transform:uppercase">
                </div>
                <div class="sb-row">
                    <label class="sb-label">Accent color</label>
                    <input type="color" class="sb-input" x-model="config.accent_color" @change="saveSettings()" style="height:42px;padding:4px">
                </div>
                <p class="text-xs" style="color:var(--text-faint)" x-text="savedMsg"></p>
            </div>

            <!-- Scheduling -->
            <div class="sb-card" x-show="config.mode === 'booking'">
                <h5>Scheduling</h5>
                <div class="sb-row">
                    <label class="sb-label">Slot length (minutes)</label>
                    <input class="sb-input" type="number" min="5" max="1440" step="5" x-model="config.slot_length_minutes" @change="saveSettings()">
                    <p class="text-xs mt-1" style="color:var(--text-muted)">How far apart bookable start times are.</p>
                </div>
                <div class="sb-row">
                    <label class="sb-label">Lead time (minutes)</label>
                    <input class="sb-input" type="number" min="0" max="43200" step="15" x-model="config.lead_time_minutes" @change="saveSettings()">
                    <p class="text-xs mt-1" style="color:var(--text-muted)">Minimum notice before a booking can start.</p>
                </div>
                <div class="sb-row">
                    <label class="sb-label">Max days ahead</label>
                    <input class="sb-input" type="number" min="1" max="365" x-model="config.max_days_ahead" @change="saveSettings()">
                </div>
                <div class="sb-row">
                    <label class="sb-label">Timezone</label>
                    <input class="sb-input" list="sb-tz-list" x-model="config.timezone" @change="saveSettings()" placeholder="e.g. America/New_York">
                    <datalist id="sb-tz-list">
                        @foreach(timezone_identifiers_list() as $tz)<option value="{{ $tz }}">@endforeach
                    </datalist>
                </div>
                <div class="sb-row" x-show="config.mode === 'booking'">
                    <label class="sb-label">WhatsApp number (optional)</label>
                    <input class="sb-input" type="text" x-model="config.whatsapp_number" @change="saveSettings()" maxlength="40" placeholder="e.g. +1 555 123 4567">
                    <p class="text-xs mt-1" style="color:var(--text-muted)">If set, visitors get a "Send my booking via WhatsApp" button on their booking status page with the details pre-filled.</p>
                </div>
            </div>

            <!-- GST / tax estimate -->
            <div class="sb-card" x-show="config.mode === 'booking'">
                <h5>Estimated tax (GST)</h5>
                <p class="text-xs mb-3" style="color:var(--text-muted)">Show an estimated GST/tax line on the visitor's estimate. This is an estimate only — no money is collected here.</p>
                <div class="sb-row">
                    <label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)">
                        <input type="checkbox" x-model="tax.enabled" @change="saveSettings()"> Add a tax line to the estimate
                    </label>
                </div>
                <template x-if="tax.enabled">
                    <div>
                        <div class="sb-row">
                            <label class="sb-label">Tax label</label>
                            <input class="sb-input" x-model="tax.label" maxlength="24" placeholder="GST" @change="saveSettings()">
                        </div>
                        <div class="sb-row">
                            <label class="sb-label">Rate (%)</label>
                            <input class="sb-input" type="number" step="0.001" min="0" max="100" x-model="tax.rate" @change="saveSettings()">
                        </div>
                        <div class="sb-row">
                            <label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)">
                                <input type="checkbox" x-model="tax.inclusive" @change="saveSettings()"> Prices already include tax
                            </label>
                            <p class="text-xs mt-1" style="color:var(--text-muted)" x-text="tax.inclusive ? 'Tax is shown as “incl.” and not added on top.' : 'Tax is added on top of the subtotal.'"></p>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Weekly availability -->
            <div class="sb-card" x-show="config.mode === 'booking'">
                <h5>Weekly hours</h5>
                <p class="text-xs mb-3" style="color:var(--text-muted)">Add the hours you accept bookings on each day. Slots are generated inside these windows.</p>
                <template x-for="d in days" :key="d.idx">
                    <div class="sb-day-row">
                        <span class="sb-day-name" x-text="d.short"></span>
                        <template x-for="r in rulesFor(d.idx)" :key="r.id">
                            <span style="display:inline-flex;align-items:center;gap:4px;background:var(--bg-glass-input);border:1px solid var(--border-glass);border-radius:999px;padding:3px 6px 3px 10px;font-size:12px;color:var(--text-primary)">
                                <span x-text="r.start_time + '–' + r.end_time"></span>
                                <button class="sb-btn sm danger" style="padding:2px 7px" @click="deleteRule(r)"><i class="fas fa-times"></i></button>
                            </span>
                        </template>
                        <button class="sb-btn sm ghost" @click="openRule(d.idx)"><i class="fas fa-plus"></i></button>
                    </div>
                </template>
            </div>

            <!-- Blocked dates -->
            <div class="sb-card" x-show="config.mode === 'booking'">
                <h5 style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                    <span>Blocked dates <button class="sb-btn sm" @click="openBlocked()"><i class="fas fa-plus"></i></button></span>
                </h5>
                <p class="text-xs mb-3" style="color:var(--text-muted)">Days off / holidays when no bookings are accepted.</p>
                <template x-for="b in blockedDates" :key="b.id">
                    <div class="sb-table-row">
                        <span style="color:var(--text-primary)">
                            <strong x-text="b.date"></strong>
                            <span style="color:var(--text-muted)" x-show="b.reason" x-text="' · ' + b.reason"></span>
                        </span>
                        <button class="sb-btn sm danger" @click="deleteBlocked(b)"><i class="fas fa-trash"></i></button>
                    </div>
                </template>
                <template x-if="blockedDates.length === 0">
                    <p class="text-sm" style="color:var(--text-muted)">No blocked dates.</p>
                </template>
            </div>
        </div>
    </div>

    <!-- Category modal -->
    <div class="sb-modal-bg" x-show="catModal.open" x-cloak @click.self="catModal.open=false">
        <div class="sb-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px" x-text="catModal.id ? 'Edit category' : 'New category'"></h5>
            <div class="sb-row"><label class="sb-label">Name</label><input class="sb-input" x-model="catModal.name"></div>
            <div class="sb-row"><label class="sb-label">Description</label><textarea class="sb-textarea" x-model="catModal.description"></textarea></div>
            <div class="flex justify-end gap-2">
                <button class="sb-btn ghost" @click="catModal.open=false">Cancel</button>
                <button class="sb-btn" @click="saveCategory()">Save</button>
            </div>
        </div>
    </div>

    <!-- Service modal -->
    <div class="sb-modal-bg" x-show="svcModal.open" x-cloak @click.self="svcModal.open=false">
        <div class="sb-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px" x-text="svcModal.id ? 'Edit service' : 'New service'"></h5>
            <div class="sb-row"><label class="sb-label">Name</label><input class="sb-input" x-model="svcModal.name"></div>
            <div class="sb-row"><label class="sb-label">Description</label><textarea class="sb-textarea" x-model="svcModal.description"></textarea></div>
            <div class="sb-row">
                <label class="sb-label">Category</label>
                <select class="sb-select" x-model="svcModal.category_id">
                    <option :value="null">Uncategorized</option>
                    <template x-for="c in categories" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                </select>
            </div>
            <div class="sb-row" style="display:flex;gap:10px">
                <div style="flex:1"><label class="sb-label">Estimated price</label><input class="sb-input" type="number" step="0.01" min="0" x-model="svcModal.price"></div>
                <div style="flex:1"><label class="sb-label">Duration (min)</label><input class="sb-input" type="number" min="5" max="1440" step="5" x-model="svcModal.duration_minutes"></div>
            </div>
            <div class="sb-row">
                <label class="sb-label">Photo</label>
                <input class="sb-input" x-model="svcModal.photo_url" placeholder="https://… or upload below">
                <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
                    <button type="button" class="sb-btn sm ghost" @click="$refs.photoInput.click()" :disabled="photoUploading">
                        <i class="fas" :class="photoUploading ? 'fa-spinner fa-spin' : 'fa-cloud-upload-alt'"></i>
                        <span x-text="photoUploading ? ('Uploading… ' + photoProgress + '%') : 'Upload photo'"></span>
                    </button>
                    <button type="button" class="sb-btn sm ghost" x-show="svcModal.photo_url" @click="svcModal.photo_url=''">Remove</button>
                    <input type="file" x-ref="photoInput" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="hidden" @change="uploadPhoto($event)">
                </div>
                <p class="text-xs" style="color:var(--accent-danger,#f87171)" x-show="photoError" x-text="photoError"></p>
                <template x-if="svcModal.photo_url">
                    <img :src="svcModal.photo_url" alt="Preview" style="margin-top:8px;max-height:120px;border-radius:10px;object-fit:contain" x-on:error="$el.style.display='none'" x-on:load="$el.style.display='block'">
                </template>
            </div>
            <div class="sb-row"><label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)"><input type="checkbox" x-model="svcModal.is_unavailable"> Unavailable (shown but not bookable)</label></div>
            <div class="flex justify-end gap-2">
                <button class="sb-btn ghost" @click="svcModal.open=false">Cancel</button>
                <button class="sb-btn" @click="saveService()">Save</button>
            </div>
        </div>
    </div>

    <!-- Availability rule modal -->
    <div class="sb-modal-bg" x-show="ruleModal.open" x-cloak @click.self="ruleModal.open=false">
        <div class="sb-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px">Add hours · <span x-text="dayName(ruleModal.day_of_week)"></span></h5>
            <div class="sb-row" style="display:flex;gap:10px">
                <div style="flex:1"><label class="sb-label">From</label><input class="sb-input" type="time" x-model="ruleModal.start_time"></div>
                <div style="flex:1"><label class="sb-label">To</label><input class="sb-input" type="time" x-model="ruleModal.end_time"></div>
            </div>
            <div class="flex justify-end gap-2">
                <button class="sb-btn ghost" @click="ruleModal.open=false">Cancel</button>
                <button class="sb-btn" @click="saveRule()">Add</button>
            </div>
        </div>
    </div>

    <!-- Blocked date modal -->
    <div class="sb-modal-bg" x-show="blockedModal.open" x-cloak @click.self="blockedModal.open=false">
        <div class="sb-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px">Block a date</h5>
            <div class="sb-row"><label class="sb-label">Date</label><input class="sb-input" type="date" x-model="blockedModal.date"></div>
            <div class="sb-row"><label class="sb-label">Reason (optional)</label><input class="sb-input" x-model="blockedModal.reason" maxlength="160" placeholder="Holiday"></div>
            <div class="flex justify-end gap-2">
                <button class="sb-btn ghost" @click="blockedModal.open=false">Cancel</button>
                <button class="sb-btn" @click="saveBlocked()">Block</button>
            </div>
        </div>
    </div>
</div>

<script>
@php
    $sbCategories = $config->categories->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'description'=>$c->description])->values();
    $sbServices = $config->services->map(fn($s)=>['id'=>$s->id,'category_id'=>$s->category_id,'name'=>$s->name,'description'=>$s->description,'price'=>$s->price,'duration_minutes'=>$s->duration_minutes,'photo_url'=>$s->photo_url,'is_unavailable'=>$s->is_unavailable])->values();
    $sbRules = $config->availabilityRules->map(fn($r)=>['id'=>$r->id,'day_of_week'=>$r->day_of_week,'start_time'=>substr((string)$r->start_time,0,5),'end_time'=>substr((string)$r->end_time,0,5)])->values();
    $sbBlocked = $config->blockedDates->map(fn($b)=>['id'=>$b->id,'date'=>$b->date?->format('Y-m-d'),'reason'=>$b->reason])->values();
    $sbConfigData = ['mode' => $config->mode, 'currency' => $config->currency, 'accent_color' => $config->accent_color, 'slot_length_minutes' => $config->slot_length_minutes, 'lead_time_minutes' => $config->lead_time_minutes, 'max_days_ahead' => $config->max_days_ahead, 'timezone' => $config->timezone, 'whatsapp_number' => $config->settings['whatsapp_number'] ?? ''];
    $sbTaxData = ['enabled' => $config->taxEnabled(), 'rate' => $config->taxRate(), 'inclusive' => $config->taxInclusive(), 'label' => $config->taxLabel()];
@endphp
function serviceBookingEditor() {
    return {
        config: @json($sbConfigData),
        tax: @json($sbTaxData),
        categories: @json($sbCategories),
        services: @json($sbServices),
        rules: @json($sbRules),
        blockedDates: @json($sbBlocked),
        days: [{idx:1,short:'Mon'},{idx:2,short:'Tue'},{idx:3,short:'Wed'},{idx:4,short:'Thu'},{idx:5,short:'Fri'},{idx:6,short:'Sat'},{idx:0,short:'Sun'}],
        savedMsg: '',
        catModal: { open:false, id:null, name:'', description:'' },
        svcModal: { open:false, id:null, category_id:null, name:'', description:'', price:'', duration_minutes:30, photo_url:'', is_unavailable:false },
        ruleModal: { open:false, day_of_week:1, start_time:'09:00', end_time:'17:00' },
        blockedModal: { open:false, date:'', reason:'' },
        base: @json(rtrim(url('/user/links/'.$link->id.'/service-booking'), '/')),
        uploadUrl: @json(route('user.files.upload')),
        csrf: @json(csrf_token()),
        photoUploading: false,
        photoProgress: 0,
        photoError: '',
        init(){},
        dayName(i){ return {0:'Sunday',1:'Monday',2:'Tuesday',3:'Wednesday',4:'Thursday',5:'Friday',6:'Saturday'}[i] || ''; },
        servicesFor(catId){ return this.services.filter(s => (s.category_id||null) === (catId||null)); },
        rulesFor(dow){ return this.rules.filter(r => r.day_of_week === dow).sort((a,b)=>a.start_time.localeCompare(b.start_time)); },
        uploadPhoto(e){
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file) return;
            this.photoUploading = true; this.photoProgress = 0; this.photoError = '';
            const fd = new FormData(); fd.append('file', file);
            const xhr = new XMLHttpRequest(); const self = this;
            xhr.upload.addEventListener('progress', function(ev){ if (ev.lengthComputable) self.photoProgress = Math.round((ev.loaded/ev.total)*100); });
            xhr.addEventListener('load', function(){
                self.photoUploading = false;
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && data.success && data.file) { self.svcModal.photo_url = data.file.url; }
                    else { self.photoError = (data.error && data.error.message) || (typeof data.error === 'string' ? data.error : '') || data.message || ('Upload failed (' + xhr.status + ')'); }
                } catch (err) { self.photoError = 'Upload failed (' + xhr.status + ')'; }
            });
            xhr.addEventListener('error', function(){ self.photoUploading = false; self.photoError = 'Network error'; });
            xhr.open('POST', this.uploadUrl);
            xhr.setRequestHeader('X-CSRF-TOKEN', this.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(fd);
        },
        async api(method, path, body){
            const r = await fetch(this.base + path, {
                method, headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'},
                body: body ? JSON.stringify(body) : undefined
            });
            const j = await r.json().catch(()=>({}));
            if (!r.ok) { alert((j.error && j.error.message) || (j.message) || 'Request failed'); throw new Error('fail'); }
            return j.data;
        },
        async saveSettings(){
            await this.api('POST','/settings',{
                mode:this.config.mode,
                currency:(this.config.currency||'USD').toUpperCase(),
                accent_color:this.config.accent_color,
                slot_length_minutes:parseInt(this.config.slot_length_minutes||30),
                lead_time_minutes:parseInt(this.config.lead_time_minutes||0),
                max_days_ahead:parseInt(this.config.max_days_ahead||30),
                timezone:this.config.timezone||'',
                whatsapp_number:(this.config.whatsapp_number||'').trim(),
                tax_enabled:!!this.tax.enabled,
                tax_rate:parseFloat(this.tax.rate||0),
                tax_inclusive:!!this.tax.inclusive,
                tax_label:this.tax.label||'GST',
            });
            this.savedMsg = 'Saved ✓'; setTimeout(()=>this.savedMsg='', 1500);
        },
        openCategory(cat){ this.catModal = cat ? {open:true,id:cat.id,name:cat.name,description:cat.description||''} : {open:true,id:null,name:'',description:''}; },
        async saveCategory(){
            if (!this.catModal.name.trim()) return;
            const payload = { name:this.catModal.name, description:this.catModal.description };
            if (this.catModal.id) { const d = await this.api('PUT','/categories/'+this.catModal.id, payload); const i=this.categories.findIndex(c=>c.id===this.catModal.id); this.categories[i]=d.category; }
            else { const d = await this.api('POST','/categories', payload); this.categories.push(d.category); }
            this.catModal.open = false;
        },
        async deleteCategory(cat){ if(!confirm('Delete "'+cat.name+'"? Its services become uncategorized.')) return; await this.api('DELETE','/categories/'+cat.id); this.categories=this.categories.filter(c=>c.id!==cat.id); this.services=this.services.map(s=>s.category_id===cat.id?{...s,category_id:null}:s); },
        async moveCategory(cat, dir){
            const idx = this.categories.findIndex(c=>c.id===cat.id);
            const to = idx + dir;
            if (idx<0 || to<0 || to>=this.categories.length) return;
            const arr = this.categories.slice();
            arr.splice(to, 0, arr.splice(idx, 1)[0]);
            this.categories = arr;
            await this.api('POST','/categories/reorder', { order: arr.map(c=>c.id) });
        },
        openService(catId, svc){ this.svcModal = svc
            ? {open:true,id:svc.id,category_id:svc.category_id,name:svc.name,description:svc.description||'',price:svc.price,duration_minutes:svc.duration_minutes,photo_url:svc.photo_url||'',is_unavailable:!!svc.is_unavailable}
            : {open:true,id:null,category_id:catId||null,name:'',description:'',price:'',duration_minutes:30,photo_url:'',is_unavailable:false}; },
        async saveService(){
            if (!this.svcModal.name.trim()) return;
            const payload = { category_id:this.svcModal.category_id||null, name:this.svcModal.name, description:this.svcModal.description, price:parseFloat(this.svcModal.price||0), duration_minutes:parseInt(this.svcModal.duration_minutes||30), photo_url:this.svcModal.photo_url||null, is_unavailable:this.svcModal.is_unavailable };
            if (this.svcModal.id) { const d = await this.api('PUT','/services/'+this.svcModal.id, payload); const i=this.services.findIndex(x=>x.id===this.svcModal.id); this.services[i]=d.service; }
            else { const d = await this.api('POST','/services', payload); this.services.push(d.service); }
            this.svcModal.open = false;
        },
        async deleteService(svc){ if(!confirm('Delete "'+svc.name+'"?')) return; await this.api('DELETE','/services/'+svc.id); this.services=this.services.filter(s=>s.id!==svc.id); },
        async moveService(catId, svc, dir){
            const list = this.servicesFor(catId);
            const idx = list.findIndex(s=>s.id===svc.id);
            const to = idx + dir;
            if (idx<0 || to<0 || to>=list.length) return;
            list.splice(to, 0, list.splice(idx, 1)[0]);
            const ids = list.map(s=>s.id);
            const others = this.services.filter(s=>(s.category_id||null)!==(catId||null));
            this.services = others.concat(list);
            await this.api('POST','/services/reorder', { order: ids });
        },
        openRule(dow){ this.ruleModal = {open:true, day_of_week:dow, start_time:'09:00', end_time:'17:00'}; },
        async saveRule(){
            const payload = { day_of_week:this.ruleModal.day_of_week, start_time:this.ruleModal.start_time, end_time:this.ruleModal.end_time };
            const d = await this.api('POST','/availability', payload);
            this.rules.push({id:d.rule.id, day_of_week:d.rule.day_of_week, start_time:String(d.rule.start_time).slice(0,5), end_time:String(d.rule.end_time).slice(0,5)});
            this.ruleModal.open = false;
        },
        async deleteRule(r){ await this.api('DELETE','/availability/'+r.id); this.rules=this.rules.filter(x=>x.id!==r.id); },
        openBlocked(){ this.blockedModal = {open:true, date:'', reason:''}; },
        async saveBlocked(){
            if (!this.blockedModal.date) return;
            const d = await this.api('POST','/blocked-dates', { date:this.blockedModal.date, reason:this.blockedModal.reason });
            this.blockedDates.push({id:d.blocked_date.id, date:String(d.blocked_date.date).slice(0,10), reason:d.blocked_date.reason});
            this.blockedModal.open = false;
        },
        async deleteBlocked(b){ await this.api('DELETE','/blocked-dates/'+b.id); this.blockedDates=this.blockedDates.filter(x=>x.id!==b.id); },
    };
}
</script>
@endsection
