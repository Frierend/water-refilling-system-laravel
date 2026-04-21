@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="display-6 fw-bold text-primary">
            <i class="bi bi-pencil-square me-2"></i>Edit Inventory Item
        </h1>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex">
                <div class="me-3">
                    <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                </div>
                <div>
                    <h5 class="alert-heading">Please fix the following errors:</h5>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('inventory.update', $item->id) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $item->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="water" {{ old('type', $item->type) == 'water' ? 'selected' : '' }}>Water</option>
                                <option value="container" {{ old('type', $item->type) == 'container' ? 'selected' : '' }}>Container</option>
                                <option value="empty" {{ old('type', $item->type) == 'empty' ? 'selected' : '' }}>Empty</option>
                                <option value="cap" {{ old('type', $item->type) == 'cap' ? 'selected' : '' }}>Cap</option>
                                <option value="seal" {{ old('type', $item->type) == 'seal' ? 'selected' : '' }}>Seal</option>
                                <option value="other" {{ old('type', $item->type) == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $item->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="threshold" class="form-label">Low Stock Threshold <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('threshold') is-invalid @enderror" id="threshold" name="threshold" min="1" value="{{ old('threshold', $item->threshold) }}" required>
                            <div class="form-text">Alerts will show when stock falls below this level.</div>
                            @error('threshold')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <div>
                                    <strong>Current Quantity: {{ $item->quantity }}</strong>
                                    <div>To adjust the quantity, please use the inventory adjustment feature.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-2"></i>Update Item
                            </button>
                            <a href="{{ route('inventory.adjust', $item->id) }}" class="btn btn-outline-primary">
                                <i class="bi bi-sliders me-1"></i> Adjust Stock Quantity
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Item Details</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Current Quantity
                            <span class="badge {{ $item->quantity <= $item->threshold ? ($item->quantity <= $item->threshold/2 ? 'bg-danger' : 'bg-warning text-dark') : 'bg-success' }} rounded-pill">{{ $item->quantity }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Last Updated
                            <span>{{ $item->updated_at->format('M d, Y') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card shadow-sm bg-light">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-info-circle me-2"></i>Quick Tip
                    </h5>
                    <p class="card-text">
                        You can view the complete transaction history for this item to track all changes and adjustments over time.
                    </p>
                    <a href="{{ route('inventory.show', $item->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-clock-history me-1"></i> View Transaction History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
