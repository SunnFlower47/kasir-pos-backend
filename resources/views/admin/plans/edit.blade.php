@extends('admin.layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Edit Subscription Plan: {{ $plan->name }}</h1>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <form action="{{ route('admin.plans.update', $plan) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-3">
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" id="name" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->name }}">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="slug" class="block text-sm font-medium text-gray-700">Slug</label>
                            <input type="text" name="slug" id="slug" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->slug }}">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="price" class="block text-sm font-medium text-gray-700">Price (IDR)</label>
                            <input type="number" name="price" id="price" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->price }}">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="duration_in_days" class="block text-sm font-medium text-gray-700">Duration (Days)</label>
                            <input type="number" name="duration_in_days" id="duration_in_days" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->duration_in_days }}">
                        </div>

                        <div class="col-span-6">
                             <textarea name="description" id="description" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md">{{ $plan->description }}</textarea>
                        </div>
                        
                        <div class="col-span-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 border-b pb-2">Feature Limits</h3>
                        </div>

                        <div class="col-span-6 sm:col-span-2">
                             <label for="max_users" class="block text-sm font-medium text-gray-700">Max Users</label>
                             <input type="number" name="max_users" id="max_users" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->features['max_users'] ?? '' }}">
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                             <label for="max_outlets" class="block text-sm font-medium text-gray-700">Max Outlets</label>
                             <input type="number" name="max_outlets" id="max_outlets" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->features['max_outlets'] ?? '' }}">
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                             <label for="max_products" class="block text-sm font-medium text-gray-700">Max Products</label>
                             <input type="number" name="max_products" id="max_products" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="{{ $plan->features['max_products'] ?? '' }}">
                        </div>

                        <div class="col-span-6">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="is_active" value="0">
                                    <input id="is_active" name="is_active" type="checkbox" value="1" {{ $plan->is_active ? 'checked' : '' }} class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_active" class="font-medium text-gray-700">Active</label>
                                    <p class="text-gray-500">Inactive plans will not be visible to users.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Update Plan
                        </button>
                         <a href="{{ route('admin.plans.index') }}" class="ml-3 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
