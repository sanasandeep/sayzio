@if(!empty($d['instructor_bio']))
    <h4>About the instructor</h4><p class="preserve-lines">{{ $d['instructor_bio'] }}</p>
@endif
@if(!empty($d['syllabus']))
    <h4>What you’ll learn</h4>
    <ol class="syllabus">
        @foreach(preg_split('/\r?\n/', $d['syllabus']) as $topic)
            @if(trim($topic))
                <li>{{ trim($topic) }}</li>
            @endif
        @endforeach
    </ol>
@endif
@if(!empty($d['prerequisites']))
    <h4>Before you start</h4><p class="preserve-lines">{{ $d['prerequisites'] }}</p>
@endif
@if(!empty($d['certificate']))
    <h4>Certificate</h4><p>{{ $d['certificate'] }}</p>
@endif
@foreach($d['batches'] ?? [] as $batch)
    <div class="batch-info">
        <h4>{{ $batch['name'] }}</h4>
        <p>{{ $batch['start_date'] ?? '' }}
            @if(!empty($batch['end_date']))
                – {{ $batch['end_date'] }}
            @endif
        </p>
        <p>{{ $batch['schedule'] ?? '' }} ({{ $timezone }})</p>
        <small>{{ ucfirst($batch['status'] ?? 'closed') }}
            @if(isset($batch['seats']))
                · {{ $batch['seats'] }} remaining seats
            @endif
        </small>
    </div>
@endforeach
