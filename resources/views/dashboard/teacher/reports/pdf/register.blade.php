<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: sans-serif; font-size: 11px; color: #222; }
h1 { font-size: 18px; margin: 0 0 4px; }
h2 { font-size: 14px; margin: 16px 0 8px; }
small { color: #666; }
table { width: 100%; border-collapse: collapse; margin-top: 8px; }
th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
th { background: #eee; }
.summary td { border: none; padding: 2px 6px; }
</style>
</head>
<body>
<h1>{{ $exam->title }} — Result Register</h1>
<small>{{ $exam->subject->name ?? 'N/A' }} · Passing: {{ $exam->passing_marks }} · Generated {{ now()->format('Y-m-d H:i') }}</small>

<h2>Summary</h2>
<table class="summary">
<tr><td>Appeared: {{ $summary['appeared'] }}</td><td>Completed: {{ $summary['completed'] }}</td><td>Passed: {{ $summary['passed'] }}</td><td>Failed: {{ $summary['failed'] }}</td><td>Ungraded: {{ $summary['ungraded'] }}</td></tr>
<tr><td>Mean: {{ $summary['mean'] ?? '—' }}</td><td>Median: {{ $summary['median'] ?? '—' }}</td><td>Min: {{ $summary['min'] ?? '—' }}</td><td>Max: {{ $summary['max'] ?? '—' }}</td><td>Pass Rate: {{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—' }}</td></tr>
</table>

<h2>Register</h2>
<table>
<thead>
<tr><th>#</th><th>Student</th><th>Attempt</th><th>Status</th><th>Marks</th><th>%</th><th>Result</th><th>Viol.</th><th>Time</th></tr>
</thead>
<tbody>
@forelse($register as $i => $row)
<tr>
<td>{{ $i+1 }}</td>
<td>{{ $row['student_name'] }}<br><small>{{ $row['student_email'] }}</small></td>
<td>{{ $row['attempt'] }}</td>
<td>{{ $row['end_reason_display'] }}</td>
<td>{{ number_format($row['marks_secured'],2) }}/{{ number_format($row['total_marks'],2) }}</td>
<td>{{ $row['percentage'] !== null ? number_format($row['percentage'],2).'%' : '—' }}</td>
<td>{{ !$row['is_graded'] ? 'Pending' : ($row['passed'] ? 'Pass' : 'Fail') }}</td>
<td>{{ $row['violation_count'] }}</td>
<td>{{ $row['time_spent'] }}s</td>
</tr>
@empty
<tr><td colspan="9">No sessions.</td></tr>
@endforelse
</tbody>
</table>
</body>
</html>
