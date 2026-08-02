@extends('layouts.auth')

@section('content')
<div class="auth-container">
    <div class="auth-grid">
        <section class="auth-brand-panel">
            <div class="auth-logo">JumpWash</div>
            <h1>JUMPWASH LAUNDRY POS</h1>
            <span class="auth-divider"></span>
            <p class="hero-copy">Offline laundry operations delivered with speed, accuracy, and local control.</p>

            <ul class="auth-feature-list">
                <li><span>✓</span> Orders, payments, and customer history</li>
                <li><span>□</span> Pickup, delivery, and staff assignment</li>
                <li><span>▣</span> Barcode tags, receipts, reports, and backups</li>
            </ul>
        </section>

        <section class="auth-login-panel">
            <div>
                <h2>Welcome back</h2>
                <p>Sign in with your staff email address</p>
            </div>

            @if ($errors->any())
                <div class="auth-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                <div class="auth-field">
                    <label class="field-label" for="email">Email address</label>
                    <div class="input-shell">
                        <span>@</span>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="field-input" placeholder="Email address" required autofocus>
                    </div>
                </div>

                <div class="auth-field">
                    <label class="field-label" for="password">Password</label>
                    <div class="input-shell">
                        <span>⌕</span>
                        <input id="password" name="password" type="password" class="field-input" placeholder="Password" required>
                    </div>
                </div>

                <div class="auth-options">
                    <label class="remember-field">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                    <span>Offline LAN access</span>
                </div>

                <button type="submit" class="auth-submit">Sign in</button>
            </form>
            <p class="auth-footer">&copy; {{ now()->year }} JumpWash Laundry Management. All rights reserved.</p>
        </section>
    </div>
</div>
@endsection
