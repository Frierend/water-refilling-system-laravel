<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        // Deny delivery personnel access (except for viewing)
        $this->middleware('role:owner,admin,helper')->except(['index', 'show']);
    }
    
    /**
     * Display a listing of the customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = Customer::query();
        
        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }
        
        // Apply customer type filter
        if ($request->has('filter')) {
            if ($request->filter === 'regular') {
                $query->where('is_regular', true);
            } elseif ($request->filter === 'non-regular') {
                $query->where('is_regular', false);
            }
        }
        
        // Apply sorting
        if ($request->has('sort')) {
            if ($request->sort === 'newest') {
                $query->latest();
            } elseif ($request->sort === 'oldest') {
                $query->oldest();
            } elseif ($request->sort === 'name') {
                $query->orderBy('name');
            } elseif ($request->sort === 'orders') {
                $query->withCount('orders')
                    ->orderByDesc('orders_count');
            }
        } else {
            $query->latest(); // Default sort by newest
        }
        
        $customers = $query->paginate(15);
        return view('customers.index', compact('customers'));
    }

    /**
     * Show the form for creating a new customer.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('customers.create');
    }

    /**
     * Store a newly created customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'is_regular' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
        
        // Set is_regular to false if not provided
        if (!isset($validated['is_regular'])) {
            $validated['is_regular'] = false;
        }
        
        $customer = Customer::create($validated);

        Log::channel('system')->info('customer.created', [
            'customer_id' => $customer->id,
            'is_regular' => (bool) $customer->is_regular,
            'performed_by' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);
        
        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified customer.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\View\View
     */
    public function show(Customer $customer)
    {
        return view('customers.show', compact('customer'));
    }

    /**
     * Show the form for editing the specified customer.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\View\View
     */
    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'is_regular' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
        
        // Set is_regular to false if not provided
        if (!isset($validated['is_regular'])) {
            $validated['is_regular'] = false;
        }
        
        $customer->update($validated);

        Log::channel('system')->info('customer.updated', [
            'customer_id' => $customer->id,
            'is_regular' => (bool) $customer->is_regular,
            'performed_by' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);
        
        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified customer from storage.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Customer $customer)
    {
        // Check if customer has orders
        if ($customer->orders()->count() > 0) {
            Log::channel('system')->warning('customer.delete.blocked.has_orders', [
                'customer_id' => $customer->id,
                'performed_by' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return redirect()->back()
                ->with('error', 'This customer has orders and cannot be deleted.');
        }
        
        $deletedCustomerId = $customer->id;
        $customer->delete();

        Log::channel('system')->info('customer.deleted', [
            'customer_id' => $deletedCustomerId,
            'performed_by' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);
        
        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
    
    /**
     * Search customers by term (API endpoint for AJAX)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $term = $request->input('term');
        
        $customers = Customer::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'is_regular']);
            
        return response()->json($customers);
    }
}
