<details class="bk-disclosure mt-5"><summary>{{ !empty($campaign)?'Brand, knowledge & destination':'Knowledge & destination' }}</summary>
<p class="bk-muted my-3">Brand Kit sets the style. AI Minds supply reference facts. Folder/project groups the created materials.</p>
<div class="bk-form-grid">@if(!empty($campaign))<label class="bk-field"><span class="bk-label">Brand Kit</span><select class="bk-input" x-model="brandKitId"><option value="">No Brand Kit for this run</option>@foreach($brandKits as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach</select></label>@endif
<label class="bk-field"><span class="bk-label">Save to folder / project</span><select class="bk-input" x-model="projectId"><option value="">{{ !empty($campaign)?'Use connected Planner destination, if any':'Choose later' }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></label></div>
<p class="bk-label">Additional AI Minds · optional</p>@forelse($minds as $mind)<label class="bk-check"><input type="checkbox" x-model="mindIds" value="{{ $mind->id }}">{{ $mind->name }}</label>@empty<p class="bk-muted">No AI Minds available.</p>@endforelse
@if(!empty($campaign))<p class="bk-muted mt-3">The campaign’s saved AI Minds, persona and Agent are also used. Edit them in the campaign brief.</p>@endif
</details>
