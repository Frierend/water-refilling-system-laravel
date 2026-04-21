@extends('layouts.app')

@section('styles')
<style>
    /* Print-specific styles */
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
        
        /* Hide everything by default */
        body * {
            visibility: hidden;
        }
        
        /* Show only the printable section */
        .printable-section, .printable-section * {
            visibility: visible;
        }
        
        /* Position the printable section at the top of the page */
        .printable-section {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Hide all non-printable elements */
        .no-print, .stats-card {
            display: none !important;
        }
        
        /* Enhanced table styling */
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
        
        /* Replace badge styling with plain text to save ink */
        .badge {
            background-color: transparent !important;
            color: #000 !important;
            font-weight: normal !important;
            padding: 0 !important;
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
            margin-bottom: 5px; 
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
            margin-top: 30px;
            page-break-inside: avoid;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            font-size: 9pt;
            color: #606f7b;
        }
    }
    
    /* Loading overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(255, 255, 255, 0.9);
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
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
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
    'icon' => 'truck',
    'title' => 'Delivery Report',
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
                    <a class="dropdown-item" href="{{ route('reports.delivery.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('reports.delivery.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-text me-2"></i>Export to CSV
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('reports.delivery.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF
                    </a>
                </li>
            </ul>
        </div>
    @endslot
@endcomponent

<!-- Report Type Buttons -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8">
                <h5 class="card-title mb-3">Report Period</h5>
                <div class="btn-group-report mb-3">
                    <a href="{{ route('reports.delivery', ['period' => 'daily', 'status' => request('status', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'daily' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-day me-1"></i> Daily
                    </a>
                    <a href="{{ route('reports.delivery', ['period' => 'weekly', 'status' => request('status', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'weekly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-week me-1"></i> Weekly
                    </a>
                    <a href="{{ route('reports.delivery', ['period' => 'monthly', 'status' => request('status', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'monthly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-month me-1"></i> Monthly
                    </a>
                    <a href="{{ route('reports.delivery', ['period' => 'custom', 'status' => request('status', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'custom' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-range me-1"></i> Custom Range
                    </a>
                </div>
                
                <form id="reportForm" action="{{ route('reports.delivery') }}" method="GET" class="row g-3 align-items-end">
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
                    <label for="status" class="form-label">Delivery Status</label>
                    <select id="statusSelect" name="status" class="form-select" onchange="updateStatus(this.value)">
                        <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All Deliveries</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="driver" class="form-label">Delivery Driver</label>
                    <select id="driverSelect" name="driver" class="form-select" onchange="updateDriver(this.value)">
                        <option value="all" {{ request('driver', 'all') == 'all' ? 'selected' : '' }}>All Drivers</option>
                        @foreach($drivers ?? [] as $driver)
                        <option value="{{ $driver->id }}" {{ request('driver') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="granularity" class="form-label">Report Granularity</label>
                    <select id="granularitySelect" name="granularity" class="form-select" onchange="updateGranularity(this.value)">
                        <option value="daily" {{ $granularity == 'daily' ? 'selected' : '' }}>Daily Breakdown</option>
                        <option value="weekly" {{ $granularity == 'weekly' ? 'selected' : '' }}>Weekly Summary</option>
                        <option value="monthly" {{ $granularity == 'monthly' ? 'selected' : '' }}>Monthly Summary</option>
                    </select>
                </div>

                @php
                    $activeDriverName = 'All Drivers';
                    if (($filterDriver ?? 'all') !== 'all') {
                        $activeDriverName = optional(($drivers ?? collect())->firstWhere('id', (int) $filterDriver))->name ?? 'All Drivers';
                    }
                @endphp
                <div class="alert alert-light border py-2 px-3 small mb-0">
                    Active filters:
                    <strong>Status: {{ ucfirst($filterStatus ?? 'all') }}</strong>,
                    <strong>Driver: {{ $activeDriverName }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4 no-print">
    <div class="col-md-3">
        @component('components.dashboard-card', [
            'icon' => 'truck',
            'color' => 'primary',
            'title' => 'Total Deliveries',
            'value' => $totalDeliveries,
            'subtitle' => '₱' . number_format($totalDeliveryAmount, 2) . ' revenue'
        ])
        @endcomponent
    </div>
    <div class="col-md-3">
        @component('components.dashboard-card', [
            'icon' => 'check-circle',
            'color' => 'success',
            'title' => 'Completed',
            'value' => $completedDeliveries,
            'subtitle' => $completedDeliveries > 0 ? number_format(($completedDeliveries / $totalDeliveries) * 100, 1) . '% completion rate' : '0% completion rate'
        ])
        @endcomponent
    </div>
    <div class="col-md-3">
        @component('components.dashboard-card', [
            'icon' => 'clock-history',
            'color' => 'warning',
            'title' => 'Pending',
            'value' => $pendingDeliveries,
            'subtitle' => $pendingDeliveries > 0 ? number_format(($pendingDeliveries / $totalDeliveries) * 100, 1) . '% of total' : '0% of total'
        ])
        @endcomponent
    </div>
    <div class="col-md-3">
        @component('components.dashboard-card', [
            'icon' => 'droplet',
            'color' => 'info',
            'title' => 'Water Delivered',
            'value' => $totalQuantity,
            'subtitle' => 'containers'
        ])
        @endcomponent
    </div>
</div>

<!-- Delivery Personnel Performance -->
@component('components.datatable', ['header' => 'Delivery Personnel Performance'])
    <div class="card-body">
        <div class="row">
            @forelse($personnelStats as $personnel)
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-circle bg-primary me-3">
                                {{ substr($personnel->name, 0, 1) }}
                            </div>
                            <div>
                                <h6 class="mb-0">{{ $personnel->name }}</h6>
                                <small class="text-muted">Delivery Personnel</small>
                            </div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fs-4 fw-bold">{{ $personnel->total_deliveries }}</div>
                                <small class="text-muted">Deliveries</small>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold">{{ $personnel->completed_deliveries }}</div>
                                <small class="text-muted">Completed</small>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold">{{ $personnel->total_quantity }}</div>
                                <small class="text-muted">Containers</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1 small">
                                <span>Completion Rate</span>
                                <span>{{ $personnel->total_deliveries > 0 ? number_format(($personnel->completed_deliveries / $personnel->total_deliveries) * 100, 1) : 0 }}%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                    style="width: {{ $personnel->total_deliveries > 0 ? ($personnel->completed_deliveries / $personnel->total_deliveries) * 100 : 0 }}%" 
                                    aria-valuenow="{{ $personnel->total_deliveries > 0 ? ($personnel->completed_deliveries / $personnel->total_deliveries) * 100 : 0 }}" 
                                    aria-valuemin="0" 
                                    aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> No delivery personnel data available for the selected period.
                </div>
            </div>
            @endforelse
        </div>
    </div>
@endcomponent

<!-- Delivery List Table -->
@component('components.datatable', ['header' => 'Delivery Details'])
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Delivery Date</th>
                <th>Personnel</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Payment</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deliveries as $delivery)
            <tr>
                <td>#{{ $delivery->id }}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary me-2">
                            {{ substr($delivery->customer->name, 0, 1) }}
                        </div>
                        <div>{{ $delivery->customer->name }}</div>
                    </div>
                </td>
                <td>
                    @if($delivery->delivery_date)
                        {{ Carbon\Carbon::parse($delivery->delivery_date)->format('M d, Y') }}
                    @else
                        <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                </td>
                <td>{{ $delivery->deliveryPerson->name ?? 'Not Assigned' }}</td>
                <td>{{ $delivery->quantity }}</td>
                <td>
                    <span class="badge {{ $delivery->order_status == 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">
                        {{ ucfirst($delivery->order_status) }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $delivery->payment_status == 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ ucfirst($delivery->payment_status) }}
                    </span>
                </td>
                <td class="text-end fw-semibold">₱{{ number_format($delivery->total_amount, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="d-flex flex-column align-items-center">
                        <i class="bi bi-truck text-muted mb-2" style="font-size: 3rem;"></i>
                        <p class="text-muted mb-0">No deliveries found for the selected period</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td colspan="4">Total: {{ $totalDeliveries }} deliveries</td>
                <td>{{ $totalQuantity }} items</td>
                <td colspan="2"></td>
                <td class="text-end">₱{{ number_format($totalDeliveryAmount, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    
    @if($deliveries->hasPages())
    @slot('footer')
        <div class="d-flex justify-content-between align-items-center">
            <div class="per-page-selector">
                <span class="me-2">Show:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ route('reports.delivery', array_merge(request()->except('per_page', 'page'), ['per_page' => 10])) }}" 
                       class="btn {{ $perPage == 10 ? 'btn-primary' : 'btn-outline-secondary' }}">10</a>
                    <a href="{{ route('reports.delivery', array_merge(request()->except('per_page', 'page'), ['per_page' => 20])) }}" 
                       class="btn {{ $perPage == 20 ? 'btn-primary' : 'btn-outline-secondary' }}">20</a>
                    <a href="{{ route('reports.delivery', array_merge(request()->except('per_page', 'page'), ['per_page' => 50])) }}" 
                       class="btn {{ $perPage == 50 ? 'btn-primary' : 'btn-outline-secondary' }}">50</a>
                    <a href="{{ route('reports.delivery', array_merge(request()->except('per_page', 'page'), ['per_page' => 100])) }}" 
                       class="btn {{ $perPage == 100 ? 'btn-primary' : 'btn-outline-secondary' }}">100</a>
                </div>
            </div>
            <div>
                {{ $deliveries->withQueryString()->links() }}
            </div>
        </div>
    @endslot
    @endif
@endcomponent

<!-- Printable Section -->
<div class="printable-section" style="display: none;">
    <div class="print-header">
        <div class="company-name">MI-GAIL WATER</div>
        <div class="report-title">DELIVERY REPORT</div>
        <div class="report-period">{{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}</div>
    </div>
    
    <div class="row mb-4">
        <div class="col-6">
            <table class="table table-sm table-borderless">
                <tr>
                    <th class="text-end">Total Deliveries:</th>
                    <td>{{ $totalDeliveries }}</td>
                </tr>
                <tr>
                    <th class="text-end">Total Revenue:</th>
                    <td>₱{{ number_format($totalDeliveryAmount, 2) }}</td>
                </tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-sm table-borderless">
                <tr>
                    <th class="text-end">Completed:</th>
                    <td>{{ $completedDeliveries }}</td>
                </tr>
                <tr>
                    <th class="text-end">Pending:</th>
                    <td>{{ $pendingDeliveries }}</td>
                </tr>
            </table>
        </div>
    </div>
    
    <table class="print-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Delivery Date</th>
                <th>Personnel</th>
                <th>Qty</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deliveries as $delivery)
            <tr>
                <td>#{{ $delivery->id }}</td>
                <td>{{ $delivery->customer->name }}</td>
                <td>
                    @if($delivery->delivery_date)
                        {{ Carbon\Carbon::parse($delivery->delivery_date)->format('M d, Y') }}
                    @else
                        Pending
                    @endif
                </td>
                <td>{{ $delivery->deliveryPerson->name ?? 'Not Assigned' }}</td>
                <td>{{ $delivery->quantity }}</td>
                <td>{{ ucfirst($delivery->order_status) }}</td>
                <td>{{ ucfirst($delivery->payment_status) }}</td>
                <td>₱{{ number_format($delivery->total_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"><strong>Total: {{ $totalDeliveries }} deliveries</strong></td>
                <td><strong>{{ $totalQuantity }}</strong></td>
                <td colspan="2"></td>
                <td><strong>₱{{ number_format($totalDeliveryAmount, 2) }}</strong></td>
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
    // Function to update status parameter
    window.updateStatus = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('status', value);
        showLoading();
        window.location = url.toString();
    };
    
    // Function to update driver parameter
    window.updateDriver = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('driver', value);
        showLoading();
        window.location = url.toString();
    };
    
    // Function to update granularity parameter
    window.updateGranularity = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('granularity', value);
        showLoading();
        window.location = url.toString();
    };
    
    // Show loading indicator
    window.showLoading = function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    };
    
    // Add loading indicator to the form submit
    document.getElementById('reportForm').addEventListener('submit', function() {
        showLoading();
    });
    
    // Handle print events
    window.addEventListener('beforeprint', function() {
        document.querySelector('.printable-section').style.display = 'block';
    });

    window.addEventListener('afterprint', function() {
        document.querySelector('.printable-section').style.display = 'none';
    });
});
</script>
@endsection
