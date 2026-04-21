@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 text-primary mb-3">
                        <i class="bi bi-shield-lock me-2"></i>Password Change Required
                    </h1>
                    <p class="mb-3">
                        Your account is using a temporary password. You must change it before accessing the rest of the system.
                    </p>
                    <p class="text-muted mb-4">
                        Your new password must meet the current security policy.
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.force.update') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                autocomplete="new-password"
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                required
                                autocomplete="new-password"
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>

                    <hr class="my-4">

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
