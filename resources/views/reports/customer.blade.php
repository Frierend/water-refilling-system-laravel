@extends('layouts.app')

@section('styles')
<style>
    @media print {
        /* Hide browser's default header and footer */
        @page {
            margin-top: 0.5cm;
            margin-bottom: 0.5cm;
            margin-left: 0.5cm;
            margin-right: 0.5cm;
            size: auto;
        }
        
        /* Hide browser header/footer content */
        html {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        body * { visibility: hidden; }
        .printable-section, .printable-section * { visibility: visible; }
        .printable-section {
            position: absolute; left: 0; top: 0; width: 100%; padding: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .no-print, .stats-card, .chart-container { display: none !important; }
        
        /* Enhanced table styling */
        .table { width: 100% !important; border-collapse: collapse !important; font-size: 10pt !important; }
        .table th, .table td { border: 1px solid #ddd !important; padding: 5px !important; }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10pt;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .print-table th {
            background-color: #f0f7fa !important;
            color: #2c3e50;
            font-weight: 600;
            border: 1px solid #c8d6e5;
            padding: 10px;
            text-align: left;
            text-transform: uppercase;
            font-size: 9pt;
            letter-spacing: 0.5px;
        }
        .print-table td {
            border: 1px solid #e9ecef;
            padding: 8px 10px;
            text-align: left;
            vertical-align: middle;
        }
        .print-table tr:nth-child(even) {
            background-color: #f8fafc !important;
        }
        
        /* Print header styling */
        .print-header { 
            text-align: center; 
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3490dc;
        }
        .company-name { 
            font-size: 28px; 
            font-weight: 800; 
            margin-bottom: 5px; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            color: #2c3e50;
        }
        .report-title { 
            font-size: 20px; 
            font-weight: 600; 
            margin-bottom: 8px;
            color: #3490dc;
            letter-spacing: 1px;
        }
        .report-period { 
            font-size: 16px; 
            margin-bottom: 15px;
            color: #606f7b;
            font-style: italic;
        }
        
        /* Print footer styling */
        .print-footer {
            margin-top: 35px;
            page-break-inside: avoid;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            font-size: 9pt;
            color: #606f7b;
        }
        
        /* Badge styling for print */
        .badge { 
            background-color: transparent !important; 
            color: #000 !important; 
            font-weight: normal !important; 
            padding: 0 !important; 
        }
    }
    
    /* Loading overlay */
    .loading-overlay { 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background-color: rgba(255,255,255,0.9); 
        z-index: 9999; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        flex-direction: column;
        backdrop-filter: blur(5px);
    }
    .spinner { 
        width: 50px; 
        height: 50px; 
        border: 4px solid var(--primary-color); 
        border-radius: 50%; 
        border-top: 4px solid #f3f3f3; 
        animation: spin 1s linear infinite; 
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
@endsection

@section('content')
<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay no-print" style="display: none;">
    <div class="spinner mb-3"></div>
    <h5>Generating Report...</h5>
</div>

<!-- Page Header -->
@component('components.page-header', [
    'icon' => 'people',
    'title' => 'Customer Report',
    'subtitle' => $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y')
])
    @slot('actions')
        <div class="btn-group mb-2">
            <button onclick="window.print()" class="btn btn-outline-dark">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Export options</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="{{ route('reports.customer.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('reports.customer.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-text me-2"></i>Export to CSV
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('reports.customer.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF
                    </a>
                </li>
            </ul>
        </div>
    @endslot
@endcomponent

<!-- Filters -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8">
                <h5 class="card-title mb-3">Report Period</h5>
                <div class="btn-group-report mb-3">
                    <a href="{{ route('reports.customer', ['period' => 'daily', 'filter' => request('filter', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'daily' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-day me-1"></i> Daily
                    </a>
                    <a href="{{ route('reports.customer', ['period' => 'weekly', 'filter' => request('filter', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'weekly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-week me-1"></i> Weekly
                    </a>
                    <a href="{{ route('reports.customer', ['period' => 'monthly', 'filter' => request('filter', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'monthly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-month me-1"></i> Monthly
                    </a>
                    <a href="{{ route('reports.customer', ['period' => 'custom', 'filter' => request('filter', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'custom' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-range me-1"></i> Custom Range
                    </a>
                </div>
                
                <form id="reportForm" action="{{ route('reports.customer') }}" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="period" value="custom">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100" onclick="showLoading()">
                            <i class="bi bi-search me-1"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4">
                <h5 class="card-title mb-3">Filter Options</h5>
                <div class="mb-3">
                    <label for="filter" class="form-label">Customer Type</label>
                    <select id="filterSelect" name="filter" class="form-select" onchange="updateFilter(this.value)">
                        <option value="all" {{ request('filter', 'all') == 'all' ? 'selected' : '' }}>All Customers</option>
                        <option value="regular" {{ request('filter') == 'regular' ? 'selected' : '' }}>Regular Customers</option>
                        <option value="non-regular" {{ request('filter') == 'non-regular' ? 'selected' : '' }}>Non-Regular Customers</option>
                        <option value="top" {{ request('filter') == 'top' ? 'selected' : '' }}>Top Customers</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Table -->
@component('components.datatable', ['header' => 'Customer Details', 'headerActions' => '<span class="text-muted">' . $customers->total() . ' customers</span>'])
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Orders</th>
                <th>Total Spent</th>
                <th>Avg. Order</th>
                <th>Last Order</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
            <tr>
                <td>{{ $customer->id }}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary me-2" style="color: #fff; font-weight: bold;">
                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $customer->name }}</div>
                            <small class="text-muted">{{ $customer->phone }}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge {{ $customer->is_regular ? 'bg-success' : 'bg-secondary' }} rounded-pill">
                        {{ $customer->is_regular ? 'Regular' : 'One-time' }}
                    </span>
                </td>
                <td>{{ $customer->orders_count }}</td>
                <td class="fw-semibold">₱{{ number_format($customer->total_spent, 2) }}</td>
                <td>₱{{ number_format($customer->orders_count > 0 ? $customer->total_spent / $customer->orders_count : 0, 2) }}</td>
                <td>{{ $customer->last_order ? $customer->last_order->format('M d, Y') : 'N/A' }}</td>
                <td class="text-end">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('customers.show', $customer->id) }}">
                                    <i class="bi bi-eye me-2"></i>View Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('customers.edit', $customer->id) }}">
                                    <i class="bi bi-pencil me-2"></i>Edit Customer
                                </a>
                            </li>
                            @if($customer->orders_count == 0)
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('customers.destroy', $customer->id) }}" method="POST" class="delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item delete-btn">
                                        <i class="bi bi-trash text-danger me-2"></i><span class="text-danger">Delete</span>
                                    </button>
                                </form>
                            </li>
                            @endif
                        </ul>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center py-3">No customers found for the selected criteria</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    
    @if($customers->hasPages())
    @slot('footer')
        <div class="d-flex justify-content-between align-items-center">
            <div class="per-page-selector">
                <span class="me-2">Show:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ route('reports.customer', array_merge(request()->except('per_page', 'page'), ['per_page' => 10])) }}" 
                       class="btn {{ $perPage == 10 ? 'btn-primary' : 'btn-outline-secondary' }}">10</a>
                    <a href="{{ route('reports.customer', array_merge(request()->except('per_page', 'page'), ['per_page' => 20])) }}" 
                       class="btn {{ $perPage == 20 ? 'btn-primary' : 'btn-outline-secondary' }}">20</a>
                    <a href="{{ route('reports.customer', array_merge(request()->except('per_page', 'page'), ['per_page' => 50])) }}" 
                       class="btn {{ $perPage == 50 ? 'btn-primary' : 'btn-outline-secondary' }}">50</a>
                    <a href="{{ route('reports.customer', array_merge(request()->except('per_page', 'page'), ['per_page' => 100])) }}" 
                       class="btn {{ $perPage == 100 ? 'btn-primary' : 'btn-outline-secondary' }}">100</a>
                </div>
            </div>
            <div>
                {{ $customers->withQueryString()->links() }}
            </div>
        </div>
    @endslot
    @endif
