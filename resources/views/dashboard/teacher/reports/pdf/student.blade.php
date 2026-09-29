<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: sans-serif; font-size: 11px; color: #222; }
h1 { font-size: 16px; margin: 0 0 2px; }
h2 { font-size: 13px; margin: 12px 0 6px; }
table { width: 100%; border-collapse: collapse; margin-top: 6px; }
th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
th { background: #eee; }
</style>
</head>
<body>
<h1>Student Card — {{ $exam->title }}</h1>
<small>{{ $card['summary']['student_name'] }} · Session #{{ $session->id }} · {{ $card['summary']['end_reason_display'] }} · {{ now()->format('Y-m-d H:i') }}</small>

<p>
Marks: {{ number_format($card['summary']['marks_secured'],2) }}/{{ number_format($card['summary']['total_marks'],2) }} ·
Percentage: {{ $card['summary']['percentage'] !== null ? number_format($card['summary']['percentage'],2).'%' : '—' }} ·
Result: @if($card['summary']['percentage'] === null) Pending @elseif($card['summary']['passed']) Pass @else Fail @endif ·
Time: {{ $card['summary']['time_spent'] }}s · Violations: {{ $card['summary']['violation_count'] }}
</p>

<h2>Answers</h2>
<table>
<thead><tr><th>#</th><th>Question</th><th>Status</th><th>Points</th><th>Time</th></tr></thead>
<tbody>
@foreach($card['rows'] as $row)
<tr>
<td>{{ $row['index'] }}</td>
<td>{{ \Illuminate\Support\Str::limit($row['question_text'], 80) }}<br><small>{{ $row['question_type'] }}</small></td>
<td>@if($row['status']=='correct') Correct @elseif($row['status']=='wrong') Wrong @else Not attempted @endif</td>
<td>{{ $row['points_earned'] !== null ? number_format($row['points_earned'],2) : '—' }}/{{ number_format($row['max_points'],2) }}</td>
<td>{{ $row['time_spent'] !== null ? $row['time_spent'].'s' : '—' }}</td>
</tr>
@endforeach
</tbody>
</table>
</body>
</html>
