@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/login.css')}}">
@endsection

@section('content')
<h1 class="login-title">管理者ログイン</h1>

<div class="login-container">
    <form class="login-form" action="{{ url('/login') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="login_type" value="admin">
        <dl class="login-section">
            <dt class="login-group">
                <label class="login-group__label" for="email">メールアドレス</label>
            </dt>
            <dd class="login-unit">
                <input
                    class="login-unit__item @error('email') is-invalid @enderror"
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                    autofocus
                >
            </dd>
            <dd class="login-unit__message">
                @error('email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </dd>
            <dt class="login-group">
                <label class="login-group__label" for="password">パスワード</label>
            </dt>
            <dd class="login-unit">
                <input
                    class="login-unit__item @error('password') is-invalid @enderror"
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </dd>
            <dd class="login-unit__message">
                @error('password')
                    <span class="error">{{ $message }}</span>
                @enderror
            </dd>
        </dl>
        <button class="login-button" type="submit">
            管理者ログインする
        </button>
    </form>
</div>
@endsection


