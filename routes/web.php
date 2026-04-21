<?php
// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;

// Authentication Routes
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Customers
    Route::resource('customers', CustomerController::class);
    
    // API routes for AJAX customer search
    Route::prefix('api')->group(function () {
        Route::get('/customers/search', [CustomerController::class, 'search']);
    });
    
    // Orders
    Route::resource('orders', OrderController::class);
    Route::get('/walkin', [OrderController::class, 'createWalkin'])->name('orders.walkin');
    Route::post('/walkin', [OrderController::class, 'storeWalkin'])->name('orders.walkin.store');
    Route::post('/orders/{id}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    
    // Deliveries
    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('/deliveries/{order}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{id}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');
    Route::post('/deliveries/{id}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
    
    // Inventory - Using explicit parameter naming
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
    Route::get('/inventory-low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{id}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::get('/inventory/{id}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    
    // Additional inventory routes
    Route::get('/inventory/{id}/adjust', [InventoryController::class, 'showAdjustForm'])->name('inventory.adjust');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjustStore'])->name('inventory.adjust.store');
    
    // Reports
    Route::get('/reports/sales', [ReportController::class, 'salesReport'])->name('reports.sales');
    Route::get('/reports/delivery', [ReportController::class, 'deliveryReport'])->name('reports.delivery');
    Route::get('/reports/customer', [ReportController::class, 'customerReport'])->name('reports.customer');
    Route::get('/reports/inventory', [App\Http\Controllers\ReportController::class, 'inventoryReport'])->name('reports.inventory');
    
    // Report exports
    Route::get('/reports/sales/export', [ReportController::class, 'exportSalesReport'])->name('reports.sales.export');
    Route::get('/reports/delivery/export', [ReportController::class, 'exportDeliveryReport'])->name('reports.delivery.export');
    Route::get('/reports/customer/export', [ReportController::class, 'exportCustomerReport'])->name('reports.customer.export');
    Route::get('/reports/inventory/export', [App\Http\Controllers\ReportController::class, 'exportInventoryReport'])->name('reports.inventory.export');
});
