@extends('admin.layouts.app')

@section('title', 'Maintenance Mode')

@section('content')
<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Maintenance Management</h1>
        </div>
        <div class="mt-4 sm:ml-4 sm:mt-0 sm:flex-none">
            <a href="{{ route('admin.system-tools.index') }}" class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                </svg>
                Back to Tools
            </a>
        </div>
    </div>

    <div class="flex justify-center">
        <div class="w-full max-w-lg">
            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Current Status</h3>
                </div>
                <div class="px-4 py-5 sm:p-6 text-center">
                    @if($isDown)
                        <div class="mb-6">
                            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-100 mb-4">
                                <svg class="h-16 w-16 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-red-600 mb-2">MAINTENANCE MODE IS ON</h2>
                            <p class="text-gray-500">The application is currently inaccessible to users (503 Service Unavailable).</p>
                        </div>
                    @else
                        <div class="mb-6">
                            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-green-100 mb-4">
                                <svg class="h-16 w-16 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-green-600 mb-2">LIVE</h2>
                            <p class="text-gray-500">The application is running normally and accepting traffic.</p>
                        </div>
                    @endif

                    <div class="border-t border-gray-200 mt-6 pt-6">
                        <form action="{{ route('admin.system-tools.maintenance.toggle') }}" method="POST">
                            @csrf
                            @if($isDown)
                                <button type="submit" class="w-full inline-flex justify-center items-center rounded-md bg-green-600 px-3 py-3 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600" onclick="return confirm('Are you sure you want to bring the application BACK ONLINE?');">
                                    <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                                    </svg>
                                    Bring Application Online
                                </button>
                            @else
                                <button type="submit" class="w-full inline-flex justify-center items-center rounded-md bg-red-600 px-3 py-3 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600" onclick="return confirm('WARNING: This will take the application offline for all users. Are you sure?');">
                                    <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                                    </svg>
                                    Enable Maintenance Mode
                                </button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
