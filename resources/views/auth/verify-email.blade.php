@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-3">Verify Your Email</h4>
                    <p>A verification link was sent to your email. Please verify your account before using the system.</p>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Resend Verification Email</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
