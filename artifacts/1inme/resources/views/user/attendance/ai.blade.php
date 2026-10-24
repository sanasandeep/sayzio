<section class="card">
    <h2>AI people assistance</h2>
    <p>Connect live attendance and leave to AI Staff, AI Minds and Coach. Enable people assistance in Working hours &amp; policy, then select the People data connection on your AI Staff or Mind. Human permissions apply on every read and approval.</p>
    @if(!$isInternal && $policy['ai_people_enabled'])
        <a class="btn-ghost space" href="{{ route('user.ai.staff.index') }}">Manage AI Staff connections</a>
        @if(($canRequest || $canManage) && $peopleAi['staff']->isNotEmpty())
            <form method="POST" action="{{ route('user.attendance.ai-draft') }}" class="space">
                @csrf
                <div class="fields">
                    <label>AI Staff<select required name="ai_staff_id">
                        @foreach($peopleAi['staff'] as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select></label>
                    <label>Prepare<select name="draft_kind">
                        @if($canRequest)
                            <option value="leave">My leave request</option>
                        @endif
                        @if($canManage)
                            <option value="roster">Team roster</option>
                        @endif
                    </select></label>
                </div>
                <label>What would you like to prepare?<textarea name="prompt" required minlength="5" maxlength="4000" placeholder="Prepare annual leave for me from November 2 to November 3, 2026"></textarea></label>
                <button class="btn-primary space" type="submit">Prepare draft</button>
                <p class="muted">Uses your AI Staff model and budget, including its connected Minds. Nothing changes until you approve the prepared fields.</p>
            </form>
        @endif
        @if($peopleAi['staff']->isEmpty())
            <p class="note">Create an AI Staff member to prepare drafts. Connected Minds can supply its knowledge.</p>
        @endif
        @foreach($peopleAi['drafts'] as $draft)
            <article class="note">
                <h3>{{ ucfirst($draft->kind) }} draft · {{ ucfirst($draft->status) }}</h3>
                <dl>
                    @foreach(json_decode($draft->payload,true) as $key=>$value)
                        <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:12px;overflow-wrap:anywhere;padding:8px 0">
                            <dt>{{ ucfirst(str_replace('_',' ',$key)) }}</dt>
                            <dd>
                                @if($key === 'leave_type_id')
                                    {{ $operations['leave_types']->firstWhere('id',$value)?->name ?? 'Unavailable leave type' }}
                                @elseif($key === 'shift_id')
                                    {{ $operations['workforce']['shifts']->firstWhere('id',$value)?->name ?? 'No shift' }}
                                @elseif($key === 'staff_ids')
                                    {{ $directory->whereIn('id',$value)->pluck('name')->implode(', ') }}
                                @else
                                    {{ is_array($value)?implode(', ',$value):$value }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
                @if($draft->status==='pending')
                    <form method="POST" action="{{ route('user.attendance.ai-draft.decide',$draft->id) }}" class="row space">
                        @csrf
                        <button class="btn-primary" name="decision" value="apply">Approve and apply</button>
                        <button class="btn-ghost" name="decision" value="dismiss">Dismiss</button>
                    </form>
                @endif
            </article>
        @endforeach
    @else
        <p class="note">AI people assistance is off. The workspace owner can enable it in Working hours &amp; policy.</p>
    @endif
</section>
