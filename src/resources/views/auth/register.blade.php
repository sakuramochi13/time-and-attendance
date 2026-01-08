@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/register.css')}}">
@endsection

@section('content')
<h1 class="register-title">会員登録</h1>
<div class="register-container">
    <form class="register-form" action="{{ route('register') }}" method="POST" novalidate>
        @csrf
            <dl class="register-section">
                <dt class="register-group">
                    <label class="register-group__label" for="name">名前</label>
                </dt>
                <dd class="register-unit">
                    <input class="register-unit__item  @error('name') is-invalid @enderror" type="text" id="name "name="name" value="{{ old('name') }}" autocomplete="name" required>
                </dd>
                <dd class="register-unit__message">
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </dd>
                <dt class="register-group">
                    <label class="register-group__label" for="email">メールアドレス</label>
                </dt>
                <dd class="register-unit">
                    <input class="register-unit__item  @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </dd>
                <dd class="register-unit__message">
                    @error('email') <span class="error">{{ $message }}</span> @enderror
                </dd>
                <dt class="register-group">
                    <label class="register-group__label" for="password" >パスワード</label>
                </dt>
                <dd class="register-unit">
                    <input class="register-unit__item  @error('password') is-invalid @enderror" type="password" id="password" name="password" autocomplete="new-password" required>
                </dd>
                <dd class="register-unit__message">
                    @error('password') <span class="error">{{ $message }}</span> @enderror
                </dd>
                <dt class="register-group">
                    <label class="register-group__label" for="password_confirmation" >パスワード確認</label>
                </dt>
                <dd class="register-unit">
                    <input class="register-unit__item" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                </dd>
                <dd class="register-unit__message">
                </dd>
            </dl>
        <button class="register-button" type="submit">登録する</button>
    </form>
</div>
<div class="login-parts">
    <a class="login-parts__link" href="{{ route('login') }}">ログインはこちら</a>
</div>
@endsection