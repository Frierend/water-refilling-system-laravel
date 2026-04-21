@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="display-6 fw-bold text-primary">
            <i class="bi bi-person-plus me-2"></i>Create User Account
        </h1>
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

    @if (session('temporary_password'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">
                <i class="bi bi-key me-2"></i>Temporary Password (Shown Once)
            </h5>
            <p class="mb-2">This temporary password is shown once. Copy it now and share it securely with the user.</p>
            <p class="mb-1"><strong>Email:</strong> {{ session('temporary_password_user_email') }}</p>
            <p class="mb-0"><strong>Temporary Password:</strong> <code>{{ session('temporary_password') }}</code></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                class="form-control @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                <option value="">Select role...</option>
                                <option value="delivery" {{ old('role') === 'delivery' ? 'selected' : '' }}>Delivery</option>
                                <option value="helper" {{ old('role') === 'helper' ? 'selected' : '' }}>Helper</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-person-plus-fill me-2"></i>Create User Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm bg-light">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-shield-check me-2"></i>Security Notes
                    </h5>
                    <ul class="card-text mb-0">
                        <li>Only delivery and helper roles can be assigned.</li>
                        <li>Password is generated by the system and stored hashed only.</li>
                        <li>User will be required to change password on first login.</li>
                        <li>Share temporary password through a secure channel.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
