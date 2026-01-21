@extends('admin.layouts.app')

@section('content')
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold leading-6 text-gray-900">All Users Management</h1>
        <p class="mt-2 text-sm text-gray-700">A centralized list of all users across the system (System Admins & Tenant Users).</p>
    </div>
</div>

<!-- Filters -->
<div class="mt-4 bg-white p-4 shadow sm:rounded-lg">
    <form action="{{ route('admin.users.index') }}" method="GET" class="grid grid-cols-1 gap-y-6 sm:grid-cols-4 sm:gap-x-4">
        <div>
            <label for="search" class="block text-sm font-medium leading-6 text-gray-900">Search</label>
            <div class="mt-2">
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Name or Email" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
        </div>

        <div>
            <label for="role" class="block text-sm font-medium leading-6 text-gray-900">Role</label>
            <div class="mt-2">
                <select name="role" id="role" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <option value="">All Roles</option>
                    @foreach($roles as $roleName)
                        <option value="{{ $roleName }}" {{ request('role') == $roleName ? 'selected' : '' }}>{{ $roleName }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="type" class="block text-sm font-medium leading-6 text-gray-900">User Type</label>
            <div class="mt-2">
                <select name="type" id="type" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <option value="">All Types</option>
                    <option value="system" {{ request('type') == 'system' ? 'selected' : '' }}>System Admin</option>
                    <option value="tenant" {{ request('type') == 'tenant' ? 'selected' : '' }}>Tenant User</option>
                </select>
            </div>
        </div>

        <div class="flex items-end">
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 w-full sm:w-auto">Filter</button>
            <a href="{{ route('admin.users.index') }}" class="ml-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 w-full sm:w-auto text-center">Reset</a>
        </div>
    </form>
</div>

<div class="mt-8 flow-root">
    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Name / Email</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Role</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Tenant / Outlet</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                <span class="sr-only">Edit</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($users as $user)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                <div class="text-gray-500">{{ $user->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @foreach($user->roles as $role)
                                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($user->tenant_id)
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Tenant</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">System</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($user->tenant)
                                    <div>{{ $user->tenant->name }}</div>
                                    @if($user->outlet)
                                    <div class="text-xs text-gray-400">{{ $user->outlet->name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
