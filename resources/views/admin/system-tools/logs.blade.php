@extends('admin.layouts.app')

@section('title', 'System Logs')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">System Logs</h1>
    <a href="{{ route('admin.system-tools.index') }}" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Tools
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">laravel.log (Last 100 lines)</h6>
        <a href="{{ request()->url() }}" class="btn btn-sm btn-primary">
            <i class="fas fa-sync-alt fa-sm"></i> Refresh
        </a>
    </div>
    <div class="card-body">
        <div class="bg-dark text-white p-3 rounded" style="max-height: 600px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;">
            @forelse($content as $line)
                <div class="border-bottom border-secondary py-1 text-break">{{ $line }}</div>
            @empty
                <div class="text-muted text-center py-5">Log file is empty or not found.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
