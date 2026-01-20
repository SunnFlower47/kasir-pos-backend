@extends('admin.layouts.app')

@section('title', 'Maintenance Mode')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Maintenance Management</h1>
    <a href="{{ route('admin.system-tools.index') }}" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Tools
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Current Status</h6>
            </div>
            <div class="card-body text-center py-5">
                @if($isDown)
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle fa-5x text-danger mb-3"></i>
                        <h2 class="text-danger font-weight-bold">MAINTENANCE MODE IS ON</h2>
                        <p class="text-muted">The application is currently inaccessible to users (503 Service Unavailable).</p>
                    </div>
                @else
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                        <h2 class="text-success font-weight-bold">LIVE</h2>
                        <p class="text-muted">The application is running normally.</p>
                    </div>
                @endif

                <hr>

                <form action="{{ route('admin.system-tools.maintenance.toggle') }}" method="POST">
                    @csrf
                    @if($isDown)
                        <button type="submit" class="btn btn-success btn-lg btn-block" onclick="return confirm('Are you sure you want to bring the application BACK ONLINE?');">
                            <i class="fas fa-power-off fa-sm mr-2"></i> Bring Application Online
                        </button>
                    @else
                        <button type="submit" class="btn btn-danger btn-lg btn-block" onclick="return confirm('WARNING: This will take the application offline for all users. Are you sure?');">
                            <i class="fas fa-power-off fa-sm mr-2"></i> Enable Maintenance Mode
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
