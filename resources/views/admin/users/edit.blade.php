@extends('admin.layouts.app')

@section('content')
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold leading-6 text-gray-900">Edit User: {{ $user->name }}</h1>
        <p class="mt-2 text-sm text-gray-700">Update user details or assign global roles.</p>
    </div>
</div>

<div class="mt-8 max-w-xl">
    <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')
        
        @if($user->tenant_id)
        <div class="rounded-md bg-yellow-50 p-4 border border-yellow-200 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                     <!-- Icon -->
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Tenant User</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>This user belongs to tenant <strong>{{ $user->tenant->name }}</strong>. Changing roles here might override tenant-specific settings. Proceed with caution.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Name</label>
            <div class="mt-2">
                <input type="text" name="name" id="name" value="{{ $user->name }}" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Email</label>
            <div class="mt-2">
                <input type="email" name="email" id="email" value="{{ $user->email }}" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
        </div>

        <div class="border-t border-gray-900/10 pt-6">
             <h2 class="text-base font-semibold leading-7 text-gray-900">Role Assignment</h2>
             <p class="mt-1 text-sm leading-6 text-gray-600">Assign a global role to this user.</p>
             
             <div class="mt-4">
                <select id="role" name="role" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <option value="">No Role Change</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" 
                            {{ $user->hasRole($role->name) ? 'selected' : '' }}
                            {{ ($user->tenant_id && $role->scope === 'system') ? 'disabled' : '' }}
                        >
                            {{ $role->name }} ({{ ucfirst($role->scope) }}) 
                            {{ ($user->tenant_id && $role->scope === 'system') ? '- System Only' : '' }}
                        </option>
                    @endforeach
                </select>
                @if($user->tenant_id)
                <p class="mt-2 text-xs text-red-500">* System roles cannot be assigned to Tenant users.</p>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-900/10 pt-6">
            <h2 class="text-base font-semibold leading-7 text-gray-900">Change Password</h2>
            <p class="mt-1 text-sm leading-6 text-gray-600">Leave blank to keep current password.</p>
            
             <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 mt-4">
                <div class="sm:col-span-3">
                    <label for="password" class="block text-sm font-medium leading-6 text-gray-900">New Password</label>
                    <div class="mt-2">
                        <input type="password" name="password" id="password" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="password_confirmation" class="block text-sm font-medium leading-6 text-gray-900">Confirm Password</label>
                    <div class="mt-2">
                        <input type="password" name="password_confirmation" id="password_confirmation" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-900/10 pt-6">
            <div class="relative flex gap-x-3">
                <div class="flex h-6 items-center">
                    <input id="is_active" name="is_active" type="checkbox" value="1" {{ $user->is_active ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                </div>
                <div class="text-sm leading-6">
                    <label for="is_active" class="font-medium text-gray-900">Active Account</label>
                    <p class="text-gray-500">Disable to block user access immediately.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-x-6">
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Update User</button>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
        </div>
    </form>
</div>
@endsection
