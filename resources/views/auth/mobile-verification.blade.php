@extends('layouts.app')

@section('title', 'Verify Mobile Number')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h1 class="h5 mb-0">Mobile Verification</h1>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Enter your mobile number and request a one-time passcode (OTP). The code expires after 5 minutes.
                        </p>

                        @if (session('status'))
                            <div class="alert alert-info">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if (app()->environment('local') && session('local_mobile_otp_preview'))
                            <div class="alert alert-warning">
                                <strong>Local development OTP preview:</strong><br>
                                Mobile: {{ session('local_mobile_otp_preview.mobile_number') }}<br>
                                OTP: <code>{{ session('local_mobile_otp_preview.otp') }}</code><br>
                                Expires in: {{ session('local_mobile_otp_preview.expires_in_minutes') }} minutes
                            </div>
                        @endif

                        <form method="POST" action="{{ route('mobile.verification.send') }}" class="mb-4">
                            @csrf
                            <div class="mb-3">
                                <label for="mobile_number" class="form-label">Mobile Number</label>
                                <input
                                    id="mobile_number"
                                    name="mobile_number"
                                    type="text"
                                    class="form-control @error('mobile_number') is-invalid @enderror"
                                    value="{{ $mobileNumber }}"
                                    placeholder="+639171234567"
                                    required
                                >
                                @error('mobile_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">Send OTP</button>
                        </form>

                        <form method="POST" action="{{ route('mobile.verification.verify') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="verify_mobile_number" class="form-label">Mobile Number</label>
                                <input
                                    id="verify_mobile_number"
                                    name="mobile_number"
                                    type="text"
                                    class="form-control @error('mobile_number') is-invalid @enderror"
                                    value="{{ old('mobile_number', $mobileNumber) }}"
                                    placeholder="+639171234567"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="otp_code" class="form-label">OTP Code</label>
                                <input
                                    id="otp_code"
                                    name="otp_code"
                                    type="text"
                                    class="form-control @error('otp_code') is-invalid @enderror"
                                    maxlength="6"
                                    inputmode="numeric"
                                    required
                                >
                                @error('otp_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-success">Verify OTP</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
