@extends('admin.layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Extend Subscription: {{ $tenant->name }}</h1>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <form action="{{ route('admin.tenants.process_extend', $tenant) }}" method="POST">
                    @csrf
                    
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-3">
                            <label for="plan_id" class="block text-sm font-medium text-gray-700">Select Plan</label>
                            <select id="plan_id" name="plan_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" onchange="updateDuration()">
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" data-duration="{{ $plan->duration_in_days }}" {{ ($tenant->activeSubscription && $tenant->activeSubscription->plan_name == $plan->name) ? 'selected' : '' }}>
                                        {{ $plan->name }} (Rp {{ number_format($plan->price, 0) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="duration_days" class="block text-sm font-medium text-gray-700">Duration (Days)</label>
                            <input type="number" name="duration_days" id="duration_days" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" value="30">
                        </div>

                        <div class="col-span-6">
                             <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                             <textarea name="notes" id="notes" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md" placeholder="Reason for manual extension..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Process Extension
                        </button>
                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="ml-3 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function updateDuration() {
        var select = document.getElementById('plan_id');
        var selectedOption = select.options[select.selectedIndex];
        var duration = selectedOption.getAttribute('data-duration');
        document.getElementById('duration_days').value = duration;
    }
    // Init
    updateDuration();
</script>
@endsection
