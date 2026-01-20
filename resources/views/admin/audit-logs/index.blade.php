@extends('admin.layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Audit Logs</h1>
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="flex gap-2">
            <input type="text" name="search" placeholder="Search logs..." class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border">
            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Filter
            </button>
        </form>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                System Activity History
            </h3>
            <span class="text-sm text-gray-500">
                Viewing latest activities
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="font-medium text-gray-900">{{ $log->created_at->format('M d, Y') }}</div>
                            <div class="text-xs">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($log->user)
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs ring-2 ring-white">
                                        {{ substr($log->user->name, 0, 2) }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $log->user->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $log->user->email }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    System
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $color = 'gray';
                                if(str_contains($log->event, 'create')) $color = 'green';
                                if(str_contains($log->event, 'update')) $color = 'blue';
                                if(str_contains($log->event, 'delete')) $color = 'red';
                                if(str_contains($log->event, 'impersonate')) $color = 'purple';
                                if(str_contains($log->event, 'suspend')) $color = 'orange';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800 uppercase tracking-wide">
                                {{ str_replace('_', ' ', $log->event) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="font-mono text-xs text-gray-400 mb-1">{{ class_basename($log->model_type) }}</div>
                            <div class="font-medium text-gray-900">ID: {{ $log->model_id }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if($log->new_values || $log->old_values)
                                <details class="group cursor-pointer">
                                    <summary class="text-indigo-600 hover:text-indigo-800 font-medium text-xs list-none focus:outline-none">
                                        <span class="group-open:hidden">View Changes &darr;</span>
                                        <span class="hidden group-open:inline">Hide Changes &uarr;</span>
                                    </summary>
                                    <div class="mt-2 p-3 bg-gray-50 rounded-md border border-gray-200 font-mono text-xs overflow-x-auto">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @if($log->old_values)
                                            <div>
                                                <span class="block text-xs font-bold text-red-600 mb-1 uppercase tracking-wider">Old Values</span>
                                                <pre class="text-gray-700 whitespace-pre-wrap">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                            @endif
                                            @if($log->new_values)
                                            <div>
                                                <span class="block text-xs font-bold text-green-600 mb-1 uppercase tracking-wider">New Values</span>
                                                <pre class="text-gray-700 whitespace-pre-wrap">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </details>
                            @else
                                <span class="text-gray-400 text-xs italic">No changes recorded</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="mt-2 block text-sm font-medium">No system logs found</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($logs->hasPages())
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
