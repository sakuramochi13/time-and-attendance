@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify-email.css')}}">
@endsection

@section('content')
<div class="verify-container">
    <p class="verify-message">登録していただいたメールアドレスに認証メールを送付しました。</p>
    <p class="verify-message">メール認証を完了してください。</p>
    <a class="verify-btn" href="http://localhost:8025" target="_blank" rel="noopener">
            認証はこちらから
    </a>
    <form method="POST" action="{{ route('verification.send') }}" style="margin-top:12px">
        @csrf
        <button type="submit" class="verify-mail">
            認証メールを再送する
        </button>
    </form>
    @if (session('status') === 'verification-link-sent')
        <p class="verify-success" style="margin-top:8px;">認証用メールを再送しました。</p>
    @endif
</div>
@endsection