@endcomponent

<!-- Printable Section -->
<div class="printable-section">
    <div class="print-header">
        <div class="company-name">MI-GAIL WATER</div>
        <div class="report-title">CUSTOMER REPORT</div>
        <div class="report-period">{{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}</div>
    </div>
    
    <!-- Summary Section -->
    <div class="summary-section mb-4">
        <div class="row">
            <div class="col-6">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th class="text-end">Total Customers:</th>
                        <td>{{ $totalCustomers }}</td>
                    </tr>
                    <tr>
                        <th class="text-end">Regular Customers:</th>
                        <td>{{ $regularCustomers ?? count(array_filter($customers->toArray()['data'] ?? [], function($c) { return $c['is_regular']; })) }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-6">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th class="text-end">Total Orders:</th>
                        <td>{{ $totalOrders }}</td>
                    </tr>
                    <tr>
                        <th class="text-end">Total Sales:</th>
                        <td>₱{{ number_format($totalSales, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Customer Table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Orders</th>
                <th>Total Spent</th>
                <th>Avg. Order</th>
                <th>Last Order</th>
            </tr>
        </thead>
        <tbody>
            @foreach($customers as $customer)
            <tr>
                <td>{{ $customer->id }}</td>
                <td>{{ $customer->name }}</td>
                <td>{{ $customer->is_regular ? 'Regular' : 'One-time' }}</td>
                <td>{{ $customer->orders_count }}</td>
                <td>₱{{ number_format($customer->total_spent, 2) }}</td>
                <td>₱{{ number_format($customer->orders_count > 0 ? $customer->total_spent / $customer->orders_count : 0, 2) }}</td>
                <td>{{ $customer->last_order ? $customer->last_order->format('M d, Y') : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Total: {{ $totalCustomers }} customers</strong></td>
                <td><strong>{{ $totalOrders }}</strong></td>
                <td><strong>₱{{ number_format($totalSales, 2) }}</strong></td>
                <td><strong>₱{{ $avgOrderValue ? number_format($avgOrderValue, 2) : '0.00' }}</strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    
    <div class="print-footer mt-4">
        <div class="row">
            <div class="col-6">
                <p class="mb-0"><strong>Generated by:</strong> {{ Auth::user()->name ?? 'DuckworthL' }}</p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-0"><strong>Date Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.updateFilter = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('filter', value);
        showLoading();
        window.location = url.toString();
    };
    
    window.showLoading = function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    };
    
    document.getElementById('reportForm').addEventListener('submit', function() {
        showLoading();
    });
    
    window.addEventListener('beforeprint', function() {
        document.querySelector('.printable-section').style.display = 'block';
    });
    
    window.addEventListener('afterprint', function() {
        document.querySelector('.printable-section').style.display = 'none';
    });
});
</script>
@endsection