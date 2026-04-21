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
        }
        .no-print, .stats-card, .chart-container { display: none !important; }
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-size: 10pt !important;
        }
        .table th, .table td {
            border: 1px solid #ddd !important;
            padding: 5px !important;
        }
        .badge {
            background-color: transparent !important;
            color: #000 !important;
            font-weight: normal !important;
            padding: 0 !important;
        }
        .card { border: none !important; box-shadow: none !important; margin: 0 !important; }
        .card-header, .card-body { padding: 0 !important; }
        .print-header { text-align: center; margin-bottom: 20px; }
        .company-name {
            font-size: 24px; font-weight: 700; margin-bottom: 5px;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .report-title { font-size: 20px; font-weight: 600; margin-bottom: 5px; }
        .report-period { font-size: 16px; margin-bottom: 15px; }
        .print-footer {
            margin-top: 30px; page-break-inside: avoid;
            border-top: 1px solid #ddd; padding-top: 10px; font-size: 9pt;
        }
        a { text-decoration: none !important; color: #000 !important; }
        .print-table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .print-table th, .print-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .print-table th {
            background-color: #f8f8f8;
            font-weight: bold;
        }
        .print-table tr:nth-child(even) { background-color: #f2f2f2; }
    }
    .loading-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(255,255,255,0.8); z-index: 9999;
        display: flex; align-items: center; justify-content: center; flex-direction: column;
    }
    .spinner {
        width: 50px; height: 50px;
        border: 4px solid var(--primary-color);
        border-radius: 50%; border-top: 4px solid #f3f3f3;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .btn-group-report { margin-bottom: 15px; }
    .btn-report { border-radius: 20px; padding: 8px 16px; margin-right: 5px; font-weight: 600; transition: all 0.2s; }
    .btn-report:hover { transform: translateY(-2px); }
    .btn-report.active { box-shadow: 0 0 0 2px white, 0 0 0 4px var(--primary-color); }
    .delete-btn { color: #dc3545 !important; font-weight: 600; }
    .delete-btn i { margin-right: 5px; }
    .per-page-selector { display: inline-flex; align-items: center; }
    .per-page-selector .btn { border-radius: 0; padding: 0.25rem 0.5rem; }
    .per-page-selector .btn:first-child { border-top-left-radius: 0.25rem; border-bottom-left-radius: 0.25rem; }
    .per-page-selector .btn:last-child { border-top-right-radius: 0.25rem; border-bottom-right-radius: 0.25rem; }
    .printable-section { display: none; }
    /* Stats icon, watermark style for all cards */
    .stats-icon {
        position: absolute;
        top: 1rem;
        right: 1.5rem;
        font-size: 2.5rem;
        opacity: 0.15;
        z-index: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
@endsection

@section('content')
<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay no-print" style="display: none;">
    <div class="spinner mb-3"></div>
    <h5>Generating Report...</h5>
</div>

<div class="container py-4">
    <!-- Page Header -->
    @component('components.page-header', [
        'icon' => 'graph-up',
        'title' => 'Sales Report',
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
                        <a class="dropdown-item" href="{{ route('reports.sales.export', array_merge(request()->query(), ['format' => 'excel'])) }}" 
                           onclick="showLoading()">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('reports.sales.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                           onclick="showLoading()">
                            <i class="bi bi-file-earmark-text me-2"></i>Export to CSV
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('reports.sales.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                           onclick="showLoading()">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF
                        </a>
                    </li>
                </ul>
            </div>
        @endslot
    @endcomponent
    
    <!-- Report filters and controls - no-print -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-8">
                    <h5 class="card-title mb-3">Report Period</h5>
                    <div class="btn-group-report mb-3">
                        <a href="{{ route('reports.sales', ['period' => 'daily', 'granularity' => request('granularity', 'daily')]) }}" 
                           class="btn btn-report {{ $reportPeriod == 'daily' ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-day me-1"></i> Daily
                        </a>
                        <a href="{{ route('reports.sales', ['period' => 'weekly', 'granularity' => request('granularity', 'daily')]) }}" 
                           class="btn btn-report {{ $reportPeriod == 'weekly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-week me-1"></i> Weekly
                        </a>
                        <a href="{{ route('reports.sales', ['period' => 'monthly', 'granularity' => request('granularity', 'daily')]) }}" 
                           class="btn btn-report {{ $reportPeriod == 'monthly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-month me-1"></i> Monthly
                        </a>
                        <a href="{{ route('reports.sales', ['period' => 'custom', 'granularity' => request('granularity', 'daily')]) }}" 
                           class="btn btn-report {{ $reportPeriod == 'custom' ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-range me-1"></i> Custom Range
                        </a>
                    </div>
                    
                    <form id="reportForm" action="{{ route('reports.sales') }}" method="GET" class="row g-3 align-items-end">
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
                        <label for="filter" class="form-label">Filter By</label>
                        <select id="filterSelect" name="filter" class="form-select" onchange="updateFilter(this.value)">
                            <option value="all" {{ request('filter') == 'all' ? 'selected' : '' }}>All Orders</option>
                            <option value="paid" {{ request('filter') == 'paid' ? 'selected' : '' }}>Paid Orders</option>
                            <option value="unpaid" {{ request('filter') == 'unpaid' ? 'selected' : '' }}>Unpaid Orders</option>
                            <option value="delivery" {{ request('filter') == 'delivery' ? 'selected' : '' }}>Delivery Orders</option>
                            <option value="pickup" {{ request('filter') == 'pickup' ? 'selected' : '' }}>Pick-up Orders</option>
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Stats - no-print -->
    <div class="mb-4 no-print">
        <div class="row g-4">
            <div class="col-md-3">
                @component('components.dashboard-card', [
                    'icon' => 'cash',
                    'color' => 'primary',
                    'title' => 'Total Sales',
                    'value' => '₱' . number_format($totalSales, 2),
                    'subtitle' => $totalOrders . ' orders'
                ])
                @endcomponent
            </div>
            <div class="col-md-3">
                @component('components.dashboard-card', [
                    'icon' => 'check-circle',
                    'color' => 'success',
                    'title' => 'Paid Orders',
                    'value' => '₱' . number_format($paidSales, 2),
                    'subtitle' => $paidOrders . ' orders'
                ])
                @endcomponent
            </div>
            <div class="col-md-3">
                @component('components.dashboard-card', [
                    'icon' => 'clock-history',
                    'color' => 'warning',
                    'title' => 'Unpaid Orders',
                    'value' => '₱' . number_format($unpaidSales, 2),
                    'subtitle' => $unpaidOrders . ' orders'
                ])
                @endcomponent
            </div>
            <div class="col-md-3">
                @component('components.dashboard-card', [
                    'icon' => 'droplet',
                    'color' => 'info',
                    'title' => 'Water Sold',
                    'value' => $totalQuantity,
                    'subtitle' => 'containers'
                ])
                @endcomponent
            </div>
        </div>
    </div>
    
    <!-- Sales Chart - no-print -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Sales Trends</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" style="position: relative; height:400px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Sales Details Table -->
    @component('components.datatable', [
        'header' => 'Sales Details',
        'headerActions' => '<span class="text-muted">' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y') . '</span>'
    ])
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="no-print">Type</th>
                    <th>Quantity</th>
                    <th>Type</th>
                    <th>Payment</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center no-print">Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->created_at->format('M d, Y') }}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle bg-primary me-2 no-print">
                                {{ substr($order->customer->name, 0, 1) }}
                            </div>
                            <div>{{ $order->customer->name }}</div>
                        </div>
                    </td>
                    <td class="no-print">
                        @if($order->is_delivery)
                        <span class="badge bg-info">Delivery</span>
                        @else
                        <span class="badge bg-secondary">Pick-up</span>
                        @endif
                    </td>
                    <td>{{ $order->quantity }}</td>
                    <td>{{ $order->is_delivery ? 'Delivery' : 'Pick-up' }}</td>
                    <td>{{ ucfirst($order->payment_status) }}</td>
                    <td class="text-end fw-bold">₱{{ number_format($order->total_amount, 2) }}</td>
                    <td class="text-center no-print">
                        <span class="badge {{ $order->payment_status == 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </td>
                    <td class="text-end no-print">
                        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-3">No sales data found for the selected period</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td></td>
                    <td colspan="2">Total: {{ $totalOrders }} orders</td>
                    <td class="no-print"></td>
                    <td>{{ $totalQuantity }} units</td>
                    <td colspan="2"></td>
                    <td class="text-end">₱{{ number_format($totalSales, 2) }}</td>
                    <td class="no-print"></td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
        </table>
        
        @if($orders->hasPages())
        @slot('footer')
            <div class="d-flex justify-content-between align-items-center">
                <div class="per-page-selector">
                    <span class="me-2">Show:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="{{ route('reports.sales', array_merge(request()->except('per_page', 'page'), ['per_page' => 10])) }}" 
                           class="btn {{ $perPage == 10 ? 'btn-primary' : 'btn-outline-secondary' }}">10</a>
                        <a href="{{ route('reports.sales', array_merge(request()->except('per_page', 'page'), ['per_page' => 20])) }}" 
                           class="btn {{ $perPage == 20 ? 'btn-primary' : 'btn-outline-secondary' }}">20</a>
                        <a href="{{ route('reports.sales', array_merge(request()->except('per_page', 'page'), ['per_page' => 50])) }}" 
                           class="btn {{ $perPage == 50 ? 'btn-primary' : 'btn-outline-secondary' }}">50</a>
                        <a href="{{ route('reports.sales', array_merge(request()->except('per_page', 'page'), ['per_page' => 100])) }}" 
                           class="btn {{ $perPage == 100 ? 'btn-primary' : 'btn-outline-secondary' }}">100</a>
                    </div>
                </div>
                <div>
                    {{ $orders->withQueryString()->links() }}
                </div>
            </div>
        @endslot
        @endif
    @endcomponent
    
    <!-- Printable Section - Hidden until printing -->
    <div class="printable-section">
        <div class="print-header">
            <div class="text-center mb-4">
                <div class="company-name">MI-GAIL WATER</div>
                <div class="report-title">SALES REPORT</div>
                <div class="report-period">{{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}</div>
            </div>
            
            <div class="row mb-4">
                <div class="col-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th class="text-end">Total Sales:</th>
                            <td>₱{{ number_format($totalSales, 2) }}</td>
                        </tr>
                        <tr>
                            <th class="text-end">Total Orders:</th>
                            <td>{{ $totalOrders }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th class="text-end">Paid Orders:</th>
                            <td>{{ $paidOrders }}</td>
                        </tr>
                        <tr>
                            <th class="text-end">Unpaid Orders:</th>
                            <td>{{ $unpaidOrders }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th class="text-end">Water Sold:</th>
                            <td>{{ $totalQuantity }} containers</td>
                        </tr>
                    </table>
                </div>
                <div class="col-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th class="text-end">Report Period:</th>
                            <td>{{ ucfirst($granularity) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Sales Data Table for Print -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Quantity</th>
                    <th>Type</th>
                    <th>Payment</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->created_at->format('M d, Y') }}</td>
                    <td>{{ $order->customer->name }}</td>
                    <td>{{ $order->quantity }}</td>
                    <td>{{ $order->is_delivery ? 'Delivery' : 'Pick-up' }}</td>
                    <td>{{ ucfirst($order->payment_status) }}</td>
                    <td class="text-end">₱{{ number_format($order->total_amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">No sales data found for the selected period</td>
                </tr>
                @endforelse
                <tr>
                    <td colspan="3"><strong>Total: {{ $totalOrders }} orders</strong></td>
                    <td><strong>{{ $totalQuantity }} units</strong></td>
                    <td colspan="2"></td>
                    <td class="text-end"><strong>₱{{ number_format($totalSales, 2) }}</strong></td>
                </tr>
            </tbody>
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
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesData = @json($chartData);

    const datasets = [
        {
            label: 'Sales (₱)',
            data: salesData.sales,
            borderColor: '#0891b2',
            backgroundColor: 'rgba(8, 145, 178, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#0891b2',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHitRadius: 10
        },
        {
            label: 'Quantity',
            data: salesData.quantities,
            borderColor: '#f59e0b',
            borderWidth: 2,
            borderDash: [5, 5],
            fill: false,
            tension: 0.3,
            yAxisID: 'y1',
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#f59e0b',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHitRadius: 10
        }
    ];

    if (salesData.salesTrend) {
        datasets.push({
            label: 'Sales Trend',
            data: salesData.salesTrend,
            borderColor: 'rgba(8, 145, 178, 0.6)',
            backgroundColor: 'transparent',
            borderWidth: 3,
            fill: false,
            tension: 0.4,
            borderDash: [],
            pointRadius: 0,
            pointHoverRadius: 0
        });
    }

    if (salesData.quantityTrend) {
        datasets.push({
            label: 'Quantity Trend',
            data: salesData.quantityTrend,
            borderColor: 'rgba(245, 158, 11, 0.6)',
            backgroundColor: 'transparent',
            borderWidth: 3,
            fill: false,
            tension: 0.4,
            borderDash: [],
            pointRadius: 0,
            pointHoverRadius: 0,
            yAxisID: 'y1'
        });
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Sales Amount (₱)',
                        font: { weight: 'bold' }
                    },
                    ticks: {
                        callback: function(value) { return '₱' + value.toLocaleString(); },
                        precision: 0
                    },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Quantity',
                        font: { weight: 'bold' }
                    },
                    grid: {
                        drawOnChartArea: false,
                        color: 'rgba(245, 158, 11, 0.1)'
                    },
                    ticks: { precision: 0 }
                },
                x: {
                    title: {
                        display: true,
                        text: '{{ $granularity == "daily" ? "Date" : ($granularity == "weekly" ? "Week" : "Month") }}',
                        font: { weight: 'bold' }
                    },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 10 }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#333',
                    bodyColor: '#666',
                    borderColor: '#ddd',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 5,
                    cornerRadius: 4,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.dataset.label === 'Sales (₱)' || context.dataset.label === 'Sales Trend') {
                                label += '₱' + context.parsed.y.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            } else { label += context.parsed.y; }
                            return label;
                        }
                    }
                }
            }
        }
    });

    window.updateFilter = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('filter', value);
        showLoading();
        window.location = url.toString();
    };

    window.updateGranularity = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('granularity', value);
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