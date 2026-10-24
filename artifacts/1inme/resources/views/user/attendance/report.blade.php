<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
@page{margin:28px 28px 34px}body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#18243c}h1{font-size:22px;margin:0 0 8px}p{color:#526079;margin:5px 0 18px}table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}tr{page-break-inside:avoid}th{background:#edf2ff;text-align:left;font-size:8px}th,td{border-bottom:1px solid #dce2ec;padding:8px 5px;word-wrap:break-word}tfoot td{color:#526079;font-size:8px}.footer{position:fixed;bottom:-18px;font-size:8px;color:#526079}
</style>
</head>
<body>
<h1>Attendance · {{ ucfirst($meta['mode']) }} report</h1>
<p>{{ $meta['scope'] }} · {{ $meta['from'] }} to {{ $meta['to'] }} · {{ $meta['timezone'] }}</p>
<table>
<thead>
<tr>@foreach($rows[0] as $header)<th>{{ $header }}</th>@endforeach</tr>
</thead>
<tbody>@forelse(array_slice($rows,1) as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@empty<tr>
<td colspan="{{ count($rows[0]) }}">No attendance records for the selected staff and period.</td>
</tr>@endforelse</tbody>
</table>
<p>Recorded hours exclude breaks. Pending/rejected evidence remains included for follow-up. Approved overtime and leave are shown separately; this report does not calculate payroll.</p>
<div class="footer">Sayzio · Generated {{ now()->format('Y-m-d H:i') }} UTC</div>
</body>
</html>
