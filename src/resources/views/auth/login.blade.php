@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/login.css')}}">
@endsection

@section('content')
<h1 class="login-title">ログイン</h1>
<div class="login-container">
    <form class="login-form" action="{{ route('login') }}" method="POST" novalidate>
        @csrf
            <dl class="login-section">
                <dt class="login-group">
                    <label class="login-group__label" for="email" >メールアドレス</label>
                </dt>
                <dd class="login-unit">
                    <input class="login-unit__item @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </dd>
                <dd class="login-unit__message">
                    @error('email')
                    <span class="error">{{ $message }}</span>
                    @enderror
                </dd>
                <dt class="login-group">
                    <label class="login-group__label" for="password" >パスワード</label>
                </dt>
                <dd class="login-unit">
                    <input class="login-unit__item @error('password') is-invalid @enderror" type="password" id="password" name="password" autocomplete="current-password" required>
                </dd>
                <dd class="login-unit__message">
                    @error('password')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </dd>
            </dl>
        <button class="login-button">ログインする</button>
    </form>
</div>
<div class="register-parts">
    <a class="register-parts__link" href="{{ route('register') }}">会員登録はこちら</a>
</div>
@endsection