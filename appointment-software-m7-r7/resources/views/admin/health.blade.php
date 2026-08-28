@extends('layouts.app')
@section('title', 'System health')
@section('content')
<h1>System health</h1>
<div class="card"><table class="table table-hover align-middle"><thead><tr><th>Dependency</th><th>Status</th><th>Details</th></tr></thead><tbody>
<tr><td>Database</td><td>{{ $database ? 'Healthy' : 'Failed' }}</td><td>{{ $details['database'] ?? 'Connection succeeded.' }}</td></tr>
<tr><td>MariaDB timezone tables</td><td>{{ $timezone ? 'Healthy' : 'Failed' }}</td><td>{{ $details['timezone'] ?? 'CONVERT_TZ with America/Toronto succeeded.' }}</td></tr>
<tr><td>Memcached</td><td>{{ $cache ? 'Healthy' : 'Failed' }}</td><td>{{ $details['cache'] ?? 'Write/read/delete test succeeded.' }}</td></tr>
</tbody></table></div>
@endsection
