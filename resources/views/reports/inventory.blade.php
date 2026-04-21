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
        }
        
        body * { visibility: hidden; }
        .printable-section, .printable-section * { visibility: visible; }
        .printable-section {
            position: absolute; left: 0; top: 0; width: 100%; padding: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Better font */
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
            box-shadow: 0 0 10px rgba(0,0,0,0.1); /* Subtle shadow */
        }
        .print-table th {
            background-color: #f0f7fa !important; /* Soft blue background */
            color: #2c3e50; /* Darker text for contrast */
            font-weight: 600;
            border: 1px solid #c8d6e5;
            padding: 10px;
            text-align: left;
            text-transform: uppercase; /* All caps headers */
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
            background-color: #f8fafc !important; /* Lighter alternating rows */
        }
        
        /* Card styling */
        .card { 
            border: none !important; 
            box-shadow: none !important; 
            margin: 0 !important; 
        }
        .card-header, .card-body { padding: 0 !important; }
        
        /* Colors and badges */
        .badge { 
            background-color: transparent !important; 
            color: #000 !important; 
            font-weight: normal !important; 
            padding: 0 !important; 
        }
        .text-success { 
            color: #38c172 !important; 
            font-weight: 600;
        }
        .text-danger { 
            color: #e3342f !important; 
            font-weight: 600;
        }
        
        /* Headers */
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
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
            color: #3490dc;
            letter-spacing: 0.5px;
        }
        
        /* Footer styling */
        .print-footer {
            margin-top: 35px; 
            page-break-inside: avoid;
            border-top: 1px solid #ddd; 
            padding-top: 15px; 
            font-size: 9pt;
            color: #606f7b;
        }
        
        /* Link styling */
        a { text-decoration: none !important; color: #3490dc !important; }
        
        /* Summary display */
        .inventory-summary, .transaction-summary {
            margin-bottom: 25px;
            background-color: #f8fafc;
            border-radius: 5px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }
        .summary-item {
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .d-flex {
            display: flex;
        }
        .justify-content-between {
            justify-content: space-between;
        }
        .mb-4 {
            margin-bottom: 20px;
        }
        
        /* Detail items */
        .detail-item {
            display: inline-block;
            margin-right: 20px;
            background-color: #f8fafc;
            padding: 5px 10px;
            border-radius: 4px;
            border-left: 3px solid #3490dc;
        }
        
        /* Value highlights */
        .highlight-value {
            font-weight: 600;
            color: #2c3e50;
        }
    }
    
    /* Regular screen styling - Enhanced */
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
    'icon' => 'box-seam',
    'title' => 'Inventory Report',
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
                    <a class="dropdown-item" href="{{ route('reports.inventory.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('reports.inventory.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                       onclick="showLoading()">
                        <i class="bi bi-file-earmark-text me-2"></i>Export to CSV
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('reports.inventory.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
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
                    <a href="{{ route('reports.inventory', ['period' => 'daily', 'item_type' => request('item_type', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'daily' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-day me-1"></i> Daily
                    </a>
                    <a href="{{ route('reports.inventory', ['period' => 'weekly', 'item_type' => request('item_type', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'weekly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-week me-1"></i> Weekly
                    </a>
                    <a href="{{ route('reports.inventory', ['period' => 'monthly', 'item_type' => request('item_type', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'monthly' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-month me-1"></i> Monthly
                    </a>
                    <a href="{{ route('reports.inventory', ['period' => 'custom', 'item_type' => request('item_type', 'all')]) }}" 
                       class="btn btn-report {{ $reportPeriod == 'custom' ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="bi bi-calendar-range me-1"></i> Custom Range
                    </a>
                </div>
                <form id="reportForm" action="{{ route('reports.inventory') }}" method="GET" class="row g-3 align-items-end">
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
                    <label for="item_type" class="form-label">Item Type</label>
                    <select id="itemTypeSelect" name="item_type" class="form-select" onchange="updateItemType(this.value)">
                        <option value="all" {{ request('item_type', 'all') == 'all' ? 'selected' : '' }}>All Items</option>
                        @foreach($currentInventory as $item)
                        <option value="{{ $item->type }}" {{ request('item_type') == $item->type ? 'selected' : '' }}>
                            {{ ucfirst($item->type) }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Current Inventory Levels Table -->
@component('components.datatable', ['header' => 'Current Inventory Levels'])
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Item Type</th>
                <th>Current Quantity</th>
                <th>Incoming (Period)</th>
                <th>Outgoing (Period)</th>
                <th>Last Updated</th>
            </tr>
        </thead>
        <tbody>
            @forelse($currentInventory as $item)
            <tr>
                <td>{{ ucfirst($item->type) }}</td>
                <td class="fw-semibold">{{ $item->quantity }}</td>
                <td class="text-success">+{{ $itemStats[$item->type]['incoming'] }}</td>
                <td class="text-danger">-{{ $itemStats[$item->type]['outgoing'] }}</td>
                <td>{{ $item->updated_at->format('M d, Y h:i A') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-3">No inventory items found</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td>Totals</td>
                <td>{{ $currentInventory->sum('quantity') }}</td>
                <td class="text-success">+{{ $totalIncoming }}</td>
                <td class="text-danger">-{{ $totalOutgoing }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endcomponent

<!-- Inventory Transactions Table -->
@component('components.datatable', ['header' => 'Inventory Transaction History', 'headerActions' => '<span class="text-muted">' . $inventoryLogs->total() . ' transactions</span>'])
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Date & Time</th>
                <th>Item</th>
                <th>Change</th>
                <th>User</th>
                <th>Order #</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inventoryLogs as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                <td>{{ ucfirst($log->inventoryItem->type) }}</td>
                <td class="{{ $log->quantity_change > 0 ? 'text-success fw-semibold' : 'text-danger fw-semibold' }}">
                    {{ $log->quantity_change > 0 ? '+' : '' }}{{ $log->quantity_change }}
                </td>
                <td>{{ $log->user->name ?? 'System' }}</td>
                <td>
                    @if($log->order_id)
                    <a href="{{ route('orders.show', $log->order_id) }}" class="text-decoration-none">
                        #{{ $log->order_id }}
                    </a>
                    @else
                    -
                    @endif
                </td>
                <td>{{ $log->notes ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-3">
                    <div class="d-flex flex-column align-items-center">
                        <i class="bi bi-box-seam text-muted mb-2" style="font-size: 2rem;"></i>
                        <p class="text-muted mb-0">No inventory transactions found for the selected period</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    
    @if($inventoryLogs->hasPages())
    @slot('footer')
        <div class="d-flex justify-content-between align-items-center">
            <div class="per-page-selector">
                <span class="me-2">Show:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ route('reports.inventory', array_merge(request()->except('per_page', 'page'), ['per_page' => 10])) }}" 
                       class="btn {{ $perPage == 10 ? 'btn-primary' : 'btn-outline-secondary' }}">10</a>
                    <a href="{{ route('reports.inventory', array_merge(request()->except('per_page', 'page'), ['per_page' => 20])) }}" 
                       class="btn {{ $perPage == 20 ? 'btn-primary' : 'btn-outline-secondary' }}">20</a>
                    <a href="{{ route('reports.inventory', array_merge(request()->except('per_page', 'page'), ['per_page' => 50])) }}" 
                       class="btn {{ $perPage == 50 ? 'btn-primary' : 'btn-outline-secondary' }}">50</a>
                    <a href="{{ route('reports.inventory', array_merge(request()->except('per_page', 'page'), ['per_page' => 100])) }}" 
                       class="btn {{ $perPage == 100 ? 'btn-primary' : 'btn-outline-secondary' }}">100</a>
                </div>
            </div>
            <div>
                {{ $inventoryLogs->withQueryString()->links() }}
            </div>
        </div>
    @endslot
    @endif
@endcomponent

<!-- Printable Section -->
<div class="printable-section">
    <div class="print-header">
        <div class="company-name">MI-GAIL WATER</div>
        <div class="report-title">INVENTORY REPORT</div>
        <div class="report-period">{{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}</div>
    </div>

    <!-- Current Inventory Summary -->
    <div class="inventory-summary mb-4">
        <h4 class="section-title">Current Inventory Levels</h4>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Item Type</th>
                    <th>Current Quantity</th>
                    <th>Incoming</th>
                    <th>Outgoing</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                @foreach($currentInventory as $item)
                <tr>
                    <td><strong>{{ ucfirst($item->type) }}</strong></td>
                    <td>{{ $item->quantity }}</td>
                    <td class="text-success">+{{ $itemStats[$item->type]['incoming'] }}</td>
                    <td class="text-danger">-{{ $itemStats[$item->type]['outgoing'] }}</td>
                    <td>{{ $item->updated_at->format('M d, Y h:i A') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totals</strong></td>
                    <td><strong>{{ $currentInventory->sum('quantity') }}</strong></td>
                    <td class="text-success"><strong>+{{ $totalIncoming }}</strong></td>
                    <td class="text-danger"><strong>-{{ $totalOutgoing }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <!-- Transaction Summary -->
    <div class="transaction-summary mb-4">
        <h4 class="section-title">Transaction Summary</h4>
        <div class="d-flex justify-content-between" style="margin: 0 1rem;">
            <div class="summary-item">
                <strong>Total Transactions:</strong> {{ $totalTransactions }}
            </div>
            <div class="summary-item">
                <strong>Total Incoming:</strong> +{{ $totalIncoming }}
            </div>
            <div class="summary-item">
                <strong>Total Outgoing:</strong> -{{ $totalOutgoing }}
            </div>
            <div class="summary-item">
                <strong>Net Change:</strong> {{ $totalIncoming - $totalOutgoing }}
            </div>
        </div>
    </div>
    
    <!-- Inventory Transaction History -->
    <h4 class="section-title">Inventory Transaction History</h4>
    <table class="print-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date & Time</th>
                <th>Item</th>
                <th>Change</th>
                <th>User</th>
                <th>Order #</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inventoryLogs as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                <td><strong>{{ ucfirst($log->inventoryItem->type) }}</strong></td>
                <td class="{{ $log->quantity_change > 0 ? 'text-success' : 'text-danger' }}">{{ $log->quantity_change > 0 ? '+' : '' }}{{ $log->quantity_change }}</td>
                <td>{{ $log->user->name ?? 'System' }}</td>
                <td>{{ $log->order_id ? "#{$log->order_id}" : '-' }}</td>
                <td>{{ $log->notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="print-footer mt-4">
        <div class="row">
            <div class="col-6">
                <p class="mb-0"><strong>Generated by:</strong> {{ Auth::user()->name ?? 'Administrator' }}</p>
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
    window.updateItemType = function(value) {
        const url = new URL(window.location);
        url.searchParams.set('item_type', value);
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