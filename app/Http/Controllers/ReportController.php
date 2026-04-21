<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        // Only owner/admin can access reports
        $this->middleware('role:owner,admin');
    }
    
    /**
     * Display the sales report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function salesReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'filter' => 'nullable|in:all,paid,unpaid,delivery,pickup',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|in:10,20,50,100',
        ]);

        $reportPeriod = $validated['period'] ?? 'custom';
        $granularity = $validated['granularity'] ?? 'daily';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $filter = $validated['filter'] ?? 'all';
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->startOfMonth(),
            Carbon::now()
        );
        
        // Base query
        $query = Order::whereBetween('created_at', [$startDate, $endDate]);
        $baseQuery = clone $query;
        
        // Apply filter
        if ($filter !== 'all') {
            if ($filter === 'paid') {
                $query->where('payment_status', 'paid');
            } elseif ($filter === 'unpaid') {
                $query->where('payment_status', 'unpaid');
            } elseif ($filter === 'delivery') {
                $query->where('is_delivery', true);
            } elseif ($filter === 'pickup') {
                $query->where('is_delivery', false);
            }
        }
        
        // Calculate totals
        $totalSales = (clone $baseQuery)->sum('total_amount');
        $totalQuantity = (clone $baseQuery)->sum('quantity');
        $totalOrders = (clone $baseQuery)->count();
        
        $paidOrders = (clone $baseQuery)->where('payment_status', 'paid')->count();
        $paidSales = (clone $baseQuery)->where('payment_status', 'paid')->sum('total_amount');
        
        $unpaidOrders = (clone $baseQuery)->where('payment_status', 'unpaid')->count();
        $unpaidSales = (clone $baseQuery)->where('payment_status', 'unpaid')->sum('total_amount');
        
        $deliveryOrders = (clone $baseQuery)->where('is_delivery', true)->count();
        $pickupOrders = (clone $baseQuery)->where('is_delivery', false)->count();
        
        // Get orders with customer info
        $orders = $query->with('customer')->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        
        // Prepare chart data based on granularity
        if ($granularity === 'daily') {
            $salesByPeriod = DB::table('orders')
                ->selectRaw('DATE(created_at) as period, SUM(total_amount) as period_sales, SUM(quantity) as period_quantity')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } elseif ($granularity === 'weekly') {
            $salesByPeriod = DB::table('orders')
                ->selectRaw('YEARWEEK(created_at, 1) as year_week, MIN(DATE(created_at)) as period, SUM(total_amount) as period_sales, SUM(quantity) as period_quantity')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('year_week')
                ->orderBy('year_week')
                ->get();
        } else {
            $salesByPeriod = DB::table('orders')
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as year_month, DATE_FORMAT(created_at, "%b %Y") as period, SUM(total_amount) as period_sales, SUM(quantity) as period_quantity')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('year_month', 'period')
                ->orderBy('year_month')
                ->get();
        }
        
        $chartData = [
            'labels' => $salesByPeriod->pluck('period')->toArray(),
            'sales' => $salesByPeriod->pluck('period_sales')->toArray(),
            'quantities' => $salesByPeriod->pluck('period_quantity')->toArray(),
        ];
        
        return view('reports.sales', compact(
            'orders', 
            'totalSales', 
            'totalQuantity', 
            'totalOrders',
            'paidOrders', 
            'paidSales', 
            'unpaidOrders', 
            'unpaidSales',
            'deliveryOrders',
            'pickupOrders', 
            'chartData',
            'startDate',
            'endDate',
            'reportPeriod',
            'granularity',
            'perPage'
        ));
    }

    /**
     * Display the delivery report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function deliveryReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'status' => 'nullable|in:all,pending,completed,cancelled',
            'driver' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '' || $value === 'all') {
                        return;
                    }

                    if (!ctype_digit((string) $value)) {
                        $fail('The selected driver is invalid.');
                        return;
                    }

                    $driverExists = User::whereKey((int) $value)
                        ->whereIn('role', ['delivery', 'helper'])
                        ->exists();

                    if (!$driverExists) {
                        $fail('The selected driver is invalid.');
                    }
                },
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|in:10,20,50,100',
        ]);

        $reportPeriod = $validated['period'] ?? 'custom';
        $granularity = $validated['granularity'] ?? 'daily';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $filterStatus = $validated['status'] ?? 'all';
        $filterDriver = (string) ($validated['driver'] ?? 'all');
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->startOfMonth(),
            Carbon::now()
        );

        // Base query for deliveries
        $query = Order::where('is_delivery', true)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $baseQuery = clone $query;

        // Apply status filter
        if ($filterStatus !== 'all') {
            $query->where('order_status', $filterStatus);
            $baseQuery->where('order_status', $filterStatus);
        }

        if ($filterDriver !== 'all') {
            $driverId = (int) $filterDriver;
            $query->where('delivery_user_id', $driverId);
            $baseQuery->where('delivery_user_id', $driverId);
        }

        // Get totals
        $totalDeliveries = (clone $baseQuery)->count();
        $totalDeliveryAmount = (clone $baseQuery)->sum('total_amount');
        $completedDeliveries = (clone $baseQuery)->where('order_status', 'completed')->count();
        $pendingDeliveries = (clone $baseQuery)->where('order_status', 'pending')->count();
        $totalQuantity = (clone $baseQuery)->sum('quantity');

        // Get all delivery orders
        $deliveries = $query->with(['customer', 'deliveryPerson'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Get delivery personnel performance stats
        $personnelStats = DB::table('orders')
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(orders.id) as total_deliveries')
            ->selectRaw('SUM(CASE WHEN orders.order_status = "completed" THEN 1 ELSE 0 END) as completed_deliveries')
            ->selectRaw('SUM(orders.quantity) as total_quantity')
            ->join('users', 'orders.delivery_user_id', '=', 'users.id')
            ->where('orders.is_delivery', true)
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('users.id', 'users.name')
            ->having('total_deliveries', '>', 0)
            ->orderByDesc('total_deliveries')
            ->when($filterStatus !== 'all', function ($statsQuery) use ($filterStatus) {
                return $statsQuery->where('orders.order_status', $filterStatus);
            })
            ->when($filterDriver !== 'all', function ($statsQuery) use ($filterDriver) {
                return $statsQuery->where('orders.delivery_user_id', (int) $filterDriver);
            })
            ->get();

        // Get drivers for filtering
        $drivers = User::whereIn('role', ['delivery', 'helper'])
            ->orderBy('name')
            ->get();

        return view('reports.delivery', compact(
            'deliveries',
            'totalDeliveries',
            'totalDeliveryAmount',
            'completedDeliveries',
            'pendingDeliveries',
            'totalQuantity',
            'personnelStats',
            'startDate',
            'endDate',
            'reportPeriod',
            'granularity',
            'perPage',
            'filterStatus',
            'drivers',
            'filterDriver'
        ));
    }

    /**
     * Display the customer report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function customerReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'filter' => 'nullable|in:all,regular,non-regular,top',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|in:10,20,50,100',
        ]);

        $reportPeriod = $validated['period'] ?? 'custom';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $filter = $validated['filter'] ?? 'all';
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->subMonths(3),
            Carbon::now()
        );
        
        // Base query
        $query = Customer::withCount(['orders' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->withSum(['orders' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }], 'total_amount')
            ->with(['orders' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->latest()
                  ->limit(1);
            }]);
        
        // Apply filter
        if ($filter !== 'all') {
            if ($filter === 'regular') {
                $query->where('is_regular', true);
            } elseif ($filter === 'non-regular') {
                $query->where('is_regular', false);
            } elseif ($filter === 'top') {
                $query->has('orders', '>=', 1)
                      ->orderByDesc('orders_sum_total_amount');
            }
        } else {
            $query->orderByDesc('orders_sum_total_amount');
        }
        
        $customers = $query->paginate($perPage)->withQueryString();
        
        // Format customer data
        foreach ($customers as $customer) {
            $customer->total_spent = $customer->orders_sum_total_amount ?? 0;
            $customer->last_order = $customer->orders->isNotEmpty() ? $customer->orders->first()->created_at : null;
            unset($customer->orders);
        }
        
        // Calculate totals
        $totalCustomers = Customer::count();
        $regularCustomers = Customer::where('is_regular', true)->count();
        
        $ordersInPeriod = Order::whereBetween('created_at', [$startDate, $endDate]);
        $totalOrders = $ordersInPeriod->count();
        $totalSales = $ordersInPeriod->sum('total_amount');
        $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        
        // Count customers with more than one order (repeat customers)
        $repeatCustomers = DB::table('orders')
            ->select('customer_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        
        // Prepare chart data for top customers
        $topCustomersData = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->selectRaw('customers.name, SUM(orders.total_amount) as total_revenue, COUNT(orders.id) as order_count')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('customers.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
            
        $chartData = [
            'names' => $topCustomersData->pluck('name')->toArray(),
            'revenues' => $topCustomersData->pluck('total_revenue')->toArray(),
            'orders' => $topCustomersData->pluck('order_count')->toArray(),
        ];
        
        return view('reports.customer', compact(
            'customers',
            'totalCustomers',
            'regularCustomers',
            'totalOrders',
            'totalSales',
            'avgOrderValue',
            'repeatCustomers',
            'chartData',
            'startDate',
            'endDate',
            'reportPeriod',
            'perPage'
        ));
    }

    /**
     * Display the inventory report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function inventoryReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'item_type' => 'nullable|in:all,water,container,empty,cap,seal,other',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|in:10,20,50,100',
        ]);

        $reportPeriod = $validated['period'] ?? 'custom';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $itemType = $validated['item_type'] ?? 'all';
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->subMonth(),
            Carbon::now()
        );
        
        // Get current inventory levels
        $currentInventory = InventoryItem::all();
        
        // Get inventory logs for the period
        $inventoryLogsQuery = InventoryTransaction::whereBetween('created_at', [$startDate, $endDate]);
        
        // Apply item type filter if provided
        if ($itemType !== 'all') {
            $inventoryLogsQuery->whereHas('inventoryItem', function ($q) use ($itemType) {
                $q->where('type', $itemType);
            });
        }
        
        $inventoryLogs = $inventoryLogsQuery->with(['inventoryItem', 'user', 'order'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        // Calculate inventory statistics by item type
        $itemStats = [];
        foreach ($currentInventory as $item) {
            $itemStats[$item->type] = [
                'current' => $item->quantity,
                'incoming' => InventoryTransaction::whereBetween('created_at', [$startDate, $endDate])
                    ->where('inventory_item_id', $item->id)
                    ->where('quantity_change', '>', 0)
                    ->sum('quantity_change'),
                'outgoing' => InventoryTransaction::whereBetween('created_at', [$startDate, $endDate])
                    ->where('inventory_item_id', $item->id)
                    ->where('quantity_change', '<', 0)
                    ->sum(DB::raw('ABS(quantity_change)')),
                'last_updated' => $item->updated_at
            ];
        }
        
        // Calculate total transactions
        $totalTransactions = InventoryTransaction::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalIncoming = InventoryTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('quantity_change', '>', 0)
            ->sum('quantity_change');
        $totalOutgoing = InventoryTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('quantity_change', '<', 0)
            ->sum(DB::raw('ABS(quantity_change)'));
        
        return view('reports.inventory', compact(
            'currentInventory',
            'inventoryLogs',
            'totalTransactions',
            'totalIncoming',
            'totalOutgoing',
            'itemStats',
            'startDate',
            'endDate',
            'reportPeriod',
            'perPage'
        ));
    }

    /**
     * Resolve a validated report date range.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function resolveDateRange(array $validated, Carbon $defaultStart, Carbon $defaultEnd): array
    {
        $period = $validated['period'] ?? 'custom';

        if ($period === 'daily') {
            return [Carbon::today(), Carbon::today()->endOfDay()];
        }

        if ($period === 'weekly') {
            return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()->endOfDay()];
        }

        if ($period === 'monthly') {
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()->endOfDay()];
        }

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : $defaultStart->copy()->startOfDay();
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : $defaultEnd->copy()->endOfDay();

        return [$startDate, $endDate];
    }

    /**
     * Export sales report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportSalesReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'filter' => 'nullable|in:all,paid,unpaid,delivery,pickup',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,pdf,excel',
        ]);

        $reportPeriod = $validated['period'] ?? 'custom';
        $filter = $validated['filter'] ?? 'all';
        $format = $this->resolveExportFormat($validated['format'] ?? 'csv');
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->startOfMonth(),
            Carbon::now()
        );

        $query = Order::with('customer')->whereBetween('created_at', [$startDate, $endDate]);

        if ($filter !== 'all') {
            if ($filter === 'paid') {
                $query->where('payment_status', 'paid');
            } elseif ($filter === 'unpaid') {
                $query->where('payment_status', 'unpaid');
            } elseif ($filter === 'delivery') {
                $query->where('is_delivery', true);
            } elseif ($filter === 'pickup') {
                $query->where('is_delivery', false);
            }
        }

        $orders = $query->orderByDesc('created_at')->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.exports.pdf', [
                'reportType' => 'Sales',
                'data' => $orders,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalAmount' => $orders->sum('total_amount'),
                'totalQuantity' => $orders->sum('quantity'),
                'reportPeriod' => $reportPeriod,
            ]);

            return $pdf->download('sales-report-' . now()->format('Ymd-His') . '.pdf');
        }

        $rows = $orders->map(function ($order) {
            return [
                $order->id,
                optional($order->created_at)->format('Y-m-d H:i:s'),
                optional($order->customer)->name ?? 'N/A',
                $order->quantity,
                $order->is_delivery ? 'Delivery' : 'Pick-up',
                $order->order_status,
                $order->payment_status,
                $order->payment_method,
                number_format((float) $order->total_amount, 2, '.', ''),
            ];
        })->all();

        return $this->streamCsvDownload(
            'sales-report-' . now()->format('Ymd-His') . '.csv',
            ['Order ID', 'Date', 'Customer', 'Quantity', 'Type', 'Order Status', 'Payment Status', 'Payment Method', 'Total Amount'],
            $rows
        );
    }

    /**
     * Export delivery report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportDeliveryReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'status' => 'nullable|in:all,pending,completed,cancelled',
            'driver' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '' || $value === 'all') {
                        return;
                    }

                    if (!ctype_digit((string) $value)) {
                        $fail('The selected driver is invalid.');
                        return;
                    }

                    $driverExists = User::whereKey((int) $value)
                        ->whereIn('role', ['delivery', 'helper'])
                        ->exists();

                    if (!$driverExists) {
                        $fail('The selected driver is invalid.');
                    }
                },
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,pdf,excel',
        ]);

        $filterStatus = $validated['status'] ?? 'all';
        $filterDriver = (string) ($validated['driver'] ?? 'all');
        $format = $this->resolveExportFormat($validated['format'] ?? 'csv');
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->startOfMonth(),
            Carbon::now()
        );

        $query = Order::with(['customer', 'deliveryPerson'])
            ->where('is_delivery', true)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($filterStatus !== 'all') {
            $query->where('order_status', $filterStatus);
        }

        if ($filterDriver !== 'all') {
            $query->where('delivery_user_id', (int) $filterDriver);
        }

        $deliveries = $query->orderByDesc('created_at')->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.exports.pdf', [
                'reportType' => 'Delivery',
                'data' => $deliveries,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalAmount' => $deliveries->sum('total_amount'),
                'totalQuantity' => $deliveries->sum('quantity'),
            ]);

            return $pdf->download('delivery-report-' . now()->format('Ymd-His') . '.pdf');
        }

        $rows = $deliveries->map(function ($delivery) {
            return [
                $delivery->id,
                optional($delivery->created_at)->format('Y-m-d H:i:s'),
                optional($delivery->customer)->name ?? 'N/A',
                optional($delivery->deliveryPerson)->name ?? 'Unassigned',
                $delivery->quantity,
                $delivery->order_status,
                $delivery->payment_status,
                number_format((float) $delivery->total_amount, 2, '.', ''),
            ];
        })->all();

        return $this->streamCsvDownload(
            'delivery-report-' . now()->format('Ymd-His') . '.csv',
            ['Order ID', 'Date', 'Customer', 'Driver', 'Quantity', 'Delivery Status', 'Payment Status', 'Total Amount'],
            $rows
        );
    }

    /**
     * Export customer report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportCustomerReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'filter' => 'nullable|in:all,regular,non-regular,top',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,pdf,excel',
        ]);

        $filter = $validated['filter'] ?? 'all';
        $format = $this->resolveExportFormat($validated['format'] ?? 'csv');
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->subMonths(3),
            Carbon::now()
        );

        $query = Customer::withCount(['orders' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->withSum(['orders' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }], 'total_amount');

        if ($filter === 'regular') {
            $query->where('is_regular', true);
        } elseif ($filter === 'non-regular') {
            $query->where('is_regular', false);
        } elseif ($filter === 'top') {
            $query->whereHas('orders', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })->orderByDesc('orders_sum_total_amount');
        } else {
            $query->orderByDesc('orders_sum_total_amount');
        }

        $customers = $query->get();
        foreach ($customers as $customer) {
            $customer->total_spent = (float) ($customer->orders_sum_total_amount ?? 0);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.exports.customers_pdf', [
                'customers' => $customers,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            return $pdf->download('customer-report-' . now()->format('Ymd-His') . '.pdf');
        }

        $rows = $customers->map(function ($customer) {
            return [
                $customer->id,
                $customer->name,
                $customer->phone,
                $customer->is_regular ? 'Regular' : 'Non-Regular',
                $customer->orders_count,
                number_format((float) $customer->total_spent, 2, '.', ''),
            ];
        })->all();

        return $this->streamCsvDownload(
            'customer-report-' . now()->format('Ymd-His') . '.csv',
            ['Customer ID', 'Name', 'Phone', 'Type', 'Order Count', 'Total Spent'],
            $rows
        );
    }

    /**
     * Export inventory report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportInventoryReport(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,custom',
            'item_type' => 'nullable|in:all,water,container,empty,cap,seal,other',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,pdf,excel',
        ]);

        $itemType = $validated['item_type'] ?? 'all';
        $format = $this->resolveExportFormat($validated['format'] ?? 'csv');
        [$startDate, $endDate] = $this->resolveDateRange(
            $validated,
            Carbon::now()->subMonth(),
            Carbon::now()
        );

        if ($format === 'pdf') {
            return redirect()->back()
                ->with('info', 'Inventory PDF export is not available yet. Please use CSV export.');
        }

        $query = InventoryTransaction::with(['inventoryItem', 'user', 'order'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($itemType !== 'all') {
            $query->whereHas('inventoryItem', function ($q) use ($itemType) {
                $q->where('type', $itemType);
            });
        }

        $transactions = $query->orderByDesc('created_at')->get();

        $rows = $transactions->map(function ($transaction) {
            return [
                optional($transaction->created_at)->format('Y-m-d H:i:s'),
                optional($transaction->inventoryItem)->name ?? 'N/A',
                optional($transaction->inventoryItem)->type ?? 'N/A',
                $transaction->quantity_change,
                $transaction->transaction_type,
                optional($transaction->user)->name ?? 'System',
                $transaction->order_id ?? '',
                $transaction->notes,
            ];
        })->all();

        return $this->streamCsvDownload(
            'inventory-report-' . now()->format('Ymd-His') . '.csv',
            ['Date', 'Item Name', 'Item Type', 'Quantity Change', 'Transaction Type', 'User', 'Order ID', 'Notes'],
            $rows
        );
    }

    /**
     * Normalize supported export formats.
     */
    private function resolveExportFormat(string $format): string
    {
        return $format === 'pdf' ? 'pdf' : 'csv';
    }

    /**
     * Stream a CSV download.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamCsvDownload(string $filename, array $headers, array $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
