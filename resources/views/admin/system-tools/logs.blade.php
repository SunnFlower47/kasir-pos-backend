@extends('admin.layouts.app')

@section('title', 'System Logs')

@section('content')
<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">System Logs</h1>
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

    <div class="overflow-hidden bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 flex items-center justify-between border-b border-gray-200">
            <h3 class="text-base font-semibold leading-6 text-gray-900">laravel.log <span class="text-gray-500 font-normal">(Last 100 lines)</span></h3>
            <a href="{{ request()->url() }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Refresh
            </a>
        </div>
        <div class="px-4 py-5 sm:p-6 bg-gray-50">
            <div class="bg-gray-900 text-gray-300 p-4 rounded-md shadow-inner font-mono text-xs sm:text-sm overflow-x-auto whitespace-pre-wrap" style="max-height: 600px; overflow-y: auto;">
                @forelse($content as $line)
                    <div class="border-b border-gray-800 py-1 hover:bg-gray-800 break-all">{{ $line }}</div>
                @empty
                    <div class="text-gray-500 text-center py-10">Log file is empty or not found.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
