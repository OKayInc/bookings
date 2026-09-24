@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div><h1 class="mb-1">Customers</h1><p class="text-body-secondary mb-0">Search appointment history by email or phone number.</p></div>
    @can('update', $organization)
        <a class="btn btn-outline-primary" href="{{ route('customers.settings.edit') }}">Reputation policies</a>
    @endcan
</div>

<div class="card mb-4">
    <form method="get" action="{{ route('customers.index') }}" class="row g-2 align-items-end">
        <div class="col-md-8">
            <label class="form-label" for="customer_search">Email or phone</label>
            <input id="customer_search" class="form-control" name="q" value="{{ $search }}" placeholder="customer@example.com or 613-555-1234">
        </div>
        <div class="col-md-auto"><button class="btn btn-primary" type="submit">Search</button></div>
        @if($search !== '')
            <div class="col-md-auto"><a class="btn btn-outline-secondary" href="{{ route('customers.index') }}">Clear</a></div>
        @endif
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Customer</th><th>Contact</th><th>Appointments</th><th>Successful</th><th>No-shows</th><th>List status</th><th></th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                @php
                    $entry = $customer->accessEntries->first();
                    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                @endphp
                <tr>
                    <td><strong>{{ $name ?: 'Unnamed customer' }}</strong></td>
                    <td>
                        {{ $customer->email ?: '—' }}
                        @if($customer->phone)
                            <br><span class="text-body-secondary">{{ $customer->phone }}</span>
                        @endif
                    </td>
                    <td>{{ $customer->bookings_count }}</td>
                    <td>{{ $customer->successful_count }}</td>
                    <td>{{ $customer->no_show_count }}</td>
                    <td>
                        @if($entry)
                            <span class="badge {{ $entry->list_type === 'blacklist' ? 'text-bg-danger' : 'text-bg-success' }}">{{ ucfirst($entry->list_type) }}</span>
                            @if($entry->status === 'suggested')
                                <span class="badge text-bg-warning">Suggested</span>
                            @endif
                            <div class="small text-body-secondary">{{ $entry->source === 'policy' ? 'Policy' : 'Manual' }}</div>
                        @else
                            <span class="text-body-secondary">—</span>
                        @endif
                    </td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('customers.show', $customer) }}">History</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">No customers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($customers->hasPages())
        <div class="mt-3">{{ $customers->links() }}</div>
    @endif
</div>
@endsection
