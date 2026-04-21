@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="display-5 fw-bold text-primary">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </h1>
        <div class="bg-light p-2 rounded-pill px-3">
            <i class="bi bi-calendar3 me-1"></i>
            <strong>Today:</strong> {{ now()->format('M d, Y') }}
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm stats-card bg-white">
                <div class="stats-icon text-primary">
                    <i class="bi bi-cash"></i>
                </div>
                <h6 class="text-muted mb-2">Daily Sales</h6>
                <h3 class="mb-0">₱{{ number_format($todaySales, 2) }}</h3>
                <div class="mt-2 text-muted">
                    <small>{{ $todayOrders }} orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm stats-card bg-white">
                <div class="stats-icon text-success">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h6 class="text-muted mb-2">Weekly Sales</h6>
                <h3 class="mb-0">₱{{ number_format($weeklySales, 2) }}</h3>
                <div class="mt-2 progress" style="height: 5px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ ($weeklySales > 0 && $monthlySales > 0) ? ($weeklySales / $monthlySales * 100) : 0 }}%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm stats-card bg-white">
                <div class="stats-icon text-info">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h6 class="text-muted mb-2">Monthly Sales</h6>
                <h3 class="mb-0">₱{{ number_format($monthlySales, 2) }}</h3>
                <div class="mt-2 text-muted">
                    <small>Current month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm stats-card bg-white">
                <div class="stats-icon text-warning">
                    <i class="bi bi-truck"></i>
                </div>
                <h6 class="text-muted mb-2">Pending Deliveries</h6>
                <h3 class="mb-0">{{ $pendingDeliveries }}</h3>
                <div class="mt-2">
                    <a href="{{ route('deliveries.index') }}" class="text-decoration-none small">
                        Manage <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Orders -->
<div class="col-md-8">
    <div class="card shadow-sm h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recent Orders</h5>
            <div>
                <form action="{{ route('dashboard') }}" method="GET" class="d-flex align-items-center">
                    <div class="input-group input-group-sm me-2" style="width: 200px;">
                        <input type="text" name="search" class="form-control" placeholder="Search customer" value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <select name="order_period" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                        <option value="today" {{ request('order_period', 'today') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="week" {{ request('order_period') == 'week' ? 'selected' : '' }}>This Week</option>
                        <option value="month" {{ request('order_period') == 'month' ? 'selected' : '' }}>This Month</option>
                    </select>
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <a href="{{ route('orders.index') }}" class="text-decoration-none">View</a>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td>{{ $order->id }}</td>
                            <td>{{ $order->customer->name }}</td>
                            <td>₱{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <span class="badge {{ $order->order_status == 'completed' ? 'bg-success' : ($order->order_status == 'pending' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                    {{ ucfirst($order->order_status) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $order->payment_status == 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ ucfirst($order->payment_status) }}
                                </span>
                            </td>
                            <td>{{ $order->created_at->format('M d, H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-3">
                                @if(request('search'))
                                    No orders found for "{{ request('search') }}"
                                @else
                                    No {{ request('order_period', 'today') == 'today' ? 'today\'s' : (request('order_period') == 'week' ? 'this week\'s' : 'this month\'s') }} orders found
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <span class="me-2">Show:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ route('dashboard', array_merge(request()->except('per_page', 'page'), ['per_page' => 10])) }}" class="btn {{ $perPage == 10 ? 'btn-primary' : 'btn-outline-secondary' }}">10</a>
                    <a href="{{ route('dashboard', array_merge(request()->except('per_page', 'page'), ['per_page' => 25])) }}" class="btn {{ $perPage == 25 ? 'btn-primary' : 'btn-outline-secondary' }}">25</a>
                    <a href="{{ route('dashboard', array_merge(request()->except('per_page', 'page'), ['per_page' => 50])) }}" class="btn {{ $perPage == 50 ? 'btn-primary' : 'btn-outline-secondary' }}">50</a>
                </div>
            </div>
            <div>
                {{ $recentOrders->links() }}
            </div>
        </div>
    </div>
</div>

        <!-- Low Stock & Quick Actions -->
        <div class="col-md-4">
            <!-- Low Stock Alerts -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                        Low Stock Alerts
                    </h5>
                    <span class="badge rounded-pill {{ $lowStockCount > 0 ? 'bg-danger' : 'bg-success' }}">
                        {{ $lowStockCount }}
                    </span>
                </div>
                <div class="card-body p-0">
                    @if($lowStockItems->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                            <p class="mt-2">All items are in stock</p>
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($lowStockItems as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-medium">{{ $item->name }}</span>
                                        <div class="small text-muted">{{ ucfirst($item->type) }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold {{ $item->quantity > 0 ? 'text-warning' : 'text-danger' }}">
                                            {{ $item->quantity }} left
                                        </div>
                                        <small class="text-muted">(Min: {{ $item->threshold }})</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="p-3">
                            <a href="{{ route('inventory.low-stock') }}" class="btn btn-outline-warning btn-sm d-block">
                                <i class="bi bi-eye me-1"></i>View All Low Stock Items
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('orders.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>New Order
                        </a>
                        <a href="{{ route('orders.walkin') }}" class="btn btn-success">
                            <i class="bi bi-person-plus me-2"></i>Walk-in Sale
                        </a>
                        <a href="{{ route('customers.create') }}" class="btn btn-secondary">
                            <i class="bi bi-person-add me-2"></i>Add Customer
                        </a>
                        <a href="{{ route('inventory.index') }}" class="btn btn-info text-white">
                            <i class="bi bi-box-seam me-2"></i>Inventory
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection