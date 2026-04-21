<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
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
     * Display a listing of deliveries.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
{
    $status = $request->status ?? 'pending';
    $perPage = in_array($request->per_page, [10, 20, 50, 100]) ? $request->per_page : 10;
    
    $query = Order::where('is_delivery', true);
    
    if ($status !== 'all') {
        $query->where('order_status', $status);
    }
    
    $deliveries = $query->with(['customer', 'deliveryPerson'])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);
    
    $pendingCount = Order::where('is_delivery', true)
        ->where('order_status', 'pending')
        ->count();
    
    return view('deliveries.index', compact('deliveries', 'pendingCount'));
}

    /**
     * Show a specific delivery order details.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $order = Order::with(['customer', 'deliveryPerson'])
            ->where('is_delivery', true)
            ->findOrFail($id);
        
        // Check if this delivery is assigned to current user if they are delivery personnel
        if (auth()->user()->isDelivery() && $order->delivery_user_id != auth()->id()) {
            Log::channel('security')->warning('security.authorization.denied', [
                'user_id' => auth()->id(),
                'action' => 'delivery.view',
                'delivery_id' => $order->id,
                'delivery_user_id' => $order->delivery_user_id,
                'ip' => request()->ip(),
            ]);

            abort(403, 'You are not authorized to view this delivery.');
        }
        
        return view('deliveries.show', compact('order'));
    }

    /**
     * Mark a delivery as completed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function complete(Request $request, $id)
    {
        $order = Order::where('is_delivery', true)
            ->where('order_status', 'pending')
            ->findOrFail($id);
        
        // Check if this delivery is assigned to current user if they are delivery personnel
        if (auth()->user()->isDelivery() && $order->delivery_user_id != auth()->id()) {
            Log::channel('security')->warning('security.authorization.denied', [
                'user_id' => auth()->id(),
                'action' => 'delivery.complete',
                'delivery_id' => $order->id,
                'delivery_user_id' => $order->delivery_user_id,
                'ip' => $request->ip(),
            ]);

            abort(403, 'You are not authorized to update this delivery.');
        }
        
        // If the order was unpaid, update payment status and method
        if ($order->payment_status == 'unpaid') {
            $request->validate([
                'payment_method' => 'required|in:cash,gcash',
                'payment_reference' => 'nullable|required_if:payment_method,gcash',
            ]);
            
            $order->payment_status = 'paid';
            $order->payment_method = $request->payment_method;
            $order->payment_reference = $request->payment_method == 'gcash' ? $request->payment_reference : null;
        }
        
        // Update order status and delivery date
        $order->order_status = 'completed';
        $order->delivery_date = Carbon::now();
        $order->save();

        Log::channel('system')->info('delivery.completed', [
            'actor_id' => auth()->id(),
            'order_id' => $order->id,
            'delivery_user_id' => $order->delivery_user_id,
            'payment_status' => $order->payment_status,
        ]);
        
        return redirect()->route('deliveries.index')
            ->with('success', 'Delivery marked as completed successfully.');
    }

    /**
     * Cancel a delivery.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel($id)
    {
        // Only admin/owner can cancel deliveries
        if (!auth()->user()->isOwner() && !auth()->user()->isAdmin()) {
            Log::channel('security')->warning('security.authorization.denied', [
                'user_id' => auth()->id(),
                'action' => 'delivery.cancel',
                'delivery_id' => $id,
                'ip' => request()->ip(),
            ]);

            abort(403, 'You are not authorized to cancel deliveries.');
        }
        
        $order = Order::where('is_delivery', true)
            ->where('order_status', 'pending')
            ->findOrFail($id);
        
        $order->order_status = 'cancelled';
        $order->save();

        Log::channel('system')->info('delivery.cancelled', [
            'actor_id' => auth()->id(),
            'order_id' => $order->id,
            'delivery_user_id' => $order->delivery_user_id,
        ]);
        
        return redirect()->route('deliveries.index')
            ->with('success', 'Delivery cancelled successfully.');
    }
}
