@extends('admin.layouts.app')

@section('content')
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold leading-6 text-gray-900">Create Role</h1>
        <p class="mt-2 text-sm text-gray-700">Create a new global role template.</p>
    </div>
</div>

<div class="mt-8 max-w-xl">
    <form action="{{ route('admin.roles.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <div>
            <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Role Name</label>
            <div class="mt-2">
                <input type="text" name="name" id="name" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
        </div>

        <div>
            <label for="scope" class="block text-sm font-medium leading-6 text-gray-900">Scope</label>
            <div class="mt-2">
                <select id="scope" name="scope" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <option value="tenant">Tenant (Available for Tenants to assign)</option>
                    <option value="system">System (Only for System Admins)</option>
                </select>
            </div>
            <p class="mt-2 text-xs text-gray-500">System roles give access to this Admin Panel. Tenant roles are for POS users.</p>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium leading-6 text-gray-900">Description</label>
            <div class="mt-2">
                <textarea id="description" name="description" rows="3" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"></textarea>
            </div>
        </div>

        <div class="flex items-center gap-x-6">
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Create Role</button>
            <a href="{{ route('admin.roles.index') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
        </div>
    </form>
</div>
@endsection
