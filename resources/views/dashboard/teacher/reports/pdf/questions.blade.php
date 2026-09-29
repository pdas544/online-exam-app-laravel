<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: sans-serif; font-size: 11px; color: #222; }
h1 { font-size: 16px; margin: 0 0 4px; }
table { width: 100%; border-collapse: collapse; margin-top: 8px; }
th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
th { background: #eee; }
</style>
</head>
<body>
<h1>{{ $exam->title }} — Question Analysis</h1>
<small>{{ $exam->subject->name ?? 'N/A' }} · {{ now()->format('Y-m-d H:i') }}</small>
<table>
<thead>
<tr><th>#</th><th>Question</th><th>Type</th><th>Attempt Rate</th><th>Correct Rate</th><th>Avg Time</th><th>Distractors</th></tr>
</thead>
<tbody>
@forelse($questions as $i => $q)
<tr>
<td>{{ $i+1 }}</td>
<td>{{ \Illuminate\Support\Str::limit($q['question_text'], 80) }}</td>
<td>{{ $q['question_type'] }}</td>
<td>{{ $q['attempt_rate'] }}% ({{ $q['attempted'] }}/{{ $q['total_sessions'] }})</td>
<td>{{ $q['correct_rate'] !== null ? $q['correct_rate'].'%' : '—' }} ({{ $q['correct'] }}/{{ $q['attempted'] }})</td>
<td>{{ $q['avg_time'] !== null ? $q['avg_time'].'s' : '—' }}</td>
<td>{{ json_encode($q['distractor_counts']) }}</td>
</tr>
@empty
<tr><td colspan="7">No questions.</td></tr>
@endforelse
</tbody>
</table>
</body>
</html>
