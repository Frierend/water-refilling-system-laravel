@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-3">Set Up Google Authenticator</h4>
                    <p>Add this account in Google Authenticator using the secret below or by scanning a compatible QR code generator using the otpauth URI.</p>

                    <div class="alert alert-info">
                        <strong>Secret:</strong> <code>{{ $secret }}</code>
                    </div>
                    <div class="alert alert-secondary small text-break">
                        <strong>otpauth URI:</strong> {{ $otpauth }}
                    </div>

                    <form method="POST" action="{{ route('mfa.enable') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="code">Enter current 6-digit code</label>
                            <input id="code" type="text" name="code" maxlength="6" class="form-control @error('code') is-invalid @enderror" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary" type="submit">Enable MFA</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
