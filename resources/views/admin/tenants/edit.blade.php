@extends('admin.layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
             <h1 class="text-2xl font-semibold text-gray-900">Edit Tenant: {{ $tenant->name }}</h1>
             <a href="{{ route('admin.tenants.index') }}" class="text-indigo-600 hover:text-indigo-900">Back to List</a>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <form action="{{ route('admin.tenants.update', $tenant->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <!-- Name -->
                        <div class="col-span-6 sm:col-span-3">
                            <label for="name" class="block text-sm font-medium text-gray-700">Company Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $tenant->name) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Owner Name -->
                        <div class="col-span-6 sm:col-span-3">
                            <label for="owner_name" class="block text-sm font-medium text-gray-700">Owner Name</label>
                            <input type="text" name="owner_name" id="owner_name" value="{{ old('owner_name', $tenant->owner_name) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('owner_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div class="col-span-6 sm:col-span-3">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $tenant->email) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-span-6 sm:col-span-3">
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $tenant->phone) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <!-- Business Type -->
                        <div class="col-span-6 sm:col-span-3">
                            <label for="business_type" class="block text-sm font-medium text-gray-700">Business Type</label>
                            <select name="business_type" id="business_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="retail" {{ old('business_type', $tenant->business_type) == 'retail' ? 'selected' : '' }}>Retail</option>
                                <option value="fnb" {{ old('business_type', $tenant->business_type) == 'fnb' ? 'selected' : '' }}>F&B</option>
                                <option value="service" {{ old('business_type', $tenant->business_type) == 'service' ? 'selected' : '' }}>Service</option>
                                <option value="other" {{ old('business_type', $tenant->business_type) == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        
                        <!-- Address -->
                        <div class="col-span-6">
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" id="address" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md">{{ old('address', $tenant->address) }}</textarea>
                        </div>

                        <!-- Active Status -->
                         <div class="col-span-6">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $tenant->is_active) ? 'checked' : '' }} class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    <!-- Hidden input to handle unchecked state -->
                                    <input type="hidden" name="is_active" value="0">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_active" class="font-medium text-gray-700">Active Tenant</label>
                                    <p class="text-gray-500">Unchecking this will prevent any user from this tenant logging in.</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Save Changes
                    </button>
                    <!-- Form Delete -->
                </div>
            </form>
            
            <div class="px-4 py-3 bg-gray-50 text-left sm:px-6 border-t border-gray-200">
                 <form action="{{ route('admin.tenants.destroy', $tenant->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this tenant? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">Delete Tenant (Dangerous)</button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection
