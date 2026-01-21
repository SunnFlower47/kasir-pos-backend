@extends('admin.layouts.app')

@section('content')
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold leading-6 text-gray-900">Edit Role: {{ $role->name }}</h1>
        <p class="mt-2 text-sm text-gray-700">Manage permissions for this global role.</p>
    </div>
</div>

<form action="{{ route('admin.roles.update', $role) }}" method="POST" class="mt-8 space-y-8">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 border-b border-gray-900/10 pb-8">
        <div class="sm:col-span-4">
            <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Role Name</label>
            <div class="mt-2">
                <input type="text" name="name" id="name" value="{{ $role->name }}" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
        </div>

        <div class="sm:col-span-4">
            <label for="description" class="block text-sm font-medium leading-6 text-gray-900">Description</label>
            <div class="mt-2">
                <textarea id="description" name="description" rows="3" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">{{ $role->description }}</textarea>
            </div>
        </div>
    </div>

    <!-- Permissions Matrix -->
    <div class="border-b border-gray-900/10 pb-8">
        <h2 class="text-base font-semibold leading-7 text-gray-900">Permissions</h2>
        <p class="mt-1 text-sm leading-6 text-gray-600">Select the permissions assigned to this role.</p>

        <div class="mt-6 space-y-6">
            @foreach($permissions as $group => $perms)
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-sm font-medium text-gray-900 uppercase tracking-wide mb-3 border-b border-gray-200 pb-2">{{ ucfirst($group) }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($perms as $permission)
                    <div class="relative flex items-start">
                        <div class="flex h-6 items-center">
                            <input id="perm-{{ $permission->id }}" name="permissions[]" value="{{ $permission->name }}" type="checkbox" 
                                {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-3 text-sm leading-6">
                            <label for="perm-{{ $permission->id }}" class="font-medium text-gray-900 select-none cursor-pointer">{{ $permission->name }}</label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-x-6">
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save Changes</button>
        <a href="{{ route('admin.roles.index') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
    </div>
</form>
@endsection
