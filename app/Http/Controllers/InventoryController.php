<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        // Deny delivery personnel access
        $this->middleware('role:owner,admin,helper');
    }
    
    /**
     * Display a listing of inventory items.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = InventoryItem::query();
        
        // Filter by type
        if ($request->has('filter_type') && $request->filter_type) {
            $query->where('type', $request->filter_type);
        }
        
        // Filter by stock level
        if ($request->has('show_low_stock')) {
            if ($request->show_low_stock == '1') {
                $query->whereRaw('quantity <= threshold');
            } elseif ($request->show_low_stock == '2') {
                $query->whereRaw('quantity <= threshold / 2');
            }
        }
        
        $items = $query->orderBy('type')->orderBy('name')->paginate(15);
        $lowStockCount = InventoryItem::whereRaw('quantity <= threshold')->count();
        
        return view('inventory.index', compact('items', 'lowStockCount'));
    }

    /**
     * Show the form for creating a new inventory item.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('inventory.create');
    }

    /**
     * Store a newly created inventory item in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:water,container,empty,cap,seal,other',
            'description' => 'nullable|string',
            'quantity' => 'required|integer|min:0',
            'threshold' => 'required|integer|min:1',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create inventory item
            $item = InventoryItem::create($validated);
            
            // Create initial stock transaction if quantity > 0
            if ($validated['quantity'] > 0) {
                InventoryTransaction::create([
                    'inventory_item_id' => $item->id,
                    'user_id' => auth()->id(),
                    'quantity_change' => $validated['quantity'],
                    'transaction_type' => 'initial',
                    'notes' => 'Initial inventory setup'
                ]);
            }
            
            DB::commit();

            Log::channel('audit')->info('inventory.item.created', [
                'actor_id' => auth()->id(),
                'inventory_item_id' => $item->id,
                'type' => $item->type,
                'quantity' => $item->quantity,
                'threshold' => $item->threshold,
            ]);
            
            return redirect()->route('inventory.index')
                ->with('success', 'Inventory item created successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error creating inventory item: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified inventory item with transaction history.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);
        
        $query = $item->transactions()->with('user');
        
        // Apply filter if specified
        if ($request->has('filter')) {
            if ($request->filter === 'added') {
                $query->where('quantity_change', '>', 0);
            } elseif ($request->filter === 'removed') {
                $query->where('quantity_change', '<', 0);
            } elseif ($request->filter === 'manual') {
                $query->where('transaction_type', 'adjustment');
            } elseif ($request->filter === 'order') {
                $query->where('transaction_type', 'order');
            }
        }
        
        $transactions = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return view('inventory.show', compact('item', 'transactions'));
    }

    /**
     * Show the form for editing the specified inventory item.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $item = InventoryItem::findOrFail($id);
        return view('inventory.edit', compact('item'));
    }

    /**
     * Update the specified inventory item in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:water,container,empty,cap,seal,other',
            'description' => 'nullable|string',
            'threshold' => 'required|integer|min:1',
        ]);
        
        $item = InventoryItem::findOrFail($id);
        $item->update($validated);
        
        return redirect()->route('inventory.show', $item->id)
            ->with('success', 'Inventory item updated successfully.');
    }

    /**
     * Remove the specified inventory item from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        // Only admin/owner can delete inventory items
        if (!auth()->user()->isOwner() && !auth()->user()->isAdmin()) {
            abort(403, 'You are not authorized to delete inventory items.');
        }
        
        $item = InventoryItem::findOrFail($id);
        
        // Check if item has transactions
        if ($item->transactions()->count() > 0) {
            return redirect()->back()
                ->with('error', 'This inventory item has transactions and cannot be deleted.');
        }
        
        $item->delete();

        Log::channel('audit')->info('inventory.item.deleted', [
            'actor_id' => auth()->id(),
            'inventory_item_id' => $item->id,
            'type' => $item->type,
        ]);
        
        return redirect()->route('inventory.index')
            ->with('success', 'Inventory item deleted successfully.');
    }

    /**
     * Show inventory adjustment form.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function showAdjustForm($id)
    {
        $item = InventoryItem::findOrFail($id);
        $transactions = $item->transactions()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
            
        return view('inventory.adjust', compact('item', 'transactions'));
    }

    /**
     * Process inventory adjustment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function adjustStore(Request $request)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'adjustment_type' => 'required|in:add,remove',
            'quantity' => 'required|integer|min:1',
            'notes' => 'required|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $item = InventoryItem::findOrFail($validated['inventory_item_id']);
            
            // Calculate quantity change
            $quantityChange = $validated['adjustment_type'] === 'add' ? $validated['quantity'] : -$validated['quantity'];
            
            // Check if removing too much
            if ($quantityChange < 0 && abs($quantityChange) > $item->quantity) {
                DB::rollBack();
                return back()->withErrors(['quantity' => 'Cannot remove more than current quantity.'])->withInput();
            }
            
            // Create transaction
            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'user_id' => auth()->id(),
                'quantity_change' => $quantityChange,
                'transaction_type' => 'adjustment',
                'notes' => $validated['notes']
            ]);
            
            // Update item quantity
            $item->quantity += $quantityChange;
            $item->save();
            
            DB::commit();

            Log::channel('audit')->info('inventory.item.adjusted', [
                'actor_id' => auth()->id(),
                'inventory_item_id' => $item->id,
                'quantity_change' => $quantityChange,
                'new_quantity' => $item->quantity,
                'adjustment_type' => $validated['adjustment_type'],
            ]);
            
            return redirect()->route('inventory.show', $item->id)
                ->with('success', 'Inventory adjusted successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error adjusting inventory: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show low stock items.
     *
     * @return \Illuminate\View\View
     */
    public function lowStock()
    {
        $items = InventoryItem::whereRaw('quantity <= threshold')
            ->orderByRaw('quantity/threshold ASC')
            ->get();
            
        return view('inventory.low-stock', compact('items'));
    }

    /**
     * Export inventory data to CSV or PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => 'nullable|in:csv,pdf,excel',
        ]);

        $format = $validated['format'] ?? 'csv';

        if ($format === 'pdf') {
            return redirect()->back()
                ->with('info', 'Inventory PDF export is not available yet. Please use CSV export.');
        }

        $items = InventoryItem::orderBy('type')->orderBy('name')->get();
        $rows = $items->map(function ($item) {
            return [
                $item->id,
                $item->name,
                $item->type,
                $item->quantity,
                $item->threshold,
                $item->quantity <= $item->threshold ? 'Yes' : 'No',
                optional($item->updated_at)->format('Y-m-d H:i:s'),
            ];
        })->all();

        return $this->streamCsvDownload(
            'inventory-items-' . now()->format('Ymd-His') . '.csv',
            ['ID', 'Name', 'Type', 'Quantity', 'Threshold', 'Low Stock', 'Last Updated'],
            $rows
        );
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
