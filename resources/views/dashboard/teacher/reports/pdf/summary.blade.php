<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: sans-serif; font-size: 12px; color: #222; }
h1 { font-size: 18px; margin: 0 0 4px; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; }
th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
th { background: #eee; }
</style>
</head>
<body>
<h1>{{ $exam->title }} — Summary</h1>
<p>{{ $exam->subject->name ?? 'N/A' }} · Passing: {{ $exam->passing_marks }} · {{ now()->format('Y-m-d H:i') }}</p>
<table>
<tr><th>Appeared</th><td>{{ $summary['appeared'] }}</td><th>Completed</th><td>{{ $summary['completed'] }}</td></tr>
<tr><th>Passed</th><td>{{ $summary['passed'] }}</td><th>Failed</th><td>{{ $summary['failed'] }}</td></tr>
<tr><th>Terminated</th><td>{{ $summary['terminated'] }}</td><th>Expired</th><td>{{ $summary['expired'] }}</td></tr>
<tr><th>Ungraded</th><td>{{ $summary['ungraded'] }}</td><th>Pass Rate</th><td>{{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—' }}</td></tr>
<tr><th>Mean</th><td>{{ $summary['mean'] ?? '—' }}</td><th>Median</th><td>{{ $summary['median'] ?? '—' }}</td></tr>
<tr><th>Min</th><td>{{ $summary['min'] ?? '—' }}</td><th>Max</th><td>{{ $summary['max'] ?? '—' }}</td></tr>
</table>

<h2 style="margin-top:16px; font-size:14px;">Register (excerpt)</h2>
<table>
<thead><tr><th>#</th><th>Student</th><th>Status</th><th>%</th><th>Result</th></tr></thead>
<tbody>
@forelse($register as $i => $row)
<tr><td>{{ $i+1 }}</td><td>{{ $row['student_name'] }}</td><td>{{ $row['end_reason_display'] }}</td><td>{{ $row['percentage'] !== null ? number_format($row['percentage'],2).'%' : '—' }}</td><td>{{ !$row['is_graded'] ? 'Pending' : ($row['passed'] ? 'Pass' : 'Fail') }}</td></tr>
@empty
<tr><td colspan="5">No sessions.</td></tr>
@endforelse
</tbody>
</table>
</body>
</html>
