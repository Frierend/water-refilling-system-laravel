<?php
// app/Http/Controllers/OrderController.php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of orders.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $orders = Order::with(['customer', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('orders.index', compact('orders'));
    }

    /**
     * Show form to create a new order.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        // Get all customers for dropdown
        $customers = Customer::orderBy('name')->get();
        $deliveryPersonnel = User::where('role', 'delivery')->get();
        
        return view('orders.create', compact('customers', 'deliveryPersonnel'));
    }

    /**
     * Store a newly created order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Fixed validation rules: payment_method only required if payment_status is paid
        $validationRules = [
            'customer_id' => 'required|exists:customers,id',
            'quantity' => 'required|integer|min:1',
            'is_delivery' => 'boolean',
            'payment_status' => 'required|in:paid,unpaid',
            'payment_method' => 'nullable|required_if:payment_status,paid|in:cash,gcash,none',
            'payment_reference' => 'nullable|required_if:payment_method,gcash|string|max:255',
            'delivery_user_id' => 'nullable|required_if:is_delivery,1|exists:users,id',
            'notes' => 'nullable|string',
        ];
        
        // Add empty_returns validation for non-delivery orders (refill)
        if (!$request->has('is_delivery') || !$request->is_delivery) {
            $validationRules['empty_returns'] = 'nullable|integer|min:0';
        }
        
        $validated = $request->validate($validationRules);
        
        try {
            DB::beginTransaction();
            
            // Check if water (filled gallons) stock is sufficient
            $filledGallonsItem = InventoryItem::where('type', 'water')->firstOrFail();
            if ($filledGallonsItem->quantity < $validated['quantity']) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Not enough filled gallons in stock.'])->withInput();
            }
            
            // Check if containers are sufficient (if needed for delivery)
            if ($request->has('is_delivery') && $validated['is_delivery']) {
                $containerItem = InventoryItem::where('type', 'container')->first();
                if ($containerItem && $containerItem->quantity < $validated['quantity']) {
                    DB::rollBack();
                    return back()->withErrors(['quantity' => 'Not enough containers in stock.'])->withInput();
                }
            }
            
            // Check if caps are sufficient
            $capsItem = InventoryItem::where('type', 'cap')->first();
            if ($capsItem && $capsItem->quantity < $validated['quantity']) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Not enough caps in stock.'])->withInput();
            }
            
            // Check if seals are sufficient
            $sealsItem = InventoryItem::where('type', 'seal')->first();
            if ($sealsItem && $sealsItem->quantity < $validated['quantity']) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Not enough seals in stock.'])->withInput();
            }
            
            // Create new order
            $order = new Order($validated);
            $order->user_id = auth()->id();
            $order->water_price = 25.00; // Default water price
            $order->delivery_fee = 5.00; // Default delivery fee
            
            // Calculate total amount
            $waterTotal = $order->quantity * $order->water_price;
            $deliveryTotal = isset($validated['is_delivery']) && $validated['is_delivery'] ? ($order->quantity * $order->delivery_fee) : 0;
            $order->total_amount = $waterTotal + $deliveryTotal;
            
            // Set order status
            if (isset($validated['is_delivery']) && $validated['is_delivery'] && $validated['payment_status'] == 'unpaid') {
                $order->order_status = 'pending';
            } else {
                $order->order_status = 'completed';
            }
            
            // If payment status is unpaid, set payment method to 'none'
            if ($validated['payment_status'] == 'unpaid') {
                $order->payment_method = 'none';
                $order->payment_reference = null;
            }
            
            // Save the order
            $order->save();
            
            // Create inventory transactions
            
            // 1. Always deduct filled gallons (water) - this is given to customer in all cases
            $this->createInventoryTransaction(
                $filledGallonsItem->id, 
                auth()->id(), 
                -$validated['quantity'], 
                'order', 
                $order->id, 
                'Filled gallons given to customer for order #' . $order->id
            );
            
            // 2. Handle empty gallons based on order type
            $emptyGallonsItem = InventoryItem::where('type', 'empty')->first();
            
            if ($emptyGallonsItem) {
                if (isset($validated['is_delivery']) && $validated['is_delivery']) {
                    // For delivery: We don't add empty gallons automatically since customer hasn't returned them yet
                    // Nothing to do here for empties
                } else {
                    // For refill/walk-in: Customer is bringing empties, so we increase our empty inventory
                    // Use the quantity value as default if empty_returns is not specified
                    $emptyReturns = isset($validated['empty_returns']) ? $validated['empty_returns'] : $validated['quantity'];
                    
                    if ($emptyReturns > 0) {
                        $this->createInventoryTransaction(
                            $emptyGallonsItem->id, 
                            auth()->id(), 
                            $emptyReturns, // POSITIVE value to increase inventory
                            'order', 
                            $order->id, 
                            'Empty gallons returned by customer for order #' . $order->id
                        );
                    }
                }
            }
            
            // 3. Deduct container if needed for delivery
            if (isset($validated['is_delivery']) && $validated['is_delivery']) {
                $containerItem = InventoryItem::where('type', 'container')->first();
                if ($containerItem) {
                    $this->createInventoryTransaction(
                        $containerItem->id, 
                        auth()->id(), 
                        -$validated['quantity'], 
                        'order', 
                        $order->id, 
                        'Containers used for delivery order #' . $order->id
                    );
                }
            }
            
            // 4. Deduct caps
            if ($capsItem) {
                $this->createInventoryTransaction(
                    $capsItem->id, 
                    auth()->id(), 
                    -$validated['quantity'], 
                    'order', 
                    $order->id, 
                    'Caps used for order #' . $order->id
                );
            }
            
            // 5. Deduct seals
            if ($sealsItem) {
                $this->createInventoryTransaction(
                    $sealsItem->id, 
                    auth()->id(), 
                    -$validated['quantity'], 
                    'order', 
                    $order->id, 
                    'Seals used for order #' . $order->id
                );
            }
            
            DB::commit();

            Log::channel('audit')->info('order.created', [
                'actor_id' => auth()->id(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'is_delivery' => (bool) $order->is_delivery,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
            ]);
            
            return redirect()->route('orders.index')
                ->with('success', 'Order created successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error creating order: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the order details.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\View\View
     */
    public function show(Order $order)
    {
        $order->load(['customer', 'user', 'deliveryPerson', 'inventoryTransactions.inventoryItem']);
        return view('orders.show', compact('order'));
    }

    /**
     * Show form to edit order.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\View\View
     */
    public function edit(Order $order)
    {
        $customers = Customer::orderBy('name')->get();
        $deliveryPersonnel = User::where('role', 'delivery')->get();
        
        return view('orders.edit', compact('order', 'customers', 'deliveryPersonnel'));
    }

    /**
     * Update the order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Order $order)
    {
        // Fixed validation rules for update as well
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_status' => 'required|in:paid,unpaid',
            'payment_method' => 'nullable|required_if:payment_status,paid|in:cash,gcash,none',
            'payment_reference' => 'nullable|required_if:payment_method,gcash|string|max:255',
            'order_status' => 'required|in:pending,completed,cancelled',
            'delivery_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'empty_returns' => 'nullable|integer|min:0',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Handle empty gallons return when completing a delivery order
            if ($order->is_delivery && 
                $order->order_status != 'completed' && 
                $validated['order_status'] == 'completed' && 
                isset($validated['empty_returns']) && 
                $validated['empty_returns'] > 0) {
                
                // Find empty gallons inventory item
                $emptyGallonsItem = InventoryItem::where('type', 'empty')->first();
                
                if ($emptyGallonsItem) {
                    // Record empty gallons being returned by the customer
                    $this->createInventoryTransaction(
                        $emptyGallonsItem->id, 
                        auth()->id(), 
                        $validated['empty_returns'], // POSITIVE value to increase inventory
                        'return', 
                        $order->id, 
                        'Empty gallons returned by customer for completed delivery order #' . $order->id
                    );
                }
            }
            
            // Only update payment related fields to prevent inventory issues
            $updateData = [
                'customer_id' => $validated['customer_id'],
                'payment_status' => $validated['payment_status'],
                'order_status' => $validated['order_status'],
                'delivery_user_id' => $validated['delivery_user_id'],
                'notes' => $validated['notes'],
            ];
            
            // Only include payment method and reference if payment status is paid
            if ($validated['payment_status'] == 'paid') {
                $updateData['payment_method'] = $validated['payment_method'];
                $updateData['payment_reference'] = $validated['payment_reference'];
            } else {
                $updateData['payment_method'] = 'none';
                $updateData['payment_reference'] = null;
            }
            
            $order->update($updateData);
            
            // If status is completed, set the delivery date
            if ($validated['order_status'] == 'completed' && $order->is_delivery && !$order->delivery_date) {
                $order->delivery_date = now();
                $order->save();
            }
            
            DB::commit();

            Log::channel('audit')->info('order.updated', [
                'actor_id' => auth()->id(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'delivery_user_id' => $order->delivery_user_id,
            ]);
            
            return redirect()->route('orders.show', $order)
                ->with('success', 'Order updated successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error updating order: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Create a walk-in sale quickly.
     *
     * @return \Illuminate\View\View
     */
    public function createWalkin()
    {
        return view('orders.walkin');
    }

    /**
     * Store a walk-in sale.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeWalkin(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,gcash',
            'payment_reference' => 'nullable|required_if:payment_method,gcash|string|max:255',
            'empty_returns' => 'nullable|integer|min:0',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Find or create a customer
            $customer = Customer::firstOrCreate(
                ['name' => $validated['customer_name']],
                ['is_regular' => false]
            );
            
            // Check if water (filled gallons) stock is sufficient
            $filledGallonsItem = InventoryItem::where('type', 'water')->firstOrFail();
            if ($filledGallonsItem->quantity < $validated['quantity']) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Not enough filled gallons in stock.'])->withInput();
            }
            
            // Check if other items are sufficient
            $capsItem = InventoryItem::where('type', 'cap')->first();
            if ($capsItem && $capsItem->quantity < $validated['quantity']) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Not enough caps in stock.'])->withInput();
            }
            
            $sealsItem = InventoryItem::where('type', 'seal')->first();
            if ($sealsItem && $sealsItem->quantity < $validated['quantity']) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Not enough seals in stock.'])->withInput();
            }
            
            // Create order
            $order = new Order();
            $order->customer_id = $customer->id;
            $order->user_id = auth()->id();
            $order->quantity = $validated['quantity'];
            $order->water_price = 25.00;
            $order->is_delivery = false;
            $order->total_amount = $order->quantity * $order->water_price;
            $order->payment_status = 'paid';
            $order->payment_method = $validated['payment_method'];
            $order->payment_reference = $validated['payment_reference'];
            $order->order_status = 'completed';
            $order->save();
            
            // 1. Deduct filled gallons (water)
            $this->createInventoryTransaction(
                $filledGallonsItem->id, 
                auth()->id(), 
                -$validated['quantity'], 
                'order', 
                $order->id, 
                'Filled gallons used for walk-in order #' . $order->id
            );
            
            // 2. Add empty gallons (customer is bringing empties)
            $emptyGallonsItem = InventoryItem::where('type', 'empty')->first();
            if ($emptyGallonsItem) {
                $emptyReturns = isset($validated['empty_returns']) ? $validated['empty_returns'] : $validated['quantity'];
                
                if ($emptyReturns > 0) {
                    $this->createInventoryTransaction(
                        $emptyGallonsItem->id, 
                        auth()->id(), 
                        $emptyReturns, // POSITIVE value to increase inventory
                        'order', 
                        $order->id, 
                        'Empty gallons returned by customer for walk-in order #' . $order->id
                    );
                }
            }
            
            // 3. Deduct caps
            if ($capsItem) {
                $this->createInventoryTransaction(
                    $capsItem->id, 
                    auth()->id(), 
                    -$validated['quantity'], 
                    'order', 
                    $order->id, 
                    'Caps used for walk-in order #' . $order->id
                );
            }
            
            // 4. Deduct seals
            if ($sealsItem) {
                $this->createInventoryTransaction(
                    $sealsItem->id, 
                    auth()->id(), 
                    -$validated['quantity'], 
                    'order', 
                    $order->id, 
                    'Seals used for walk-in order #' . $order->id
                );
            }
            
            DB::commit();

            Log::channel('audit')->info('order.walkin.created', [
                'actor_id' => auth()->id(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'quantity' => $order->quantity,
                'payment_method' => $order->payment_method,
            ]);
            
            return redirect()->route('orders.walkin')
                ->with('success', 'Walk-in sale recorded successfully. Total: ₱' . number_format($order->total_amount, 2));
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error creating walk-in sale: ' . $e->getMessage()])->withInput();
        }
    }
    
    /**
     * Mark an order as completed.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function complete($id)
    {
        try {
            DB::beginTransaction();
            
            $order = Order::findOrFail($id);
            
            // Check if this is a delivery order being completed
            if ($order->is_delivery && $order->order_status != 'completed') {
                // Here you would add UI to ask for empty returns
                // The actual empty returns would be handled in the update method
                // This is just a direct "complete" action without that info
                
                // Optionally, if you want to handle empty returns directly here, 
                // you could add code similar to what's in the update method
            }
            
            $order->order_status = 'completed';
            
            // Set delivery date if applicable
            if ($order->is_delivery && !$order->delivery_date) {
                $order->delivery_date = now();
            }
            
            $order->save();
            
            DB::commit();

            Log::channel('audit')->info('order.completed', [
                'actor_id' => auth()->id(),
                'order_id' => $order->id,
                'is_delivery' => (bool) $order->is_delivery,
                'delivery_date' => optional($order->delivery_date)->toDateTimeString(),
            ]);
            
            return redirect()->back()->with('success', 'Order marked as completed.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error completing order: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Cancel an order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel($id)
    {
        try {
            DB::beginTransaction();
            
            // Only admin/owner can cancel orders
            if (!auth()->user()->isOwner() && !auth()->user()->isAdmin()) {
                abort(403, 'You are not authorized to cancel orders.');
            }
            
            $order = Order::findOrFail($id);
            
            // If the order is being cancelled, we should restore inventory
            if ($order->order_status != 'cancelled') {
                // Get the inventory transactions for this order
                $inventoryTransactions = InventoryTransaction::where('order_id', $order->id)->get();
                
                foreach ($inventoryTransactions as $transaction) {
                    // Reverse the transaction (multiply by -1)
                    $reverseQuantity = -1 * $transaction->quantity_change;
                    
                    // Create a reversal transaction
                    $this->createInventoryTransaction(
                        $transaction->inventory_item_id, 
                        auth()->id(), 
                        $reverseQuantity, 
                        'cancellation', 
                        $order->id, 
                        'Inventory adjustment due to cancellation of order #' . $order->id
                    );
                }
            }
            
            $order->order_status = 'cancelled';
            $order->save();
            
            DB::commit();

            Log::channel('audit')->info('order.cancelled', [
                'actor_id' => auth()->id(),
                'order_id' => $order->id,
                'inventory_transactions_reversed' => $inventoryTransactions->count(),
            ]);
            
            return redirect()->back()->with('success', 'Order cancelled and inventory restored.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error cancelling order: ' . $e->getMessage()]);
        }
    }

    /**
     * Helper method to create inventory transaction and update item quantity.
     *
     * @param  int  $itemId
     * @param  int  $userId
     * @param  int  $quantityChange
     * @param  string  $transactionType
     * @param  int|null  $orderId
     * @param  string|null  $notes
     * @return void
     */
    private function createInventoryTransaction($itemId, $userId, $quantityChange, $transactionType, $orderId = null, $notes = null)
    {
        // Create the transaction
        InventoryTransaction::create([
            'inventory_item_id' => $itemId,
            'user_id' => $userId,
            'quantity_change' => $quantityChange,
            'transaction_type' => $transactionType,
            'order_id' => $orderId,
            'notes' => $notes,
        ]);
        
        // Update the item quantity
        $item = InventoryItem::find($itemId);
        $item->quantity += $quantityChange;
        $item->save();
    }
}
