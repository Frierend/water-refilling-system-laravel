@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-3">Multi-Factor Authentication</h4>
                    <p class="text-muted">Enter the 6-digit code from Google Authenticator.</p>

                    <form method="POST" action="{{ route('mfa.challenge.verify') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="code">Authentication Code</label>
                            <input id="code" type="text" name="code" maxlength="6" class="form-control @error('code') is-invalid @enderror" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary" type="submit">Verify</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
